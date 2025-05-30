import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, usePage } from '@inertiajs/react';
import { QRCodeCanvas } from 'qrcode.react'; // QRCodeCanvasコンポーネントをインポート

export default function Show({ auth, box }) {
    // コントローラーから渡された完全なURLを取得
    const { currentAbsoluteUrl } = usePage().props;

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex justify-between items-center">
                    <h2 className="font-semibold text-xl text-gray-800 leading-tight">
                        BOX詳細: {box.name}
                    </h2>
                    <div className="flex items-center gap-2">
                        <Link
                            href={route('boxes.edit', box.uuid)}
                            className="btn btn-sm btn-info" // DaisyUIのinfoボタンスタイルを適用
                        >
                            編集
                        </Link>
                        <Link href={route('boxes.index')} className="btn btn-sm btn-outline">
                            一覧へ戻る
                        </Link>
                    </div>
                </div>
            }
        >
            <Head title={`BOX詳細: ${box.name}`} />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-base-100 shadow-sm sm:rounded-lg">
                        <div className="p-6 space-y-4">
                            <div>
                                <h3 className="text-lg font-medium text-gray-900">BOX名</h3>
                                <p className="mt-1 text-sm text-gray-600">{box.name}</p>
                            </div>
                            <div>
                                <h3 className="text-lg font-medium text-gray-900">説明</h3>
                                <p className="mt-1 text-sm text-gray-600 whitespace-pre-wrap">{box.description || 'N/A'}</p>
                            </div>
                            <div>
                                <h3 className="text-lg font-medium text-gray-900">BOXのURL</h3>
                                <Link href={currentAbsoluteUrl} className="mt-1 text-sm text-blue-600 hover:text-blue-800 break-all">
                                    {currentAbsoluteUrl}
                                </Link>
                                {/* QRコードの表示 */}
                                <div className="mt-4">
                                    <QRCodeCanvas // SVGからCanvasに変更
                                        value={currentAbsoluteUrl} // QRコードにするURLを完全なURLに変更
                                        size={64} // QRコードのサイズ (ピクセル)
                                        bgColor={"#ffffff"} // 背景色
                                        fgColor={"#000000"} // 前景色
                                        level={"L"} // 誤り訂正レベル (L, M, Q, H)
                                        includeMargin={false} // マージンを含めるか
                                    />
                                </div>
                            </div>
                            <div>
                                <h3 className="text-lg font-medium text-gray-900">作成日時</h3>
                                <p className="mt-1 text-sm text-gray-600">{new Date(box.created_at).toLocaleString()}</p>
                            </div>
                            <div>
                                <h3 className="text-lg font-medium text-gray-900">更新日時</h3>
                                <p className="mt-1 text-sm text-gray-600">{new Date(box.updated_at).toLocaleString()}</p>
                            </div>
                            {box.photos && box.photos.length > 0 && (
                                <div className="mt-6">
                                    <h3 className="text-lg font-medium text-gray-900 mb-2">写真一覧</h3>
                                    <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                                        {box.photos.map((photo) => (
                                            <div key={photo.id} className="border rounded-lg shadow-sm p-2">
                                                <img src={photo.photo_url_public} alt={photo.caption || `写真 ${photo.id}`} className="w-full h-48 object-cover rounded-md mb-2" />
                                                {photo.caption && (
                                                    <p className="text-sm text-gray-600">{photo.caption}</p>
                                                )}
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}