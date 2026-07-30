<?php

namespace App\Http\Controllers;

use App\Models\BudgetCategory;
use App\Models\Wedding;
use Illuminate\Http\Request;

class BudgetController extends Controller
{
    public function index(Wedding $wedding)
    {
        $categories = $wedding->budgetCategories()->orderBy('name')->get();

        return view('budget.index', compact('wedding', 'categories'));
    }

    public function updateTotal(Request $request, Wedding $wedding)
    {
        $data = $request->validate(['total_budget' => ['required', 'numeric', 'min:0']]);
        $wedding->update($data);

        return back()->with('success', 'Total budget updated.');
    }

    public function store(Request $request, Wedding $wedding)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:10'],
            'budgeted' => ['nullable', 'numeric', 'min:0'],
            'spent' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);
        $wedding->budgetCategories()->create($data);

        return back()->with('success', 'Category added.');
    }

    public function update(Request $request, Wedding $wedding, BudgetCategory $category)
    {
        abort_unless($category->wedding_id === $wedding->id, 404);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:10'],
            'budgeted' => ['nullable', 'numeric', 'min:0'],
            'spent' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);
        $category->update($data);

        return back()->with('success', 'Category updated.');
    }

    public function destroy(Wedding $wedding, BudgetCategory $category)
    {
        abort_unless($category->wedding_id === $wedding->id, 404);
        $category->delete();

        return back()->with('success', 'Category removed.');
    }
}
