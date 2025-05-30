<?php declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Box;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
class BoxController extends Controller
{
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

        // 各BOXの最初の写真に公開URLを付与
        $boxes->each(function ($box) {
            $box->first_photo_url_public = $box->photos->first() ? Storage::url($box->photos->first()->file_path) : null;
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
            'new_photos.*' => 'image|mimes:jpeg,png,jpg,gif|max:5120',
            'new_photo_captions' => 'nullable|array',
            'new_photo_captions.*' => 'nullable|string|max:255',
        ]);

        // // ログインユーザーのBoxとして作成
        // $request->user()->boxes()->create($validated);
        $boxData = [
            'name' => $validated['name'],
            'description' => $validated['description'],
            'qr_code_url' => $validated['qr_code_url'],
        ];

        $box = $request->user()->boxes()->create($boxData);

        if ($request->hasFile('new_photos')) {
            foreach ($request->file('new_photos') as $index => $photoFile) {
                if ($photoFile->isValid()) {
                    $path = $photoFile->store('box_photos', 'public');
                    $caption = $request->input("new_photo_captions.{$index}", null); // Get caption by index
                    $box->photos()->create([
                        'file_path' => $path,
                        'caption' => $caption,
                    ]);
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
            $query->orderBy('id'); // Or any other consistent order
        }]);
        
        // Add public URL for photos
        $box->photos->each(function ($photo) {
            $photo->photo_url_public = Storage::url($photo->file_path);
        });

        return Inertia::render('Boxes/Show', ['box' => $box]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Box $box): Response
    {
        // TODO: 認可(未実装)
        // $this->authorize('update', $box);

        $box->load('photos'); // Load all photos

        // Add public URL for photos
        $box->photos->each(function ($photo) {
            $photo->photo_url_public = Storage::url($photo->file_path);
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
            'new_photos.*' => 'image|mimes:jpeg,png,jpg,gif|max:5120',
            'new_photo_captions' => 'nullable|array',
            'new_photo_captions.*' => 'nullable|string|max:255',
            'photos_to_delete' => 'nullable|array',
            'photos_to_delete.*' => 'integer|exists:tbl_box_photos,id', // Ensure IDs exist
            'updated_captions' => 'nullable|array',
            'updated_captions.*' => 'nullable|string|max:255', // Captions for existing photos
        ]);

        // Update Box details
        $boxData = [
            'name' => $validated['name'],
            'description' => $validated['description'],
            'qr_code_url' => $validated['qr_code_url'],
        ];
        $box->update($boxData);
        
        // Delete marked photos
        if (!empty($validated['photos_to_delete'])) {
            foreach ($validated['photos_to_delete'] as $photoIdToDelete) {
                $photo = $box->photos()->find($photoIdToDelete);
                if ($photo) {
                    Storage::disk('public')->delete($photo->file_path);
                    $photo->delete();
                }
            }
        }

        // Update captions of existing photos
        if (!empty($validated['updated_captions'])) {
            foreach ($validated['updated_captions'] as $photoId => $newCaption) {
                // Ensure $photoId is a valid ID from the box's photos
                $photoToUpdate = $box->photos()->find($photoId);
                if ($photoToUpdate) {
                    $photoToUpdate->update(['caption' => $newCaption]);
                }
            }
        }

        // Add new photos
        if ($request->hasFile('new_photos')) {
            foreach ($request->file('new_photos') as $index => $photoFile) {
                if ($photoFile->isValid()) {
                    $path = $photoFile->store('box_photos', 'public');
                    $caption = $request->input("new_photo_captions.{$index}", null);
                    $box->photos()->create([
                        'file_path' => $path,
                        'caption' => $caption,
                    ]);
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

        $box->delete();

        return redirect()->route('boxes.index')->with('success', 'BOXが削除されました。');
    }
}