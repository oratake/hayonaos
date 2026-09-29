<?php declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;

class RestoreCommand extends Command
{
    protected $signature = 'app:restore {--file= : Backup file path (.sql or .tar.gz)}';
    protected $description = 'バックアップからデータベースと画像ファイルをリストアする';

    public function handle(): int
    {
        $backupFile = $this->option('file');

        if (!$backupFile) {
            $this->error('バックアップファイルを指定してください。');
            $this->info('例: php artisan app:restore --file=/path/to/hayonaos_backup_20250101_120000.sql');
            return Command::FAILURE;
        }

        if (!file_exists($backupFile)) {
            $this->error("ファイルが見つかりません: {$backupFile}");
            return Command::FAILURE;
        }

        $fileExt = pathinfo($backupFile, PATHINFO_EXTENSION);

        if ($fileExt === 'sql') {
            // データベースのリストア
            return $this->restoreDatabase($backupFile);
        } elseif ($fileExt === 'gz') {
            // 画像のリストア
            return $this->restoreImages($backupFile);
        }

        $this->error("対応していないファイル形式です: {$fileExt}");
        return Command::FAILURE;
    }

    protected function restoreDatabase(string $sqlFile): int
    {
        $connection = Config::get('database.default');
        $dbConfig = Config::get("database.connections.{$connection}");
        $host = $dbConfig['host'];
        $port = $dbConfig['port'] ?? 3306;
        $database = $dbConfig['database'];
        $username = $dbConfig['username'];
        $password = $dbConfig['password'];

        $this->info("データベースをリストア中...");
        $this->line("  DB: {$database} @ {$host}:{$port}");

        $cmd = sprintf(
            'mysql --host=%s --port=%d --user=%s --password=%s --default-character-set=utf8mb4 %s < %s',
            escapeshellarg($host),
            $port,
            escapeshellarg($username),
            escapeshellarg($password),
            escapeshellarg($database),
            escapeshellarg($sqlFile)
        );

        $output = [];
        $returnCode = 0;
        exec($cmd, $output, $returnCode);

        if ($returnCode !== 0) {
            $this->error('データベースのリストアに失敗しました。');
            return Command::FAILURE;
        }

        $this->info('データベースのリストア完了！');
        return Command::SUCCESS;
    }

    protected function restoreImages(string $archiveFile): int
    {
        $publicPath = storage_path('app/public');

        // ディレクトリ作成
        if (!is_dir($publicPath)) {
            mkdir($publicPath, 0755, true);
        }

        $this->info("画像ファイルをリストア中...");

        $cmd = sprintf(
            'cd %s && tar xzf %s',
            escapeshellarg($publicPath),
            escapeshellarg($archiveFile)
        );

        $output = [];
        $returnCode = 0;
        exec($cmd, $output, $returnCode);

        if ($returnCode !== 0) {
            $this->error('画像のリストアに失敗しました。');
            return Command::FAILURE;
        }

        $this->info('画像のリストア完了！');
        $this->info("  出力先: {$publicPath}");
        return Command::SUCCESS;
    }
}
