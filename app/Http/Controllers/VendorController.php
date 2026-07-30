<?php

namespace App\Http\Controllers;

use App\Models\Vendor;
use App\Models\Wedding;
use Illuminate\Http\Request;

class VendorController extends Controller
{
    public function index(Request $request, Wedding $wedding)
    {
        $query = $wedding->vendors();

        if ($search = $request->get('q')) {
            $query->where('name', 'like', "%{$search}%");
        }
        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        $vendors = $query->orderBy('category')->orderBy('name')->get();

        return view('vendors.index', compact('wedding', 'vendors'));
    }

    public function store(Request $request, Wedding $wedding)
    {
        $wedding->vendors()->create($this->validated($request));

        return back()->with('success', 'Vendor added.');
    }

    public function update(Request $request, Wedding $wedding, Vendor $vendor)
    {
        abort_unless($vendor->wedding_id === $wedding->id, 404);
        $vendor->update($this->validated($request));

        return back()->with('success', 'Vendor updated.');
    }

    public function destroy(Wedding $wedding, Vendor $vendor)
    {
        abort_unless($vendor->wedding_id === $wedding->id, 404);
        $vendor->delete();

        return back()->with('success', 'Vendor removed.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'category' => ['required', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'website' => ['nullable', 'string', 'max:255'],
            'price_estimate' => ['nullable', 'numeric', 'min:0'],
            'amount_paid' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', 'in:researching,contacted,meeting,quote,rejected,booked'],
            'rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'deposit_paid' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
        ]);
    }
}
