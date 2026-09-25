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

class ExportUserDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public User $user,
        public UserExportJob $exportJob,
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        \Log::info('[ExportJob] handle started', [
            'job_id' => $this->exportJob->id,
            'user_id' => $this->user->id,
        ]);

        $this->exportJob->update([
            'status' => 'processing',
        ]);

        \Log::info('[ExportJob] status updated to processing', ['job_id' => $this->exportJob->id]);

        $outputPath = $this->exportJob->metadata['output_path'] ?? null;

        $filename = 'hayonaos-export-'.now()->format('Ymd').'-'.Str::random(8).'.zip';
        $zipPath = storage_path("app/private/exports/{$filename}");
        $zipDir = dirname($zipPath);
        if (! is_dir($zipDir)) {
            mkdir($zipDir, 0755, true);
        }

        $zip = new ZipArchive();

        try {
            $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

            // Collect all boxes with their photos
            $boxes = $this->user->boxes()->with('photos')->get();

            // Build metadata
            $metadata = [
                'export_format_version' => '3',
                'exported_at' => now()->toIso8601String(),
                'app_version' => config('app.version', '11.x'),
                'boxes' => [],
            ];

            foreach ($boxes as $box) {
                // Collect photos for this box
                $boxPhotos = [];
                foreach ($box->photos as $photo) {
                    if ($photo->file_path) {
                        $uuid = $photo->uuid;
                        $basename = basename($photo->file_path);
                        $photoFilename = "{$uuid}_{$basename}";
                        $sourcePath = Storage::disk('public')->path($photo->file_path);

                        if (file_exists($sourcePath)) {
                            // Flat structure: all files in root of zip
                            $zip->addFile($sourcePath, "{$photoFilename}");
                        }
                    }

                    $boxPhotos[] = [
                        'uuid' => $photo->uuid,
                        'file_path' => $photo->file_path,
                        'caption' => $photo->caption,
                        'created_at' => $photo->created_at->toIso8601String(),
                        'original_filename' => $photo->file_path ? basename($photo->file_path) : null,
                    ];
                }

                $metadata['boxes'][] = [
                    'uuid' => $box->uuid,
                    'name' => $box->name,
                    'description' => $box->description,
                    'created_at' => $box->created_at->toIso8601String(),
                    'photos' => $boxPhotos,
                ];
            }

            // Add metadata.json to root of zip
            $zip->addFromString(
                'metadata.json',
                json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
            );

            $zip->close();

            // Move to public storage
            $publicPath = "exports/{$filename}";
            Storage::disk('public')->put($publicPath, file_get_contents($zipPath));
            unlink($zipPath);

            // Update job record
            $jobMetadata = [
                'filename' => $filename,
                'boxes_count' => $boxes->count(),
                'photos_count' => $boxes->sum(fn ($b) => $b->photos->count()),
            ];
            if ($outputPath) {
                $jobMetadata['output_path'] = $outputPath;
            }

            $this->exportJob->update([
                'status' => 'completed',
                'file_path' => $publicPath,
                'expires_at' => now()->addHours(24),
                'metadata' => $jobMetadata,
            ]);

            \Log::info('[ExportJob] export completed', [
                'job_id' => $this->exportJob->id,
                'boxes_count' => $boxes->count(),
                'photos_count' => $boxes->sum(fn ($b) => $b->photos->count()),
            ]);

            if ($outputPath) {
                $targetDir = dirname($outputPath);
                if (! is_dir($targetDir)) {
                    mkdir($targetDir, 0755, true);
                }
                $target = is_dir($outputPath) ? $outputPath . basename($filename) : $outputPath;
                $source = Storage::disk('public')->path($publicPath);
                if (realpath($target) === false || realpath($target) !== realpath($source)) {
                    copy($source, $target);
                    \Log::info('[ExportJob] export copied to --path', [
                        'job_id' => $this->exportJob->id,
                        'target' => $target,
                    ]);
                }
            }

            // Clean up previous export after successful new export
            $previousExport = $this->user->exportJobs()
                ->where('status', 'completed')
                ->where('id', '!=', $this->exportJob->id)
                ->orderByDesc('created_at')
                ->first();

            if ($previousExport && $previousExport->file_path) {
                Storage::disk('public')->delete($previousExport->file_path);
                $previousExport->delete();
                \Log::info('[ExportJob] cleaned up previous export', [
                    'job_id' => $this->exportJob->id,
                    'previous_job_id' => $previousExport->id,
                ]);
            }
        } catch (\Throwable $e) {
            \Log::error('[ExportJob] export failed', [
                'job_id' => $this->exportJob->id,
                'error' => $e->getMessage(),
            ]);

            $this->exportJob->update([
                'status' => 'failed',
                'metadata' => [
                    'error' => $e->getMessage(),
                ],
            ]);

            if (file_exists($zipPath)) {
                unlink($zipPath);
            }

            if ($zip && $zip->status == ZipArchive::ER_OPEN) {
                $zip->close();
            }

            throw $e;
        }
    }
}
