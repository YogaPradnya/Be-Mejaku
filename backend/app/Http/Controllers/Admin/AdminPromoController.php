<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PromoCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminPromoController extends Controller
{
    /**
     * GET /api/admin/promos
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => 'sometimes|string|max:50',
            'is_active' => 'sometimes|boolean',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        $query = PromoCode::orderBy('code');

        if (array_key_exists('is_active', $validated)) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if (!empty($validated['search'])) {
            $query->where('code', 'like', '%' . $validated['search'] . '%');
        }

        $paginator = $query->paginate($validated['per_page'] ?? 15);

        $data = collect($paginator->items())->map(function (PromoCode $promo) {
            $arr = $promo->toArray();
            $arr['is_valid_now'] = $promo->isValid();
            return $arr;
        })->values();

        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    /**
     * GET /api/admin/promos/{id}
     */
    public function show(int $id): JsonResponse
    {
        $promo = PromoCode::find($id);

        if (!$promo) {
            return response()->json(['success' => false, 'message' => 'Promo tidak ditemukan.'], 404);
        }

        $data = $promo->toArray();
        $data['is_valid_now'] = $promo->isValid();

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * Aturan validasi promo (dipakai store & update).
     */
    private function rules(?int $ignoreId = null): array
    {
        return [
            'code' => ['required', 'string', 'max:50', Rule::unique('promo_codes', 'code')->ignore($ignoreId)],
            'type' => 'required|string|in:percentage,fixed',
            'value' => 'required|integer|min:1|max:10000000',
            'min_order' => 'sometimes|integer|min:0',
            'max_discount' => 'sometimes|nullable|integer|min:0',
            'is_active' => 'sometimes|boolean',
            'valid_from' => 'sometimes|nullable|date',
            'valid_until' => 'sometimes|nullable|date|after_or_equal:valid_from',
        ];
    }

    /**
     * POST /api/admin/promos
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->rules());
        $validated['code'] = strtoupper(trim($validated['code']));

        if (($validated['type'] ?? null) === 'percentage' && $validated['value'] > 100) {
            return response()->json([
                'success' => false,
                'message' => 'Nilai persen maksimal 100.',
            ], 422);
        }

        $promo = PromoCode::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Promo berhasil ditambahkan.',
            'data' => $promo,
        ], 201);
    }

    /**
     * PUT /api/admin/promos/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $promo = PromoCode::find($id);

        if (!$promo) {
            return response()->json(['success' => false, 'message' => 'Promo tidak ditemukan.'], 404);
        }

        $rules = $this->rules($promo->id);
        $rules['code'] = ['sometimes', 'string', 'max:50', Rule::unique('promo_codes', 'code')->ignore($promo->id)];
        $rules['type'] = 'sometimes|string|in:percentage,fixed';
        $rules['value'] = 'sometimes|integer|min:1|max:10000000';
        $validated = $request->validate($rules);

        if (isset($validated['code'])) {
            $validated['code'] = strtoupper(trim($validated['code']));
        }

        $type = $validated['type'] ?? $promo->type;
        $value = $validated['value'] ?? $promo->value;
        if ($type === 'percentage' && $value > 100) {
            return response()->json([
                'success' => false,
                'message' => 'Nilai persen maksimal 100.',
            ], 422);
        }

        $promo->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Promo berhasil diperbarui.',
            'data' => $promo->fresh(),
        ]);
    }

    /**
     * DELETE /api/admin/promos/{id}
     * Aman: orders menyimpan snapshot `promo_code` string, bukan FK.
     */
    public function destroy(int $id): JsonResponse
    {
        $promo = PromoCode::find($id);

        if (!$promo) {
            return response()->json(['success' => false, 'message' => 'Promo tidak ditemukan.'], 404);
        }

        $promo->delete();

        return response()->json(['success' => true, 'message' => 'Promo berhasil dihapus.']);
    }
}
