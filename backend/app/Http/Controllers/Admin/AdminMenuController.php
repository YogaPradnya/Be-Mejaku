<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Merchant;
use App\Models\MenuItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class AdminMenuController extends Controller
{
    /**
     * GET /api/admin/menu
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => 'sometimes|string|max:100',
            'is_available' => 'sometimes|boolean',
            'merchant_id' => 'sometimes|integer|exists:merchants,id',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        $query = MenuItem::with(['merchant:id,name', 'portions', 'addons'])
            ->withCount(['portions', 'addons'])
            ->orderBy('name');

        if (array_key_exists('is_available', $validated)) {
            $query->where('is_available', $request->boolean('is_available'));
        }

        if (!empty($validated['merchant_id'])) {
            $query->where('merchant_id', $validated['merchant_id']);
        }

        if (!empty($validated['search'])) {
            $query->where('name', 'like', '%' . $validated['search'] . '%');
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
     * GET /api/admin/menu/{id}
     */
    public function show(int $id): JsonResponse
    {
        $item = MenuItem::with(['merchant', 'portions', 'addons'])->find($id);

        if (!$item) {
            return response()->json(['success' => false, 'message' => 'Menu tidak ditemukan.'], 404);
        }

        return response()->json(['success' => true, 'data' => $item]);
    }

    private function menuRules(): array
    {
        return [
            // Single-merchant: merchant_id opsional, bila dikirim akan diabaikan
            // dan dipaksa ke merchant satu-satunya.
            'merchant_id' => 'sometimes|integer|exists:merchants,id',
            'name' => 'required|string|max:150',
            'description' => 'sometimes|nullable|string',
            'base_price' => 'required|integer|min:0|max:100000000',
            'stock' => 'sometimes|integer|min:0|max:1000000',
            'has_spice_option' => 'sometimes|boolean',
            'image' => 'sometimes|nullable|url|max:500',
            'is_available' => 'sometimes|boolean',
            'portions' => 'sometimes|array|max:20',
            'portions.*.name' => 'required_with:portions|string|max:50',
            'portions.*.surcharge' => 'sometimes|integer|min:0|max:10000000',
            'addons' => 'sometimes|array|max:30',
            'addons.*.name' => 'required_with:addons|string|max:100',
            'addons.*.price' => 'sometimes|integer|min:0|max:10000000',
        ];
    }

    /**
     * Konsep 1 merchant: selalu pakai merchant pertama.
     * Mengembalikan Merchant atau null bila belum ada merchant.
     */
    private function singleMerchant(): ?Merchant
    {
        return Merchant::orderBy('id')->first();
    }

    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'menu';
        $slug = $base;
        $i = 2;
        while (MenuItem::where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }

    /**
     * POST /api/admin/menu
     * merchant_id dari request diabaikan — selalu memakai merchant satu-satunya.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->menuRules());

        $merchant = $this->singleMerchant();
        if (!$merchant) {
            return response()->json(['success' => false, 'message' => 'Belum ada merchant. Buat merchant terlebih dahulu.'], 422);
        }

        $item = DB::transaction(function () use ($validated, $merchant) {
            $item = MenuItem::create([
                'merchant_id' => $merchant->id,
                'name' => $validated['name'],
                'slug' => $this->uniqueSlug($validated['name']),
                'description' => $validated['description'] ?? null,
                'base_price' => $validated['base_price'],
                'stock' => $validated['stock'] ?? 0,
                'has_spice_option' => $validated['has_spice_option'] ?? true,
                'image' => $validated['image'] ?? null,
                'is_available' => $validated['is_available'] ?? true,
            ]);

            foreach ($validated['portions'] ?? [] as $i => $p) {
                $item->portions()->create([
                    'name' => $p['name'],
                    'surcharge' => $p['surcharge'] ?? 0,
                    'sort_order' => $i + 1,
                ]);
            }

            foreach ($validated['addons'] ?? [] as $i => $a) {
                $item->addons()->create([
                    'name' => $a['name'],
                    'price' => $a['price'] ?? 0,
                    'sort_order' => $i + 1,
                ]);
            }

            return $item->load(['merchant', 'portions', 'addons']);
        });

        return response()->json([
            'success' => true,
            'message' => 'Menu berhasil ditambahkan.',
            'data' => $item,
        ], 201);
    }

    /**
     * PUT /api/admin/menu/{id}
     * portions/addons bila dikirim akan diganti penuh (replace).
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $item = MenuItem::find($id);

        if (!$item) {
            return response()->json(['success' => false, 'message' => 'Menu tidak ditemukan.'], 404);
        }

        $rules = $this->menuRules();
        $rules['merchant_id'] = 'sometimes|integer|exists:merchants,id';
        $rules['name'] = 'sometimes|string|max:150';
        $rules['base_price'] = 'sometimes|integer|min:0|max:100000000';
        $validated = $request->validate($rules);

        // Single-merchant: menu tidak bisa pindah merchant, abaikan input merchant_id.
        unset($validated['merchant_id']);

        $item = DB::transaction(function () use ($item, $validated) {
            $update = collect($validated)->except(['portions', 'addons'])->toArray();
            if (isset($validated['name']) && $validated['name'] !== $item->name) {
                $update['slug'] = $this->uniqueSlug($validated['name'], $item->id);
            }
            $item->update($update);

            if (array_key_exists('portions', $validated)) {
                $item->portions()->delete();
                foreach ($validated['portions'] ?? [] as $i => $p) {
                    $item->portions()->create([
                        'name' => $p['name'],
                        'surcharge' => $p['surcharge'] ?? 0,
                        'sort_order' => $i + 1,
                    ]);
                }
            }

            if (array_key_exists('addons', $validated)) {
                $item->addons()->delete();
                foreach ($validated['addons'] ?? [] as $i => $a) {
                    $item->addons()->create([
                        'name' => $a['name'],
                        'price' => $a['price'] ?? 0,
                        'sort_order' => $i + 1,
                    ]);
                }
            }

            return $item->fresh(['merchant', 'portions', 'addons']);
        });

        return response()->json([
            'success' => true,
            'message' => 'Menu berhasil diperbarui.',
            'data' => $item,
        ]);
    }

    /**
     * POST /api/admin/menu/upload-image — upload foto menu ke Cloudinary via backend.
     * Body: multipart/form-data dengan field `image`.
     * Return: { success, data: { url, public_id } }
     */
    public function uploadImage(Request $request): JsonResponse
    {
        $request->validate([
            'image' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        $cloud = (string) config('cloudinary.cloud_name');
        $apiKey = (string) config('cloudinary.api_key');
        $apiSecret = (string) config('cloudinary.api_secret');
        $folder = (string) (config('cloudinary.folder') ?: 'mejaku/menus');

        if ($cloud === '' || $apiKey === '' || $apiSecret === '') {
            return response()->json(['success' => false, 'message' => 'Konfigurasi Cloudinary belum lengkap.'], 503);
        }

        $file = $request->file('image');
        $timestamp = time();
        $transformation = 'w_800,q_auto,f_auto';
        $toSign = "folder={$folder}&timestamp={$timestamp}&transformation={$transformation}{$apiSecret}";

        try {
            $response = Http::timeout(30)->attach(
                'file',
                file_get_contents($file->getRealPath()),
                $file->getClientOriginalName()
            )->post("https://api.cloudinary.com/v1_1/{$cloud}/image/upload", [
                'api_key' => $apiKey,
                'timestamp' => $timestamp,
                'folder' => $folder,
                'transformation' => $transformation,
                'signature' => sha1($toSign),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Gagal mengunggah ke Cloudinary: ' . $e->getMessage()], 502);
        }

        if (!$response->successful()) {
            return response()->json(['success' => false, 'message' => 'Cloudinary menolak upload.', 'error' => $response->json()], 502);
        }

        $json = $response->json();

        return response()->json([
            'success' => true,
            'message' => 'Foto berhasil diunggah.',
            'data' => [
                'url' => $json['secure_url'] ?? null,
                'public_id' => $json['public_id'] ?? null,
            ],
        ]);
    }

    /**
     * PATCH /api/admin/menu/{id}/toggle
     */
    public function toggle(int $id): JsonResponse
    {
        $item = MenuItem::find($id);

        if (!$item) {
            return response()->json(['success' => false, 'message' => 'Menu tidak ditemukan.'], 404);
        }

        $item->update(['is_available' => !$item->is_available]);

        return response()->json([
            'success' => true,
            'message' => $item->is_available ? 'Menu tersedia.' : 'Menu disembunyikan.',
            'data' => $item->fresh(),
        ]);
    }

    /**
     * DELETE /api/admin/menu/{id} — soft: set is_available=false agar histori order aman.
     * ?force=1 untuk hapus permanen (cascade portions/addons).
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $item = MenuItem::find($id);

        if (!$item) {
            return response()->json(['success' => false, 'message' => 'Menu tidak ditemukan.'], 404);
        }

        if ($request->boolean('force')) {
            $item->delete();

            return response()->json(['success' => true, 'message' => 'Menu dihapus permanen.']);
        }

        $item->update(['is_available' => false]);

        return response()->json([
            'success' => true,
            'message' => 'Menu dinonaktifkan (soft delete).',
            'data' => $item->fresh(),
        ]);
    }
}
