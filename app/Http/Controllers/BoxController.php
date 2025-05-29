<?php declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Locations;
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
        $boxes = auth()->user()->locations()->latest()->get();

        return Inertia::render('Boxes/BoxesList', ['boxes' => $boxes]);
    }
}