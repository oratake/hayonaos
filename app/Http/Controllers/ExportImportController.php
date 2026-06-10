<?php declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\ExportUserDataJob;
use App\Jobs\ImportUserDataJob;
use App\Models\UserExportJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ExportImportController extends Controller
{
    /**
     * エクスポート/インポートページ表示
     */
    public function index()
    {
        $exportJob = Auth::user()->exportJobs()
            ->where('type', 'export')
            ->where('status', 'completed')
            ->orderBy('created_at', 'desc')
            ->first();

        return inertia('ExportImport', [
            'exportJob' => $exportJob,
        ]);
    }

    /**
     * エクスポート開始
     */
    public function startExport(Request $request): \Illuminate\Http\RedirectResponse
    {
        \Log::info('[Export] startExport called', [
            'user_id' => Auth::id(),
            'type' => $request->input('type'),
        ]);

        $request->validate([
            'type' => 'required|in:all,boxes_only,photos_only',
        ]);

        // キューに既存のpending/processingジョブがあれば更新
        $existing = Auth::user()->exportJobs()
            ->whereIn('status', ['pending', 'processing'])
            ->first();

        if ($existing) {
            \Log::info('[Export] updating existing job', ['job_id' => $existing->id, 'type' => $request->input('type')]);
            $existing->update([
                'type' => $request->input('type'),
                'status' => 'pending',
                'metadata' => [
                    'export_type' => $request->input('type'),
                ],
            ]);
            // キューに再ディスパッチ
            \App\Jobs\ExportUserDataJob::dispatch(Auth::user(), $existing);
            return redirect()->back()->with('status', 'エクスポートを再開始しました。');
        }

        // 完了済みのエクスポートがあれば削除
        $oldExport = Auth::user()->exportJobs()
            ->where('type', 'export')
            ->where('status', 'completed')
            ->first();

        if ($oldExport && $oldExport->file_path) {
            \Log::info('[Export] deleting old export', ['job_id' => $oldExport->id, 'file_path' => $oldExport->file_path]);
            Storage::disk('public')->delete($oldExport->file_path);
            $oldExport->delete();
        }

        $job = Auth::user()->exportJobs()->create([
            'type' => $request->input('type'),
            'status' => 'pending',
            'metadata' => [
                'export_type' => $request->input('type'),
            ],
        ]);

        \Log::info('[Export] job created', ['job_id' => $job->id, 'status' => $job->status]);

        \App\Jobs\ExportUserDataJob::dispatch(Auth::user(), $job);

        \Log::info('[Export] job dispatched', ['job_id' => $job->id]);

        return redirect()->back()->with('status', 'エクスポートを開始しました。');
    }

    /**
     * エクスポートステータス確認（ポーリング用）
     */
    public function getStatus(Request $request): \Illuminate\Http\JsonResponse
    {
        \Log::info('[Export] getStatus called', [
            'user_id' => Auth::id(),
            'ip' => $request->ip(),
        ]);

        $exportJob = Auth::user()->exportJobs()
            ->where('type', 'export')
            ->orderBy('created_at', 'desc')
            ->first();

        \Log::info('[Export] job found', [
            'job_id' => $exportJob?->id,
            'status' => $exportJob?->status,
        ]);

        if (!$exportJob) {
            return response()->json([
                'status' => 'none',
            ]);
        }

        return response()->json([
            'status' => $exportJob->status,
            'metadata' => $exportJob->metadata,
            'expires_at' => $exportJob->expires_at?->toIso8601String(),
            'file_path' => $exportJob->file_path,
        ]);
    }

    /**
     * ZIPファイルダウンロード
     */
    public function download(Request $request): \Illuminate\Http\JsonResponse
    {
        $exportJob = Auth::user()->exportJobs()
            ->where('type', 'export')
            ->where('status', 'completed')
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$exportJob || !$exportJob->file_path) {
            return response()->json(['error' => 'エクスポートデータが見つかりません。'], 404);
        }

        if (!$exportJob->expires_at || $exportJob->expires_at->isPast()) {
            return response()->json(['error' => 'エクスポートデータが期限切れです。'], 410);
        }

        $file = Storage::disk('public')->path($exportJob->file_path);

        if (!file_exists($file)) {
            return response()->json(['error' => 'ファイルが存在しません。'], 404);
        }

        $filename = $exportJob->metadata['filename'] ?? "hayonaos-export-{$exportJob->created_at->format('Ymd')}.zip";
        return response()->download($file, $filename);
    }

    /**
     * エクスポート削除
     */
    public function delete(Request $request): \Illuminate\Http\JsonResponse
    {
        $exportJob = Auth::user()->exportJobs()
            ->where('type', 'export')
            ->where('status', 'completed')
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$exportJob) {
            return response()->json(['status' => 'not_found']);
        }

        if ($exportJob->file_path) {
            Storage::disk('public')->delete($exportJob->file_path);
        }

        $exportJob->delete();

        return response()->json(['status' => 'deleted']);
    }

    /**
     * データインポート
     */
    public function import(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:zip|max:10240',
        ]);

        $file = $request->file('file');

        // ZIPファイルを一時的に保存
        $tempPath = storage_path("app/private/imports/import-{$request->user()->id}-" . time() . ".zip");
        $file->move(storage_path('app/private/imports'), basename($tempPath));

        $job = Auth::user()->exportJobs()->create([
            'type' => 'import',
            'status' => 'pending',
            'metadata' => [
                'original_filename' => $file->getClientOriginalName(),
            ],
        ]);

        ImportUserDataJob::dispatch(Auth::user(), $job, $tempPath);

        return response()->json([
            'status' => 'started',
            'message' => 'インポートを開始しました。',
        ]);
    }
}
