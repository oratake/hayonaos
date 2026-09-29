<?php declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;

class BackupCommand extends Command
{
    protected $signature = 'app:backup {--output= : Output directory path (default: current directory)}';
    protected $description = 'データベースと画像ファイルをバックアップする';

    public function handle(): int
    {
        $outputDir = $this->option('output') ?: getcwd();

        // ディレクトリ作成
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $timestamp = now()->format('Ymd_His');
        $backupName = "hayonaos_backup_{$timestamp}";
        $dbDumpPath = "{$outputDir}/{$backupName}.sql";
        $archivePath = "{$outputDir}/{$backupName}.tar.gz";
        $metadataPath = "{$outputDir}/{$backupName}_metadata.json";

        $this->info('バックアップを開始します...');
        $this->info("出力先: {$outputDir}");

        // DB接続情報を取得
        $connection = Config::get('database.default');
        $dbConfig = Config::get("database.connections.{$connection}");
        $host = $dbConfig['host'];
        $port = $dbConfig['port'] ?? 3306;
        $database = $dbConfig['database'];
        $username = $dbConfig['username'];
        $password = $dbConfig['password'];

        // 1. データベースバックアップ
        $this->line('');
        $this->line('データベースをバックアップ中...');

        $cmd = sprintf(
            'mysqldump --host=%s --port=%d --user=%s --password=%s --default-character-set=utf8mb4 %s > %s',
            escapeshellarg($host),
            $port,
            escapeshellarg($username),
            escapeshellarg($password),
            escapeshellarg($database),
            escapeshellarg($dbDumpPath)
        );

        $output = [];
        $returnCode = 0;
        exec($cmd, $output, $returnCode);

        if ($returnCode !== 0 || !file_exists($dbDumpPath) || filesize($dbDumpPath) === 0) {
            $this->error('データベースのバックアップに失敗しました。');
            return Command::FAILURE;
        }

        $this->info('✓ データベースバックアップ完了: ' . number_format(filesize($dbDumpPath)) . ' bytes');

        // 2. 画像ファイルのアーカイブ作成
        $publicPath = storage_path('app/public');
        if (is_dir($publicPath)) {
            $this->line('画像ファイルをアーカイブ中...');

            $cmd = sprintf(
                'cd %s && tar czf %s box_photos box_photo_thumbnails',
                escapeshellarg($publicPath),
                escapeshellarg($archivePath)
            );

            $output = [];
            $returnCode = 0;
            exec($cmd, $output, $returnCode);

            if ($returnCode !== 0 || !file_exists($archivePath)) {
                $this->warn('画像アーカイブの作成に失敗しました。DBバックアップのみ出力します。');
            } else {
                $this->info('✓ 画像アーカイブ完了: ' . number_format(filesize($archivePath)) . ' bytes');
            }
        } else {
            $this->warn('publicストレージディレクトリが見つかりません。');
        }

        // 3. メタデータファイル
        $metadata = [
            'backup_name' => $backupName,
            'created_at' => now()->toISOString(),
            'laravel_version' => app()->version(),
            'php_version' => PHP_VERSION,
            'db_host' => $host,
            'db_database' => $database,
        ];
        file_put_contents(
            $metadataPath,
            json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );

        $this->info('');
        $this->info('バックアップ完了！');
        $this->info("  DB dump:   {$dbDumpPath}");
        if (file_exists($archivePath)) {
            $this->info("  Archive:  {$archivePath}");
        }
        $this->info("  Metadata: {$metadataPath}");

        return Command::SUCCESS;
    }
}
