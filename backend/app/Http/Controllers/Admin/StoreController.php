<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class StoreController extends Controller
{
    private const KEY = 'store.is_open';

    /**
     * GET /api/admin/store/status
     */
    public function show(): JsonResponse
    {
        $isOpen = Cache::get(self::KEY, true);

        return response()->json([
            'success' => true,
            'data' => [
                'is_open' => (bool) $isOpen,
                'status' => $isOpen ? 'OPEN' : 'CLOSED',
            ],
        ]);
    }

    /**
     * PUT /api/admin/store/status {is_open: bool}
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate(['is_open' => 'required|boolean']);
        Cache::forever(self::KEY, $validated['is_open']);

        return response()->json([
            'success' => true,
            'message' => $validated['is_open'] ? 'Toko dibuka.' : 'Toko ditutup.',
            'data' => [
                'is_open' => (bool) $validated['is_open'],
                'status' => $validated['is_open'] ? 'OPEN' : 'CLOSED',
            ],
        ]);
    }
}
