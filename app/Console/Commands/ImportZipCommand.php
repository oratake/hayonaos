<?php declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\ImportUserDataJob;
use App\Models\BoxPhoto;
use App\Models\User;
use App\Models\UserExportJob;
use Illuminate\Console\Command;
use ZipArchive;

class ImportZipCommand extends Command
{
    protected $signature = 'app:import-zip {--path= : 導入する ZIP ファイルのパス} {--user= : 対象ユーザーのメールアドレス} {--force : 既存 Box を全削除して導入 (警告 + 確認) } {--keep-file : 導入後に ZIP を保持}';

    protected $description = 'FTP に落ちたエクスポート ZIP をキュー経由でインポートする';

    public function handle(): int
    {
        $zipPath = $this->option('path');
        $userEmail = $this->option('user');
        $force = (bool) $this->option('force');
        $keepFile = (bool) $this->option('keep-file');

        if ($zipPath === null || $zipPath === '') {
            $this->error('ZIP ファイルを指定してください。');
            $this->info('例: php artisan app:import-zip --path=/path/export.zip --user=user@example.com');
            return Command::FAILURE;
        }

        if (!file_exists($zipPath) || !is_readable($zipPath)) {
            $this->error("ファイルが見つからない、または読み取れません: {$zipPath}");
            return Command::FAILURE;
        }

        $test = new ZipArchive();
        if ($test->open($zipPath) !== true) {
            $this->error("有効な ZIP ファイルではありません: {$zipPath}");
            return Command::FAILURE;
        }
        $test->close();

        if ($userEmail === null || $userEmail === '') {
            $this->error('対象ユーザーのメールアドレスを指定してください。');
            return Command::FAILURE;
        }

        $user = User::where('email', $userEmail)->first();
        if ($user === null) {
            $this->error("ユーザーが見つかりません: {$userEmail}");
            return Command::FAILURE;
        }

        if ($force) {
            $boxCount = $user->boxes()->count();
            $photoCount = BoxPhoto::whereIn('box_id', $user->boxes()->pluck('id'))->count();

            $this->error('⚠  対象ユーザーの既存データが全削除されます:');
            $this->line("  Box:  {$boxCount} 件");
            $this->line("  Photo: {$photoCount} 件");
            $this->error('  実行しますか?');

            if ($this->confirm('実行しますか? [y/N]') === false) {
                $this->info('実行を中止しました。');
                return Command::FAILURE;
            }
        }

        $job = UserExportJob::create([
            'user_id' => $user->id,
            'type' => 'import',
            'status' => 'pending',
            'file_path' => $zipPath,
            'metadata' => [
                'source' => 'cli',
                'original_filename' => pathinfo($zipPath, PATHINFO_FILENAME),
                'force' => $force,
                'keep_file' => $keepFile,
            ],
        ]);

        ImportUserDataJob::dispatch(
            $user,
            $job,
            $zipPath,
            $force,
            $keepFile,
        );

        $this->info('インポートジョブを送信しました。');
        $this->info("  Job ID: {$job->id}");
        $this->info("  確認: php artisan app:import-status --id={$job->id}");

        return Command::SUCCESS;
    }
}