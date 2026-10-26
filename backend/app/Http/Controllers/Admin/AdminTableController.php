<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Table;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminTableController extends Controller
{
    /**
     * GET /api/admin/tables
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => 'sometimes|string|max:50',
            'is_active' => 'sometimes|boolean',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        $query = Table::withCount(['orders as active_orders_count' => function ($q) {
            $q->whereIn('preparation_status', ['received', 'preparing', 'ready']);
        }])->orderBy('code');

        if (array_key_exists('is_active', $validated)) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if (!empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('floor', 'like', "%{$search}%");
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
            'demo_mode' => config('mejaku.demo_mode'),
        ]);
    }

    /**
     * GET /api/admin/tables/{id}
     */
    public function show(int $id): JsonResponse
    {
        $table = Table::withCount(['orders as active_orders_count' => function ($q) {
            $q->whereIn('preparation_status', ['received', 'preparing', 'ready']);
        }])->find($id);

        if (!$table) {
            return response()->json(['success' => false, 'message' => 'Meja tidak ditemukan.'], 404);
        }

        return response()->json(['success' => true, 'data' => $table]);
    }

    /**
     * POST /api/admin/tables
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:20|unique:tables,code',
            'floor' => 'required|string|max:50',
            'is_active' => 'sometimes|boolean',
        ]);

        $validated['code'] = strtoupper(trim($validated['code']));

        $table = Table::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Meja berhasil ditambahkan.',
            'data' => $table,
        ], 201);
    }

    /**
     * PUT /api/admin/tables/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $table = Table::find($id);

        if (!$table) {
            return response()->json(['success' => false, 'message' => 'Meja tidak ditemukan.'], 404);
        }

        $validated = $request->validate([
            'code' => ['sometimes', 'string', 'max:20', Rule::unique('tables', 'code')->ignore($table->id)],
            'floor' => 'sometimes|string|max:50',
            'is_active' => 'sometimes|boolean',
        ]);

        if (isset($validated['code'])) {
            $validated['code'] = strtoupper(trim($validated['code']));
        }

        $table->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Meja berhasil diperbarui.',
            'data' => $table->fresh(),
        ]);
    }

    /**
     * PATCH /api/admin/tables/{id}/toggle
     */
    public function toggle(int $id): JsonResponse
    {
        $table = Table::find($id);

        if (!$table) {
            return response()->json(['success' => false, 'message' => 'Meja tidak ditemukan.'], 404);
        }

        $table->update(['is_active' => !$table->is_active]);

        return response()->json([
            'success' => true,
            'message' => $table->is_active ? 'Meja diaktifkan.' : 'Meja dinonaktifkan.',
            'data' => $table->fresh(),
        ]);
    }

    /**
     * DELETE /api/admin/tables/{id}
     * Ditolak bila masih ada order aktif (FK restrict + cek eksplisit).
     */
    public function destroy(int $id): JsonResponse
    {
        $table = Table::find($id);

        if (!$table) {
            return response()->json(['success' => false, 'message' => 'Meja tidak ditemukan.'], 404);
        }

        $hasActive = $table->orders()
            ->whereIn('preparation_status', ['received', 'preparing', 'ready'])
            ->exists();

        if ($hasActive) {
            return response()->json([
                'success' => false,
                'message' => 'Meja tidak dapat dihapus karena masih ada pesanan aktif.',
            ], 409);
        }

        $table->delete();

        return response()->json(['success' => true, 'message' => 'Meja berhasil dihapus.']);
    }
}
