<?php declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\UserExportJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ExportCleanupCommand extends Command
{
    protected $signature = 'export:cleanup';

    protected $description = 'Delete expired export data';

    public function handle(): int
    {
        $expiredJobs = UserExportJob::where('type', 'export')
            ->where('status', 'completed')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->get();

        $count = 0;

        foreach ($expiredJobs as $job) {
            if ($job->file_path) {
                Storage::disk('public')->delete($job->file_path);
            }
            $job->delete();
            $count++;
        }

        $this->info("Deleted {$count} expired export jobs.");

        return 0;
    }
}
