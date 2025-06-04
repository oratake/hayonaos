import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import TextareaInput from '@/Components/TextareaInput';

export default function BoxForm({
    data,
    setData,
    errors,
    processing,
    onSubmit,
    submitButtonText = "保存する",
    isFocused = true,
    // For editing existing photos
    existingPhotos = [], // Default to empty array if not provided
    onRemoveExistingPhoto = () => {}, // Default to no-op
    onUpdateExistingPhotoCaption = () => {}, // Default to no-op
}) {
    const [newPhotoPreviews, setNewPhotoPreviews] = useState([]);

    useEffect(() => {
        if (data.new_photos && data.new_photos.length > 0) {
            const previews = [];
            data.new_photos.forEach(file => {
                if (file instanceof File) { // Ensure it's a file object
                    previews.push(URL.createObjectURL(file));
                }
            });
            setNewPhotoPreviews(previews);

            // Clean up object URLs on component unmount or when new_photos change
            return () => {
                previews.forEach(url => URL.revokeObjectURL(url));
            };
        } else {
            setNewPhotoPreviews([]);
        }
    }, [data.new_photos]);

    const handleFileChange = (e) => {
        const files = Array.from(e.target.files);
        setData(prevData => ({
            ...prevData,
            new_photos: [...(prevData.new_photos || []), ...files],
            new_photo_captions: [
                ...(prevData.new_photo_captions || []),
                ...Array(files.length).fill('')
            ]
        }));
        e.target.value = null; // Reset file input to allow selecting the same file again
    };

    const handleRemoveNewPhoto = (indexToRemove) => {
        setData(prevData => ({
            ...prevData,
            new_photos: prevData.new_photos.filter((_, index) => index !== indexToRemove),
            new_photo_captions: prevData.new_photo_captions.filter((_, index) => index !== indexToRemove),
        }));
    };

    const handleNewPhotoCaptionChange = (index, value) => {
        setData(prevData => {
            const updatedCaptions = [...prevData.new_photo_captions];
            updatedCaptions[index] = value;
            return {
                ...prevData,
                new_photo_captions: updatedCaptions,
            };
        });
    };

    const handleExistingPhotoCaptionChange = (photoId, value) => {
        // Update local state for immediate UI feedback if needed,
        // but the main logic is handled by onUpdateExistingPhotoCaption in Edit.jsx
        const updatedExistingPhotos = data.existing_photos.map(p =>
            p.id === photoId ? { ...p, caption: value } : p
        );
        setData('existing_photos', updatedExistingPhotos); // Update local form data
        onUpdateExistingPhotoCaption(photoId, value); // Propagate to parent
    };

    return (
        <form onSubmit={onSubmit} className="p-6 space-y-6">
            <div>
                <InputLabel htmlFor="name" value="BOX名" />
                <TextInput
                    id="name"
                    name="name"
                    value={data.name}
                    className="mt-1 block w-full"
                    autoComplete="name"
                    isFocused={isFocused}
                    onChange={(e) => setData('name', e.target.value)}
                    required
                />
                <InputError message={errors.name} className="mt-2" />
            </div>

            <div>
                <InputLabel htmlFor="description" value="説明" />
                <TextareaInput
                    id="description"
                    name="description"
                    value={data.description}
                    className="mt-1 block w-full"
                    onChange={(e) => setData('description', e.target.value)}
                />
                <InputError message={errors.description} className="mt-2" />
            </div>

            <div>
                <InputLabel htmlFor="qr_code_url" value="QRコードURL" />
                <TextInput
                    id="qr_code_url"
                    name="qr_code_url"
                    type="url"
                    value={data.qr_code_url || ''}
                    className="mt-1 block w-full"
                    onChange={(e) => setData('qr_code_url', e.target.value)}
                />
                <InputError message={errors.qr_code_url} className="mt-2" />
            </div>

            <div>
                <InputLabel htmlFor="new_photos" value="BOXの写真 (複数選択可)" />
                <input
                    type="file"
                    id="new_photos"
                    multiple // Allow multiple file selection
                    className="file-input file-input-bordered file-input-primary w-full mt-1"
                    onChange={handleFileChange}
                    accept="image/jpeg,image/png,image/gif" // Specify acceptable file types
                />
                <InputError message={errors.new_photos} className="mt-2" />
                {/* Individual file errors (e.g., new_photos.0) */}
                {Object.keys(errors)
                    .filter(key => key.startsWith('new_photos.'))
                    .map(key => (
                        <InputError key={key} message={errors[key]} className="mt-1" />
                ))}
            </div>

            {/* Preview for new photos */}
            {newPhotoPreviews.length > 0 && (
                <div className="mt-4 space-y-4">
                    <h3 className="text-lg font-medium">新しい写真のプレビュー</h3>
                    {newPhotoPreviews.map((previewUrl, index) => (
                        <div key={index} className="p-4 border rounded-lg shadow-sm">
                            <img src={previewUrl} alt={`新規写真プレビュー ${index + 1}`} className="w-40 h-40 object-cover rounded-md mb-2" />
                            <TextInput
                                type="text"
                                placeholder="キャプション (任意)"
                                value={data.new_photo_captions[index] || ''}
                                onChange={(e) => handleNewPhotoCaptionChange(index, e.target.value)}
                                className="input input-bordered w-full mb-2"
                            />
                            <button type="button" onClick={() => handleRemoveNewPhoto(index)} className="btn btn-sm btn-error btn-outline">この画像を削除</button>
                            <InputError message={errors[`new_photo_captions.${index}`]} className="mt-1" />
                        </div>
                    ))}
                </div>
            )}

            {/* Display existing photos for editing */}
            {existingPhotos && existingPhotos.length > 0 && (
                <div className="mt-6 space-y-4">
                    <h3 className="text-lg font-medium">登録済みの写真</h3>
                    {existingPhotos.map((photo) => (
                        <div key={photo.id} className="p-4 border rounded-lg shadow-sm bg-base-200">
                            <img
                                // photo.photo_url_public から photo.thumbnail_url_public に変更
                                src={photo.thumbnail_url_public || photo.photo_url_public} // サムネイルがなければオリジナル
                                alt={photo.caption || `既存写真 ${photo.id}`}
                                className="w-40 h-40 object-cover rounded-md mb-2"
                            />
                            <TextInput
                                type="text"
                                placeholder="キャプション (任意)"
                                value={data.updated_captions && data.updated_captions[photo.id] !== undefined ? data.updated_captions[photo.id] : (photo.caption || '')}
                                onChange={(e) => handleExistingPhotoCaptionChange(photo.id, e.target.value)}
                                className="input input-bordered w-full mb-2"
                            />
                            <button
                                type="button"
                                onClick={() => onRemoveExistingPhoto(photo.id)}
                                className="btn btn-sm btn-warning btn-outline"
                            >
                                この写真を削除リストに追加
                            </button>
                        </div>
                    ))}
                </div>
            )}

            <div className="flex items-center gap-4">
                <PrimaryButton disabled={processing}>{submitButtonText}</PrimaryButton>
                <Link href={route('boxes.index')} className="btn btn-ghost">
                    キャンセル
                </Link>
            </div>
        </form>
    );
}