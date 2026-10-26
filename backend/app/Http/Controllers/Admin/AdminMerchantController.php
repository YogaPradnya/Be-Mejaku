<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Merchant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminMerchantController extends Controller
{
    /**
     * GET /api/admin/merchants
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => 'sometimes|string|max:100',
            'is_active' => 'sometimes|boolean',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        $query = Merchant::withCount('menuItems')->orderBy('name');

        if (array_key_exists('is_active', $validated)) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if (!empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%");
            });
        }

        $paginator = $query->paginate($validated['per_page'] ?? 15);

        return response()->json([
            'success' => true,
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    /**
     * GET /api/admin/merchants/{id}
     */
    public function show(int $id): JsonResponse
    {
        $merchant = Merchant::with(['menuItems:id,merchant_id,name,slug,base_price,stock,is_available'])
            ->withCount('menuItems')
            ->find($id);

        if (!$merchant) {
            return response()->json(['success' => false, 'message' => 'Merchant tidak ditemukan.'], 404);
        }

        return response()->json(['success' => true, 'data' => $merchant]);
    }

    /**
     * POST /api/admin/merchants
     * Konsep single-merchant: tolak bila sudah ada 1 merchant.
     */
    public function store(Request $request): JsonResponse
    {
        if (Merchant::count() >= 1) {
            return response()->json([
                'success' => false,
                'message' => 'Konsep 1 merchant: merchant sudah ada, gunakan update merchant yang ada.',
            ], 409);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'description' => 'sometimes|nullable|string',
            'address' => 'sometimes|nullable|string|max:255',
            'image' => 'sometimes|nullable|url|max:500',
            'is_active' => 'sometimes|boolean',
        ]);

        $merchant = Merchant::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Merchant berhasil ditambahkan.',
            'data' => $merchant,
        ], 201);
    }

    /**
     * PUT /api/admin/merchants/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $merchant = Merchant::find($id);

        if (!$merchant) {
            return response()->json(['success' => false, 'message' => 'Merchant tidak ditemukan.'], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:150',
            'description' => 'sometimes|nullable|string',
            'address' => 'sometimes|nullable|string|max:255',
            'image' => 'sometimes|nullable|url|max:500',
            'is_active' => 'sometimes|boolean',
        ]);

        $merchant->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Merchant berhasil diperbarui.',
            'data' => $merchant->fresh(),
        ]);
    }

    /**
     * DELETE /api/admin/merchants/{id}
     * Ditolak bila masih punya menu (mencegah cascade tak sengaja). ?force=1 untuk paksa.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $merchant = Merchant::find($id);

        if (!$merchant) {
            return response()->json(['success' => false, 'message' => 'Merchant tidak ditemukan.'], 404);
        }

        if ($merchant->menuItems()->exists() && !$request->boolean('force')) {
            return response()->json([
                'success' => false,
                'message' => 'Merchant masih memiliki menu. Nonaktifkan atau gunakan ?force=1.',
            ], 409);
        }

        // Single-merchant: jangan hapus merchant terakhir tanpa pengganti.
        if (Merchant::count() <= 1 && !$request->boolean('force')) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak bisa menghapus satu-satunya merchant. Gunakan ?force=1 bila memang ingin reset.',
            ], 409);
        }

        $merchant->delete();

        return response()->json(['success' => true, 'message' => 'Merchant berhasil dihapus.']);
    }
}
