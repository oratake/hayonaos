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
        // Userモデルに boxes() リレーションが定義されている前提
        $boxes = auth()->user()->locations()->latest()->get();

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
        // Userモデルに boxes() リレーションが定義され、
        // Boxモデルの $fillable に name と description が設定されている前提
        $request->user()->locations()->create($validated);

        return redirect()->route('boxes.index')->with('success', 'BOXが作成されました。');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Box $box): Response
    {
        // 認可: ログインユーザーがこのBOXを編集する権限があるか確認 (Policy推奨)
        // if (auth()->user()->cannot('update', $box)) {
        //     abort(403);
        // }
        return Inertia::render('Boxes/Edit', [
            'box' => $box,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Box $box): RedirectResponse
    {
        // 認可
        // if (auth()->user()->cannot('update', $box)) {
        //     abort(403);
        // }
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $box->update($validated);

        return redirect()->route('boxes.index')->with('success', 'BOXが更新されました。');
    }
    // TODO: show, destroy メソッドを後ほど追加
}