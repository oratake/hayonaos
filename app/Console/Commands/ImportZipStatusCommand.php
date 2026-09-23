<?php declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\UserExportJob;
use Illuminate\Console\Command;

class ImportZipStatusCommand extends Command
{
    protected $signature = 'app:import-status {--id= : インポートジョブの ID}';

    protected $description = 'インポートジョブの状態と結果を確認する';

    public function handle(): int
    {
        $jobId = $this->option('id');

        if ($jobId === null || $jobId === '') {
            $this->error('ジョブ ID を指定してください。');
            return Command::FAILURE;
        }

        $job = UserExportJob::find($jobId);
        if ($job === null) {
            $this->error("ジョブが見つかりません: {$jobId}");
            return Command::FAILURE;
        }

        if ($job->type !== 'import') {
            $this->error("これはインポートジョブではありません (type={$job->type})");
            return Command::FAILURE;
        }

        $this->info('インポートジョブの状態:');
        $this->line("  Job ID:  {$job->id}");
        $this->line("  User:    {$job->user->name} ({$job->user->email})");
        $this->line("  Status:  {$job->status}");

        $metadata = $job->metadata ?? [];
        if ($metadata === []) {
            $this->info('  Metadata: (なし)');
        } else {
            $this->line('  Metadata:');
            foreach ($metadata as $key => $value) {
                if (is_scalar($value) || $value === null) {
                    $this->line("    {$key}: {$value}");
                } else {
                    $this->line('    ' . $key . ': ' . json_encode($value, JSON_PRETTY_PRINT));
                }
            }
        }

        return Command::SUCCESS;
    }
}