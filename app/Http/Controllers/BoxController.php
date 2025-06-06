<?php declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Box;
use App\Models\BoxPhoto;
use App\Services\BoxPhotoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class BoxController extends Controller
{
    private BoxPhotoService $boxPhotoService;

    /**
     * Inject the BoxPhotoService via the constructor.
     */
    public function __construct(BoxPhotoService $boxPhotoService)
    {
        $this->boxPhotoService = $boxPhotoService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(): Response
    {
        // ログインしているユーザーに紐づくBoxを取得
        $boxes = auth()->user()->boxes()
            ->with(['photos' => function ($query) {
                $query->orderBy('id')->limit(1); // 各BOXの最初の写真のみを取得 (ID順)
            }])
            ->orderByDesc('updated_at')
            ->get();

        $boxes->each(function ($box) {
            // サムネイルパスがあればサムネイルURLを、なければオリジナル画像URLを、どちらもなければnull
            $first_photo = $box->photos->first();
            $box->first_photo_url_public = null;
            if ($first_photo && $first_photo->thumbnail_file_path) {
                $box->first_photo_url_public = Storage::url($first_photo->thumbnail_file_path);
            } elseif ($first_photo && $first_photo->file_path) {
                $box->first_photo_url_public = Storage::url($first_photo->file_path);
            }
        });

        return Inertia::render('Boxes/BoxesList', ['boxes' => $boxes]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        return Inertia::render('Boxes/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'qr_code_url' => 'nullable|url|max:255',
            'new_photos' => 'nullable|array',
            'new_photos.*' => 'image|mimes:jpeg,png,jpg,gif|max:10240', // 10MB per photo max
            'new_photo_captions' => 'nullable|array',
            'new_photo_captions.*' => 'nullable|string|max:255',
        ]);

        $boxData = [
            'name' => $validated['name'],
            'description' => $validated['description'],
            'qr_code_url' => $validated['qr_code_url'],
        ];

        $box = $request->user()->boxes()->create($boxData);

        if ($request->hasFile('new_photos')) {
            foreach ($request->file('new_photos') as $index => $photoFile) {
                if ($photoFile->isValid()) {
                    $caption = $request->input("new_photo_captions.{$index}", null);

                    $this->boxPhotoService->createPhotoForBox($box, $photoFile, $caption);
                }
            }
        }

        return redirect()->route('boxes.index')->with('success', 'BOXが作成されました。');
    }

    /**
     * Display the specified resource.
     */
    public function show(Box $box): Response
    {
        // TODO: 認可(未実装)
        // $this->authorize('view', $box);

        // 必要に応じて、関連データも Eager Loading できます
        $box->load(['photos' => function ($query) {
            $query->orderBy('id');
        }]);

        $box->photos->each(function ($photo) {
            $photo->original_photo_url_public = Storage::url($photo->file_path); // オリジナル画像URL
            // サムネイルパスがあればサムネイルURLを、なければオリジナル画像URLを (フォールバック)
            if ($photo->thumbnail_file_path) {
                $photo->display_photo_url_public = Storage::url($photo->thumbnail_file_path);
            } else {
                $photo->display_photo_url_public = Storage::url($photo->file_path);
            }
        });

        return Inertia::render('Boxes/Show', [
            'box' => $box,
            'currentAbsoluteUrl' => url()->current(),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Box $box): Response
    {
        // TODO: 認可(未実装)
        // $this->authorize('update', $box);

        $box->load('photos');
        $box->photos->each(function ($photo) {
            $photo->photo_url_public = Storage::url($photo->file_path); // オリジナル画像のURL
            // 編集画面ではサムネイルとオリジナル両方表示する可能性を考慮 (ここではオリジナルを維持)
            // または、サムネイルパスを渡してフロントで制御
            $photo->thumbnail_url_public = $photo->thumbnail_file_path ? Storage::url($photo->thumbnail_file_path) : Storage::url($photo->file_path);
        });
        return Inertia::render('Boxes/Edit', [
            'box' => $box,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Box $box): RedirectResponse
    {
        // TODO: 認可(未実装)
        // $this->authorize('update', $box);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'qr_code_url' => 'nullable|url|max:255',
            'new_photos' => 'nullable|array',
            'new_photos.*' => 'image|mimes:jpeg,png,jpg,gif|max:10240', // 10MB par photo max
            'new_photo_captions' => 'nullable|array',
            'new_photo_captions.*' => 'nullable|string|max:255',
            'photos_to_delete' => 'nullable|array',
            'photos_to_delete.*' => 'integer|exists:tbl_box_photos,id',
            'updated_captions' => 'nullable|array',
            'updated_captions.*' => 'nullable|string|max:255',
        ]);

        $boxData = [
            'name' => $validated['name'],
            'description' => $validated['description'],
            'qr_code_url' => $validated['qr_code_url'],
        ];
        $box->update($boxData);

        if (!empty($validated['photos_to_delete'])) {
            foreach ($validated['photos_to_delete'] as $photoIdToDelete) {
                $photo = $box->photos()->find($photoIdToDelete);
            
                $this->boxPhotoService->deletePhoto($photo);
            }
        }

        if (!empty($validated['updated_captions'])) {
            foreach ($validated['updated_captions'] as $photoId => $newCaption) {
                $photoToUpdate = $box->photos()->find($photoId);
                if ($photoToUpdate) {
                    $photoToUpdate->update(['caption' => $newCaption]);
                }
            }
        }

        if ($request->hasFile('new_photos')) {
            foreach ($request->file('new_photos') as $index => $photoFile) {
                if ($photoFile->isValid()) {
                    $caption = $request->input("new_photo_captions.{$index}", null);

                    $this->boxPhotoService->createPhotoForBox($box, $photoFile, $caption);
                }
            }
        }

        return redirect()->route('boxes.index')->with('success', 'BOXが更新されました。');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Box $box): RedirectResponse
    {
        // TODO: 認可(未実装)
        // $this->authorize('delete', $box);

        // 関連する写真を先に削除 (ストレージからも)
        foreach ($box->photos as $photo) {
            $this->boxPhotoService->deletePhoto($photo);
        }
        $box->delete();

        return redirect()->route('boxes.index')->with('success', 'BOXが削除されました。');
    }
}