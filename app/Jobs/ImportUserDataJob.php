<?php declare(strict_types=1);

namespace App\Jobs;

use App\Models\BoxPhoto;
use App\Models\User;
use App\Models\UserExportJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class ImportUserDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public UserExportJob $exportJob,
        public string $zipPath,
        public bool $force = false,
        public bool $keepFile = false,
    ) {
    }

    public function handle(): void
    {
        $this->exportJob->update([
            'status' => 'processing',
        ]);

        $extractDir = storage_path("app/private/imports/import-{$this->exportJob->id}");

        $stats = ['boxes' => 0, 'photos' => 0, 'conflicts' => []];
        $success = false;

        try {
            $zip = new ZipArchive();
            if ($zip->open($this->zipPath) !== true) {
                throw new \Exception('ZIPファイルを開けられません。');
            }

            $zip->extractTo($extractDir);
            $zip->close();

            $metadataContent = file_get_contents("{$extractDir}/metadata.json");
            if ($metadataContent === false) {
                throw new \Exception('metadata.json が読めませんでした。');
            }
            $metadata = json_decode($metadataContent, true);
            if (!is_array($metadata)) {
                throw new \Exception('metadata.json が無効です。');
            }

            $formatVersion = (string) ($metadata['export_format_version'] ?? '1');
            if (!in_array($formatVersion, ['1', '2', '3'], true)) {
                throw new \Exception('このエクスポートデータは古い形式です。インポートできません。');
            }

            // fail-fast: 名前衝突を検出したら、データ作成前に失敗（zipは保持）
            if (!$this->force) {
                $conflicts = $this->findConflictingNames($metadata);
                if ($conflicts !== []) {
                    $existing = $this->exportJob->metadata ?? [];
                    $this->exportJob->update([
                        'status' => 'failed',
                        'metadata' => array_merge($existing, [
                            'error' => sprintf('Box名が重複しています: %s。--force を指定して既存を削除して再実行してください。', implode(', ', $conflicts)),
                            'conflicts' => $conflicts,
                        ]),
                    ]);
                    return;
                }
            } else {
                $this->deleteAllBoxesAndPhotos();
            }

            if ($formatVersion === '3') {
                $this->importV3($metadata, $extractDir, $stats);
            } else {
                $this->importLegacy($metadata, $extractDir, $formatVersion, $stats);
            }

            $existing = $this->exportJob->metadata ?? [];
            $this->exportJob->update([
                'status' => 'completed',
                'metadata' => array_merge($existing, [
                    'boxes_imported' => $stats['boxes'],
                    'photos_imported' => $stats['photos'],
                ]),
            ]);
            $success = true;
        } catch (\Throwable $e) {
            $existing = $this->exportJob->metadata ?? [];
            $this->exportJob->update([
                'status' => 'failed',
                'metadata' => array_merge($existing, [
                    'error' => $e->getMessage(),
                ]),
            ]);
        } finally {
            $this->cleanup($extractDir);

            if (!$this->keepFile && $success) {
                if (file_exists($this->zipPath)) {
                    @unlink($this->zipPath);
                }
            }
        }
    }

    private function findConflictingNames(array $metadata): array
    {
        $existing = $this->user->boxes()->pluck('name')->toArray();
        $conflicts = [];
        foreach (($metadata['boxes'] ?? []) as $boxData) {
            $name = $boxData['name'] ?? null;
            if ($name !== null && in_array($name, $existing, true)) {
                $conflicts[] = $name;
            }
        }
        return $conflicts;
    }

    private function deleteAllBoxesAndPhotos(): void
    {
        $boxIds = $this->user->boxes()->pluck('id');
        BoxPhoto::whereIn('box_id', $boxIds)->delete();
        $this->user->boxes()->delete();
    }

    private function importV3(array $metadata, string $extractDir, array &$stats): void
    {
        // 1) root の平たいファイル {photo_uuid}_{basename} → photo uuid でのマップ
        $fileMap = [];
        foreach (scandir($extractDir) ?: [] as $filename) {
            if ($filename === '.' || $filename === '..') {
                continue;
            }
            $filePath = "{$extractDir}/{$filename}";
            if (!is_file($filePath)) {
                continue;
            }
            $parts = explode('_', $filename, 2);
            if (count($parts) === 2 && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $parts[0])) {
                $fileMap[$parts[0]] = [
                    'filename' => $filename,
                    'path' => $filePath,
                    'originalName' => $parts[1],
                ];
            }
        }

        // 2) box 作成（uuid は自動生成。v2 と同様に元 uuid を保持しない）
        foreach (($metadata['boxes'] ?? []) as $boxData) {
            $name = $boxData['name'] ?? 'unnamed';

            $box = $this->user->boxes()->create([
                'name' => $name,
                'description' => $boxData['description'] ?? null,
            ]);
            $stats['boxes']++;

            // 3) photo 作成: v3 metadata では各 box の photos が配列内に既に分組済み
            foreach (($boxData['photos'] ?? []) as $photoData) {
                $photoUuid = $photoData['uuid'] ?? null;
                $file = $fileMap[$photoUuid] ?? null;
                if ($file === null) {
                    $stats['warnings'][] = 'Photo file not found: ' . ($photoData['file_path'] ?? $photoUuid ?? '');
                    continue;
                }

                $storedPath = "box_photos/{$file['originalName']}";
                Storage::disk('public')->put($storedPath, file_get_contents($file['path']));

                $box->photos()->create([
                    'file_path' => $storedPath,
                    'caption' => $photoData['caption'] ?? null,
                ]);
                $stats['photos']++;
            }
        }
    }

    private function importLegacy(array $metadata, string $extractDir, string $formatVersion, array &$stats): void
    {
        $v2 = $formatVersion >= '2';

        foreach (($metadata['boxes'] ?? []) as $boxData) {
            $boxName = $boxData['name'];
            if ($boxName === null || $boxName === '') {
                continue;
            }

            $box = $this->user->boxes()->create([
                'name' => $boxName,
                'description' => $boxData['description'] ?? null,
            ]);
            $stats['boxes']++;

            $boxDirName = ($v2 && isset($boxData['encoded_name']))
                ? rawurldecode($boxData['encoded_name'])
                : $boxName;
            $boxDirPath = "{$extractDir}/boxes/{$boxDirName}";

            if (is_dir($boxDirPath)) {
                foreach (scandir($boxDirPath) ?: [] as $filename) {
                    if ($filename === '.' || $filename === '..') {
                        continue;
                    }
                    if ($v2 && str_starts_with($filename, 'thumb_')) {
                        continue;
                    }

                    $filePath = "{$boxDirPath}/{$filename}";
                    if (!is_file($filePath)) {
                        continue;
                    }

                    $originalFilename = $filename;
                    if ($v2) {
                        $parts = explode('_', $filename, 2);
                        if (count($parts) === 2 && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $parts[0])) {
                            $originalFilename = $parts[1];
                        } else {
                            $originalFilename = pathinfo($filename, PATHINFO_FILENAME);
                        }
                    }

                    $storedPath = "box_photos/{$originalFilename}";
                    Storage::disk('public')->put($storedPath, file_get_contents($filePath));

                    // metadata から caption 検索
                    $caption = null;
                    if (!empty($boxData['photos'])) {
                        foreach ($boxData['photos'] as $p) {
                            if (($p['original_filename'] ?? null) === $originalFilename || basename($p['file_path'] ?? '') === $filename) {
                                $caption = $p['caption'] ?? null;
                                break;
                            }
                        }
                    }

                    $box->photos()->create([
                        'file_path' => $storedPath,
                        'caption' => $caption,
                    ]);
                    $stats['photos']++;
                }
            }

            // v2: thumbnail 処理
            if ($v2 && is_dir($boxDirPath)) {
                foreach (scandir($boxDirPath) ?: [] as $filename) {
                    if (!str_starts_with($filename, 'thumb_')) {
                        continue;
                    }
                    $thumbFilePath = "{$boxDirPath}/{$filename}";
                    if (!is_file($thumbFilePath)) {
                        continue;
                    }

                    $thumbWithoutPrefix = substr($filename, 6);
                    $thumbParts = explode('_', $thumbWithoutPrefix, 2);
                    if (count($thumbParts) === 2 && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $thumbParts[0])) {
                        $thumbOriginalName = $thumbParts[1];
                    } else {
                        $thumbOriginalName = $thumbWithoutPrefix;
                    }

                    $thumbStoredPath = "box_photo_thumbnails/thumb_{$thumbOriginalName}";
                    Storage::disk('public')->put($thumbStoredPath, file_get_contents($thumbFilePath));
                    $box->photos()->where('file_path', "box_photos/{$thumbOriginalName}")->update([
                        'thumbnail_file_path' => $thumbStoredPath,
                    ]);
                }
            }
        }
    }

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