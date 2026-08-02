<?php

namespace App\Http\Controllers;

use App\Models\Wedding;
use Illuminate\Http\Request;

class StorageController extends Controller
{
    /**
     * Show storage management for a wedding
     */
    public function show(Wedding $wedding)
    {
        $this->authorize('view', $wedding);

        $storagePlan = $wedding->storagePlan();
        if (!$storagePlan) {
            $storagePlan = $wedding->storagePlan()->create([
                'total_bytes' => 2147483648, // 2GB default
                'used_bytes' => 0,
                'plan_type' => 'free',
            ]);
        }

        $percentUsed = $storagePlan->total_bytes > 0 
            ? round(($storagePlan->used_bytes / $storagePlan->total_bytes) * 100, 2)
            : 0;

        return view('storage.show', compact('wedding', 'storagePlan', 'percentUsed'));
    }

    /**
     * Update storage plan
     */
    public function update(Request $request, Wedding $wedding)
    {
        $this->authorize('update', $wedding);

        $data = $request->validate([
            'plan_type' => ['required', 'in:free,5gb,10gb,20gb,50gb'],
        ]);

        $planLimits = [
            'free' => 2147483648, // 2GB
            '5gb' => 5368709120, // 5GB
            '10gb' => 10737418240, // 10GB
            '20gb' => 21474836480, // 20GB
            '50gb' => 53687091200, // 50GB
        ];

        $storagePlan = $wedding->storagePlan();
        if (!$storagePlan) {
            $storagePlan = $wedding->storagePlan()->create($data);
        }

        $storagePlan->update([
            'plan_type' => $data['plan_type'],
            'total_bytes' => $planLimits[$data['plan_type']],
        ]);

        return back()->with('success', 'Storage plan updated successfully.');
    }
}
