<?php

namespace App\Http\Controllers;

use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Wedding;
use Illuminate\Http\Request;

class MenuController extends Controller
{
    public function index(Wedding $wedding)
    {
        $menus = $wedding->menus()->with('items')->get();

        return view('menus.index', compact('wedding', 'menus'));
    }

    public function store(Request $request, Wedding $wedding)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255']]);
        $data['sort_order'] = ($wedding->menus()->max('sort_order') ?? 0) + 1;
        $wedding->menus()->create($data);

        return back()->with('success', 'Menu added.');
    }

    public function destroy(Wedding $wedding, Menu $menu)
    {
        abort_unless($menu->wedding_id === $wedding->id, 404);
        $menu->delete();

        return back()->with('success', 'Menu removed.');
    }

    public function storeItem(Request $request, Wedding $wedding, Menu $menu)
    {
        abort_unless($menu->wedding_id === $wedding->id, 404);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'course' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
        ]);
        $data['sort_order'] = ($menu->items()->max('sort_order') ?? 0) + 1;
        $menu->items()->create($data);

        return back()->with('success', 'Item added.');
    }

    public function destroyItem(Wedding $wedding, Menu $menu, MenuItem $item)
    {
        abort_unless($menu->wedding_id === $wedding->id && $item->menu_id === $menu->id, 404);
        $item->delete();

        return back()->with('success', 'Item removed.');
    }
}
