import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, usePage } from '@inertiajs/react';
import { QRCodeCanvas } from 'qrcode.react';
import Modal from '@/Components/Modal';
import { useState } from 'react';

export default function Show({ auth, box }) {
    // コントローラーから渡された完全なURLを取得
    const { currentAbsoluteUrl } = usePage().props;
    const [showImageModal, setShowImageModal] = useState(false);
    const [selectedImageUrl, setSelectedImageUrl] = useState('');
    const [selectedImageCaption, setSelectedImageCaption] = useState('');


    const openImageModal = (imageUrl, caption) => {
        setSelectedImageUrl(imageUrl);
        setSelectedImageCaption(caption || '');
        setShowImageModal(true);
    };

    const closeImageModal = () => {
        setShowImageModal(false);
        setSelectedImageUrl('');
        setSelectedImageCaption('');
    };


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
                            className="btn btn-sm btn-info"
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
                                <h3 className="text-lg font-medium text-gray-900">QRコード</h3>
                                <div className="mt-4">
                                    <QRCodeCanvas
                                        value={box.qr_code_url || currentAbsoluteUrl}
                                        size={64}
                                        bgColor={"#ffffff"}
                                        fgColor={"#000000"}
                                        level={"L"} // 誤り訂正レベル (L, M, Q, H)
                                        includeMargin={false}
                                    />
                                </div>
                                <Link href={box.qr_code_url || currentAbsoluteUrl} className="mt-1 text-sm text-blue-600 hover:text-blue-800 break-all">
                                    {box.qr_code_url || currentAbsoluteUrl}
                                </Link>
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
                                    <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
                                        {box.photos.map((photo) => (
                                            <div key={photo.id} className="border rounded-lg shadow-sm p-2">
                                                <img
                                                    src={photo.display_photo_url_public}
                                                    alt={photo.caption || `写真 ${photo.id}`}
                                                    className="w-full h-40 object-cover rounded-md mb-2 cursor-pointer hover:opacity-75 transition-opacity"
                                                    onClick={() => openImageModal(photo.original_photo_url_public, photo.caption)}
                                                />
                                                {photo.caption && (
                                                    <p className="text-xs text-gray-600 truncate" title={photo.caption}>{photo.caption}</p>
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

            {/* 画像表示用モーダル */}
            <Modal show={showImageModal} onClose={closeImageModal} maxWidth="2xl" closeable={true}>
                <div className="p-4 bg-base-100 rounded-lg">
                    <img src={selectedImageUrl} alt={selectedImageCaption || "拡大画像"} className="max-w-full max-h-[80vh] mx-auto rounded-md" />
                    {selectedImageCaption && (
                        <p className="text-center mt-2 text-sm text-gray-700">{selectedImageCaption}</p>
                    )}
                    <div className="mt-4 text-right">
                        <button onClick={closeImageModal} className="btn btn-sm btn-ghost">閉じる</button>
                    </div>
                </div>
            </Modal>
        </AuthenticatedLayout>
    );
}