import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

export default function Show({ auth, box }) {
    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex justify-between items-center">
                    <h2 className="font-semibold text-xl text-gray-800 leading-tight">
                        BOX詳細: {box.name}
                    </h2>
                    <Link href={route('boxes.index')} className="btn btn-sm btn-outline">
                        一覧へ戻る
                    </Link>
                </div>
            }
        >
            <Head title={`BOX詳細: ${box.name}`} />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-base-100 shadow-sm sm:rounded-lg">
                        <div className="p-6 space-y-4">
                            <div>
                                <h3 className="text-lg font-medium text-gray-900">ID</h3>
                                <p className="mt-1 text-sm text-gray-600">{box.id}</p>
                            </div>
                            <div>
                                <h3 className="text-lg font-medium text-gray-900">UUID</h3>
                                <p className="mt-1 text-sm text-gray-600">{box.uuid}</p>
                            </div>
                            <div>
                                <h3 className="text-lg font-medium text-gray-900">BOX名</h3>
                                <p className="mt-1 text-sm text-gray-600">{box.name}</p>
                            </div>
                            <div>
                                <h3 className="text-lg font-medium text-gray-900">説明</h3>
                                <p className="mt-1 text-sm text-gray-600 whitespace-pre-wrap">{box.description || 'N/A'}</p>
                            </div>
                            <div>
                                <h3 className="text-lg font-medium text-gray-900">QRコードURL</h3>
                                <p className="mt-1 text-sm text-gray-600">{box.qr_code_url || 'N/A'}</p>
                            </div>
                            <div>
                                <h3 className="text-lg font-medium text-gray-900">作成日時</h3>
                                <p className="mt-1 text-sm text-gray-600">{new Date(box.created_at).toLocaleString()}</p>
                            </div>
                            <div>
                                <h3 className="text-lg font-medium text-gray-900">更新日時</h3>
                                <p className="mt-1 text-sm text-gray-600">{new Date(box.updated_at).toLocaleString()}</p>
                            </div>
                            {/* TODO: 写真一覧などを表示する場合はここに追加 */}
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}