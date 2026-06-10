<?php declare(strict_types=1);

namespace App\Jobs;

use App\Models\Box;
use App\Models\BoxPhoto;
use App\Models\User;
use App\Models\UserExportJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

class ImportUserDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public User $user,
        public UserExportJob $job,
        public string $zipPath,
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->job->update([
            'status' => 'processing',
        ]);

        $zip = new ZipArchive();
        $extractDir = storage_path("app/private/imports/import-{$this->job->id}");

        try {
            $zip->open($this->zipPath);

            // Extract to temp directory
            $zip->extractTo($extractDir);
            $zip->close();

            // Read metadata.json
            $metadataContent = file_get_contents("{$extractDir}/metadata.json");
            $metadata = json_decode($metadataContent, true);

            // Check version
            $formatVersion = $metadata['export_format_version'] ?? '1';
            if (!in_array($formatVersion, ['1', '2'])) {
                $this->job->update([
                    'status' => 'failed',
                    'metadata' => [
                        'error' => 'このエクスポートデータは古い形式です。インポートできません。',
                    ],
                ]);
                $this->cleanup($extractDir);
                return;
            }

            $boxesImported = 0;
            $photosImported = 0;
            $conflicts = [];

            // Process each box
            foreach ($metadata['boxes'] as $boxData) {
                // Format v2: use encoded_name for directory path
                $boxDirName = ($formatVersion >= '2' && isset($boxData['encoded_name']))
                    ? $boxData['encoded_name']
                    : $boxData['name'];
                $boxName = $boxData['name'];

                // Check for name conflict
                $newName = $boxName;
                $suffix = 0;

                while ($this->user->boxes()->where('name', $newName)->exists()) {
                    $suffix++;
                    $newName = $boxName . ($suffix === 1 ? ' (imported)' : " (imported-{$suffix})");
                }

                if ($newName !== $boxName) {
                    $conflicts[] = [
                        'old_name' => $boxName,
                        'new_name' => $newName,
                    ];
                }

                // Create box
                $box = $this->user->boxes()->create([
                    'uuid' => (string) Str::uuid(),
                    'name' => $newName,
                    'description' => $boxData['description'],
                ]);

                // Process photos
                $boxPhotosDir = "boxes/{$boxDirName}";
                $boxDirPath = "{$extractDir}/{$boxPhotosDir}";

                if (is_dir($boxDirPath)) {
                    $files = scandir($boxDirPath);
                    foreach ($files as $filename) {
                        if ($filename === '.' || $filename === '..') {
                            continue;
                        }

                        // Skip thumbnail files (they are handled separately below)
                        if ($formatVersion >= '2' && str_starts_with($filename, 'thumb_')) {
                            continue;
                        }

                        $filePath = "{$boxDirPath}/{$filename}";

                        if (!is_file($filePath)) {
                            continue;
                        }

                        // Format v2: encoded filename is "uuid_originalname.ext"
                        $originalFilename = $filename;
                        if ($formatVersion >= '2') {
                            // Extract original name from uuid_originalname.ext
                            $parts = explode('_', $filename, 2);
                            if (count($parts) === 2 && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $parts[0])) {
                                $originalFilename = $parts[1];
                            } else {
                                // No separator found (v1 or v2 without original name): use basename without extension
                                $originalFilename = pathinfo($filename, PATHINFO_FILENAME);
                            }
                        }

                        // Store original file
                        $fileContent = file_get_contents($filePath);
                        $storedPath = "box_photos/{$originalFilename}";
                        Storage::disk('public')->put($storedPath, $fileContent);

                        // Find matching photo data from metadata
                        $photoMeta = null;
                        if ($boxData['photos'] ?? null) {
                            foreach ($boxData['photos'] as $p) {
                                if ($formatVersion >= '2') {
                                    if ($p['original_filename'] === $originalFilename) {
                                        $photoMeta = $p;
                                        break;
                                    }
                                } else {
                                    if (basename($p['file_path']) === $filename) {
                                        $photoMeta = $p;
                                        break;
                                    }
                                }
                            }
                        }

                        // Create BoxPhoto record
                        BoxPhoto::create([
                            'uuid' => (string) Str::uuid(),
                            'box_id' => $box->id,
                            'file_path' => $storedPath,
                            'thumbnail_file_path' => null, // Will be set after thumbnail processing
                            'caption' => $photoMeta['caption'] ?? null,
                        ]);

                        $photosImported++;
                    }

                    // Process thumbnails separately for v2
                    if ($formatVersion >= '2') {
                        foreach ($files as $filename) {
                            if (!str_starts_with($filename, 'thumb_')) {
                                continue;
                            }

                            $thumbFilePath = "{$boxDirPath}/{$filename}";
                            if (!is_file($thumbFilePath)) {
                                continue;
                            }

                            // Extract original filename: thumb_uuid_IMG_1234.jpg → IMG_1234.jpg
                            $thumbWithoutPrefix = substr($filename, 6); // Remove 'thumb_'
                            $thumbParts = explode('_', $thumbWithoutPrefix, 2);
                            if (count($thumbParts) === 2 && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $thumbParts[0])) {
                                $thumbOriginalName = $thumbParts[1];
                            } else {
                                $thumbOriginalName = $thumbWithoutPrefix;
                            }

                            // Store thumbnail with original name
                            $thumbContent = file_get_contents($thumbFilePath);
                            $thumbStoredPath = "box_photo_thumbnails/thumb_{$thumbOriginalName}";
                            Storage::disk('public')->put($thumbStoredPath, $thumbContent);

                            // Update the BoxPhoto record with thumbnail path
                            $box->photos()->where('file_path', "box_photos/{$thumbOriginalName}")->update([
                                'thumbnail_file_path' => $thumbStoredPath,
                            ]);
                        }
                    }
                }

                $boxesImported++;
            }

            $this->job->update([
                'status' => 'completed',
                'metadata' => [
                    'boxes_imported' => $boxesImported,
                    'photos_imported' => $photosImported,
                    'conflicts' => $conflicts,
                ],
            ]);
        } catch (\Throwable $e) {
            $this->job->update([
                'status' => 'failed',
                'metadata' => [
                    'error' => $e->getMessage(),
                ],
            ]);
        } finally {
            $this->cleanup($extractDir);

            // Clean up the uploaded zip file
            if (file_exists($this->zipPath)) {
                unlink($this->zipPath);
            }
        }
    }

    /**
     * Cleanup extracted files.
     */
    private function cleanup(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = glob("{$dir}/*");
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            } elseif (is_dir($file)) {
                $this->cleanupRecursive($file);
            }
        }
        rmdir($dir);
    }

    /**
     * Recursively delete a directory.
     */
    private function cleanupRecursive(string $dir): void
    {
        $files = glob("{$dir}/*");
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            } elseif (is_dir($file)) {
                $this->cleanupRecursive($file);
            }
        }
        rmdir($dir);
    }
}
