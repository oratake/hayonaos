import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, router } from '@inertiajs/react';
import axios from 'axios';
import { useEffect, useState, useRef } from 'react';

export default function ExportImport({ auth, exportJob }) {
    const [status, setStatus] = useState(exportJob ? 'completed' : 'none');
    const [metadata, setMetadata] = useState(exportJob ? exportJob.metadata : null);
    const [expiresAt, setExpiresAt] = useState(exportJob ? exportJob.expires_at : null);
    const [exportType, setExportType] = useState('all');
    const [exporting, setExporting] = useState(false);
    const [uploading, setUploading] = useState(false);
    const [importStatus, setImportStatus] = useState(null);
    const [importPolling, setImportPolling] = useState(false);
    const fileInputRef = useRef(null);

    // エクスポートのステータス監視
    useEffect(() => {
        if (status === 'pending' || status === 'processing') {
            const timer = setInterval(() => {
                axios.get(route('export.status'), { params: { type: 'export' } }).then((response) => {
                    const { status, metadata, expires_at } = response.data;
                    if (status) {
                        setStatus(status);
                        setMetadata(metadata);
                        setExpiresAt(expires_at);
                    }
                });
            }, 3000);

            return () => clearInterval(timer);
        }
    }, [status]);

    // エクスポート
    const handleExport = (e) => {
        e.preventDefault();
        setExporting(true);
        router.post(route('export.start'), { type: exportType }, {
            preserveScroll: true,
            onSuccess: () => {
                setStatus('pending');
                setExporting(false);
            },
            onError: () => {
                setExporting(false);
            },
        });
    };

    // ダウンロード
    const handleDownload = () => {
        window.open(route('export.download', { type: 'export' }), '_blank');
    };

    // 削除
    const handleDelete = () => {
        if (!confirm('エクスポートデータを削除しますか？')) return;

        router.delete(route('export.delete', { type: 'export' }), {
            preserveScroll: true,
            onSuccess: () => {
                setStatus('none');
                setMetadata(null);
                setExpiresAt(null);
                setExportType('all');
            },
        });
    };

    // インポート
    const handleImport = (e) => {
        e.preventDefault();
        setUploading(true);
        setImportStatus('processing');

        const formData = new FormData();
        formData.append('file', fileInputRef.current.files[0]);

        router.post(route('import'), formData, {
            preserveScroll: true,
            onSuccess: (page) => {
                setImportPolling(true);
                pollImportStatus();
            },
            onError: () => {
                setImportStatus('error');
                setUploading(false);
            },
            onFinish: () => {
                setUploading(false);
            },
        });
    };

    // インポートステータス監視
    const pollImportStatus = () => {
        const timer = setInterval(() => {
            axios.get(route('export.status', { type: 'import' })).then((response) => {
                const { status, type } = response.data;
                if (type === 'import' && (status === 'completed' || status === 'failed')) {
                    clearInterval(timer);
                    setImportPolling(false);
                    setImportStatus(status);
                }
            });
        }, 3000);
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex justify-between items-center">
                    <h2 className="font-semibold text-xl text-gray-800 leading-tight">
                        データのエクスポート/インポート
                    </h2>
                    <div className="flex items-center gap-2">
                        <a href={route('boxes.index')} className="btn btn-sm btn-outline">
                            一覧へ戻る
                        </a>
                    </div>
                </div>
            }
        >
            <Head title="データのエクスポート/インポート" />

            <div className="py-12">
                <div className="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">

                    {/* エクスポートセクション */}
                    <div className="bg-base-100 shadow-sm sm:rounded-lg">
                        <div className="p-6">
                            <h3 className="text-lg font-medium text-gray-900 mb-4">エクスポート</h3>

                            {status === 'completed' && metadata && (
                                <div className="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg">
                                    <p className="text-sm text-green-800">
                                        エクスポート完了: {metadata.boxes_count} BOX, {metadata.photos_count} 枚の写真をエクスポートしました
                                    </p>
                                    <p className="text-xs text-green-600 mt-1">
                                        有効期限: {new Date(expiresAt).toLocaleString()}
                                    </p>
                                    <div className="mt-3 flex gap-2">
                                        <button onClick={handleDownload} className="btn btn-sm btn-primary">
                                            ダウンロード
                                        </button>
                                        <button onClick={handleDelete} className="btn btn-sm btn-ghost btn-error">
                                            削除
                                        </button>
                                    </div>
                                </div>
                            )}

                            {(status === 'pending' || status === 'processing') && (
                                <div className="mb-4 p-4 bg-amber-50 border border-amber-300 rounded-lg">
                                    <div className="flex items-center gap-3">
                                        <span className="loading loading-spinner loading-md text-amber-600"></span>
                                        <div>
                                            <p className="text-sm font-medium text-amber-900">
                                                エクスポート処理中
                                            </p>
                                            <p className="text-xs text-amber-700 mt-0.5">
                                                完了まで数秒お待ちください。このページは閉しないでください。
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            )}

                            <form onSubmit={handleExport} className="space-y-4">
                                <div>
                                    <label className="label">
                                        <span className="label-text">エクスポート内容</span>
                                    </label>
                                    <div className="flex gap-4">
                                        <label className="label cursor-pointer gap-2">
                                            <input
                                                type="radio"
                                                name="exportType"
                                                value="all"
                                                checked={exportType === 'all'}
                                                onChange={() => setExportType('all')}
                                                className="radio radio-primary"
                                            />
                                            <span className="label-text">BOX名・説明文・写真すべて</span>
                                        </label>
                                        <label className="label cursor-pointer gap-2">
                                            <input
                                                type="radio"
                                                name="exportType"
                                                value="boxes_only"
                                                checked={exportType === 'boxes_only'}
                                                onChange={() => setExportType('boxes_only')}
                                                className="radio radio-primary"
                                            />
                                            <span className="label-text">BOX名・説明文のみ</span>
                                        </label>
                                        <label className="label cursor-pointer gap-2">
                                            <input
                                                type="radio"
                                                name="exportType"
                                                value="photos_only"
                                                checked={exportType === 'photos_only'}
                                                onChange={() => setExportType('photos_only')}
                                                className="radio radio-primary"
                                            />
                                            <span className="label-text">写真のみ</span>
                                        </label>
                                    </div>
                                </div>

                                <button
                                    type="submit"
                                    className="btn btn-primary"
                                    disabled={exporting}
                                >
                                    {exporting ? (
                                        <>
                                            <span className="loading loading-spinner loading-xs"></span>
                                            処理中...
                                        </>
                                    ) : 'エクスポート開始'}
                                </button>
                            </form>
                        </div>
                    </div>

                    {/* インポートセクション */}
                    <div className="bg-base-100 shadow-sm sm:rounded-lg">
                        <div className="p-6">
                            <h3 className="text-lg font-medium text-gray-900 mb-4">インポート</h3>

                            {importStatus === 'processing' || importPolling ? (
                                <div className="mb-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                                    <div className="flex items-center gap-2">
                                        <span className="loading loading-spinner loading-sm"></span>
                                        <span className="text-sm text-blue-800">
                                            インポート処理中...
                                        </span>
                                    </div>
                                </div>
                            ) : null}

                            {importStatus === 'completed' && (
                                <div className="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg">
                                    <p className="text-sm text-green-800">
                                        インポート完了しました。
                                    </p>
                                </div>
                            )}

                            {importStatus === 'error' && (
                                <div className="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg">
                                    <p className="text-sm text-red-800">
                                        エラーが発生しました。
                                    </p>
                                </div>
                            )}

                            <form onSubmit={handleImport} className="space-y-4">
                                <div>
                                    <label className="label">
                                        <span className="label-text">ZIPファイルを選択</span>
                                    </label>
                                    <input
                                        ref={fileInputRef}
                                        type="file"
                                        accept=".zip"
                                        className="file-input file-input-bordered w-full"
                                        disabled={uploading || importPolling}
                                    />
                                    <label className="label">
                                        <span className="label-text-alt text-gray-500">
                                            形式: hayonaos-export-YYYYMMDD_HHmmss.zip
                                        </span>
                                    </label>
                                </div>

                                <button
                                    type="submit"
                                    className="btn btn-primary"
                                    disabled={uploading || importPolling}
                                >
                                    インポート開始
                                </button>
                            </form>
                        </div>
                    </div>

                </div>
            </div>
        </AuthenticatedLayout>
    );
}
