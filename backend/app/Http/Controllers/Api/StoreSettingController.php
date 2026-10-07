<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StoreSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StoreSettingController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json(['data' => $this->current()]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'store_name' => ['required', 'string', 'max:150'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'receipt_footer' => ['nullable', 'string', 'max:255'],
        ]);

        $setting = $this->current();
        $setting->update($data);

        return response()->json([
            'message' => 'Pengaturan toko berhasil disimpan.',
            'data' => $setting,
        ]);
    }

    private function current(): StoreSetting
    {
        return StoreSetting::first() ?? StoreSetting::create(['store_name' => 'Toko Kasirku']);
    }
}