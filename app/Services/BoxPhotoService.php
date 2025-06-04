<?php declare(strict_types=1);

namespace App\Services;

use App\Models\Box;
use App\Models\BoxPhoto;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;

class BoxPhotoService
{
    private ImageManager $imageManager;

    /**
     * Constructor to inject dependencies.
     *
     * @param ImageManager $imageManager
     */
    public function __construct(ImageManager $imageManager)
    {
        $this->imageManager = $imageManager;
    }

    /**
     * Processes an uploaded photo, generates a thumbnail, and attaches it to a Box.
     *
     * @param Box $box The box to attach the photo to.
     * @param UploadedFile $photoFile The uploaded photo file.
     * @param string|null $caption The caption for the photo.
     * @return Photo The created Photo model instance.
     */
    public function createPhotoForBox(Box $box, UploadedFile $photoFile, ?string $caption = null): BoxPhoto
    {
        // Store the original image
        $originalPath = $photoFile->store('box_photos', 'public');

        // Generate thumbnail
        // Use Storage::disk('public')->path() to get the absolute path for Intervention Image
        $thumbnailImage = $this->imageManager->read(Storage::disk('public')->path($originalPath));
        $thumbnailImage->cover(150, 150); // 150x150にリサイズしてクロップ

        $thumbnailFilename = 'thumb_' . basename($originalPath);
        $thumbnailDirectory = 'box_photo_thumbnails';
        Storage::disk('public')->makeDirectory($thumbnailDirectory); // ディレクトリがなければ作成
        $thumbnailPath = $thumbnailDirectory . '/' . $thumbnailFilename;

        // Save the thumbnail
        // Use Storage::disk('public')->path() to get the absolute path for Intervention Image save
        $thumbnailImage->save(Storage::disk('public')->path($thumbnailPath));

        // Create the Photo model and associate it with the Box
        $photo = $box->photos()->create([
            'file_path' => $originalPath,
            'thumbnail_file_path' => $thumbnailPath,
            'caption' => $caption,
        ]);

        return $photo;
    }

    /**
     * Deletes a photo and its thumbnail from storage and the database.
     *
     * @param BoxPhoto $photo The photo model instance to delete.
     * @return bool True if deletion was successful, false otherwise.
     */
    public function deletePhoto(BoxPhoto $photo): bool
    {
        // Delete from storage
        Storage::disk('public')->delete($photo->file_path);
        if ($photo->thumbnail_file_path) {
            Storage::disk('public')->delete($photo->thumbnail_file_path);
        }

        // Delete from database
        return $photo->delete();
    }
}