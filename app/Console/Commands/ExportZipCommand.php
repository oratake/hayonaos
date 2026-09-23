<?php declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\ExportUserDataJob;
use App\Models\User;
use App\Models\UserExportJob;
use Illuminate\Console\Command;

class ExportZipCommand extends Command
{
    protected $signature = 'app:export-zip {--user= : 対象ユーザーのメールアドレス}';

    protected $description = '全データ (ボックス・写真を含む) をキュー経由でエクスポートする';

    public function handle(): int
    {
        $userEmail = $this->option('user');

        if ($userEmail === null || $userEmail === '') {
            $this->error('対象ユーザーのメールアドレスを指定してください。');
            $this->info('例: php artisan app:export-zip --user=user@example.com');
            return Command::FAILURE;
        }

        $user = User::where('email', $userEmail)->first();
        if ($user === null) {
            $this->error("ユーザーが見つかりません: {$userEmail}");
            return Command::FAILURE;
        }

        $job = UserExportJob::create([
            'user_id' => $user->id,
            'type' => 'all',
            'status' => 'pending',
            'metadata' => [
                'export_type' => 'all',
                'source' => 'cli',
                'requested_at' => now()->toIso8601String(),
            ],
        ]);

        ExportUserDataJob::dispatch($user, $job);

        $this->info('エクスポートジョブを送信しました。');
        $this->line("  Job ID: {$job->id}");
        $this->line('  確認: php artisan app:export-status --id=' . $job->id);

        return Command::SUCCESS;
    }
}
