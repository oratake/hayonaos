<?php declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BoxPhoto;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Throwable;

class GenerateMissingThumbnails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:generate-missing-thumbnails {--overwrite : 既存のサムネイルも上書きする}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'thumbnail_file_pathが未設定の写真に対してサムネイルを生成します。';

    /**
     * Execute the console command.
     */
    public function handle(ImageManager $imageManager): int
    {
        $this->info('サムネイル生成処理を開始します...');
        $overwrite = $this->option('overwrite');

        $query = BoxPhoto::query();

        if (!$overwrite) {
            $query->whereNull('thumbnail_file_path');
        }
        // else: overwriteが指定された場合は全件対象 (file_pathが存在するもの)
        $query->whereNotNull('file_path');


        $photosToProcess = $query->get();

        if ($photosToProcess->isEmpty()) {
            $this->info('処理対象の写真はありませんでした。');
            return Command::SUCCESS;
        }

        $progressBar = $this->output->createProgressBar($photosToProcess->count());
        $progressBar->start();

        $successCount = 0;
        $errorCount = 0;

        foreach ($photosToProcess as $photo) {
            try {
                if (!Storage::disk('public')->exists($photo->file_path)) {
                    $this->warn(" Photo ID: {$photo->id} - 元ファイルが見つかりません: {$photo->file_path}");
                    $errorCount++;
                    $progressBar->advance();
                    continue;
                }

                // 既存のサムネイルを上書きする場合、古いサムネイルを削除
                if ($overwrite && $photo->thumbnail_file_path && Storage::disk('public')->exists($photo->thumbnail_file_path)) {
                    Storage::disk('public')->delete($photo->thumbnail_file_path);
                }

                $thumbnailImage = $imageManager->read(storage_path('app/public/' . $photo->file_path));

                $thumbnailImage->cover(150, 150); // 150x150 にリサイズ・クロップ

                $thumbnailFilename = 'thumb_' . basename($photo->file_path);
                $thumbnailDirectory = 'box_photo_thumbnails';
                Storage::disk('public')->makeDirectory($thumbnailDirectory); // ディレクトリがなければ作成

                $thumbnailPath = $thumbnailDirectory . '/' . $thumbnailFilename;
                $thumbnailImage->save(Storage::disk('public')->path($thumbnailPath));

                $photo->thumbnail_file_path = $thumbnailPath;
                $photo->save();

                $successCount++;
            } catch (Throwable $e) {
                $this->error(" Photo ID: {$photo->id} - サムネイル生成エラー: " . $e->getMessage());
                $errorCount++;
            }
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->info("\nサムネイル生成処理が完了しました。");
        $this->info("成功: {$successCount}件, エラー: {$errorCount}件");

        return Command::SUCCESS;
    }
}
