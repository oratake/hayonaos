import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, Link } from '@inertiajs/react';
import BoxForm from '@/Components/BoxForm';

export default function Edit({ auth, box }) {
    const { data, setData, post, processing, errors, reset } = useForm({ // Changed put to post
        name: box.name || '',
        description: box.description || '',
        qr_code_url: box.qr_code_url || '',
        // For new photos
        new_photos: [],
        new_photo_captions: [],
        // For managing existing photos
        existing_photos: box.photos || [], // Pass existing photos from controller
        photos_to_delete: [], // Array of IDs of photos to delete
        updated_captions: {}, // Object to store { photoId: newCaption }
        _method: 'PUT', // Important for file uploads with PUT
    });

    const submit = (e) => {
        e.preventDefault();
        // Use 'post' for multipart/form-data with _method: 'PUT'
        // The 'data' object already contains all necessary fields including new_photos, photos_to_delete etc.
        post(route('boxes.update', box.id), {
            // preserveState: true, // Consider if you want to preserve state on validation errors
        });
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex justify-between items-center">
                    <h2 className="font-semibold text-xl text-gray-800 leading-tight">BOX編集</h2>
                    <Link href={route('boxes.index')} className="btn btn-sm btn-outline">
                        一覧へ戻る
                    </Link>
                </div>
            }
        >
            <Head title="BOX編集" />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-base-100 shadow-sm sm:rounded-lg">
                        <BoxForm
                            data={data}
                            setData={setData}
                            errors={errors}
                            processing={processing}
                            onSubmit={submit}
                            submitButtonText="更新する"
                            existingPhotos={data.existing_photos}
                            onRemoveExistingPhoto={(photoId) => {
                                setData('photos_to_delete', [...data.photos_to_delete, photoId]);
                                setData('existing_photos', data.existing_photos.filter(p => p.id !== photoId)); // Visually remove
                            }}
                            onUpdateExistingPhotoCaption={(photoId, caption) => {
                                setData('updated_captions', { ...data.updated_captions, [photoId]: caption });
                            }}
                            isFocused={false} // 編集時は最初のフィールドに自動フォーカスしない場合
                        />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}