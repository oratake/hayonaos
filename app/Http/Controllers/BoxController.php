<?php declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Box;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        $boxes = auth()->user()->boxes()->latest()->get();

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
        ]);

        // ログインユーザーのBoxとして作成
        $request->user()->boxes()->create($validated);

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
        // $box->load('photos');

        return Inertia::render('Boxes/Show', ['box' => $box]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Box $box): Response
    {
        // TODO: 認可(未実装)
        // $this->authorize('update', $box);

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
        ]);

        $box->update($validated);

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