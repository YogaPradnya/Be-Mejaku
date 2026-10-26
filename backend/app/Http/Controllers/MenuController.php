<?php

namespace App\Http\Controllers;

use App\Models\MenuItem;
use App\Models\Merchant;
use App\Models\Order;
use App\Models\SpiceLevel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MenuController extends Controller
{
    /**
     * GET /api/menu
     * Daftar semua menu (untuk landing page).
     */
    public function index(): JsonResponse
    {
        $items = MenuItem::with(['merchant:id,name', 'portions', 'addons'])
            ->where('is_available', true)
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'merchant' => [
                        'id' => $item->merchant->id,
                        'name' => $item->merchant->name,
                    ],
                    'name' => $item->name,
                    'slug' => $item->slug,
                    'description' => $item->description,
                    'base_price' => $item->base_price,
                    'rating' => (float) $item->rating,
                    'rating_count' => $item->rating_count,
                    'image' => $item->image,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $items,
            'demo_mode' => config('mejaku.demo_mode'),
        ]);
    }

    /**
     * GET /api/menu/{slug}
     * Detail menu lengkap dengan porsi, addon, dan spice levels.
     */
    public function show(string $slug): JsonResponse
    {
        $item = MenuItem::with(['merchant', 'portions', 'addons'])
            ->where('slug', $slug)
            ->first();

        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Menu tidak ditemukan.',
            ], 404);
        }

        $spiceLevels = SpiceLevel::orderBy('sort_order')->get();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $item->id,
                'merchant' => [
                    'id' => $item->merchant->id,
                    'name' => $item->merchant->name,
                    'description' => $item->merchant->description,
                    'address' => $item->merchant->address,
                    'image' => $item->merchant->image,
                ],
                'name' => $item->name,
                'slug' => $item->slug,
                'description' => $item->description,
                'base_price' => $item->base_price,
                'has_spice_option' => (bool) $item->has_spice_option,
                'rating' => (float) $item->rating,
                'rating_count' => $item->rating_count,
                'image' => $item->image,
                'portions' => $item->portions->map(fn($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'surcharge' => $p->surcharge,
                ]),
                'addons' => $item->addons->map(fn($a) => [
                    'id' => $a->id,
                    'name' => $a->name,
                    'price' => $a->price,
                ]),
                'spice_levels' => $spiceLevels->map(fn($s) => [
                    'id' => $s->id,
                    'name' => $s->name,
                    'level' => $s->level,
                ]),
                'recent_reviews' => $item->ratings()->limit(5)->get()->map(fn($r) => [
                    'id' => $r->id,
                    'customer_name' => $r->customer_name,
                    'rating' => $r->rating,
                    'review' => $r->review,
                    'created_at' => $r->created_at?->toIso8601String(),
                ]),
            ],
            'demo_mode' => config('mejaku.demo_mode'),
        ]);
    }

    /**
     * POST /api/menu/{id}/rate
     * Kirim ulasan dan rating untuk menu.
     */
    public function rate(Request $request, string|int $id): JsonResponse
    {
        $item = is_numeric($id)
            ? MenuItem::find($id)
            : MenuItem::where('slug', $id)->first();

        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Menu tidak ditemukan.',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'rating' => 'required|integer|min:1|max:5',
            'review' => 'nullable|string|max:500',
            'customer_name' => 'nullable|string|max:100',
            'order_number' => 'nullable|string|exists:orders,order_number',
            'order_id' => 'nullable|integer|exists:orders,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Data rating tidak valid.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $orderId = $request->input('order_id');
        if (!$orderId && $request->filled('order_number')) {
            $orderId = Order::where('order_number', $request->input('order_number'))->value('id');
        }

        // Bila rating dikaitkan ke order, pastikan order tersebut benar-benar
        // berisi menu ini dan sudah lunas — cegah rating spam/abal-abal.
        if ($orderId) {
            $order = Order::with('items')->find($orderId);
            $containsMenu = $order && $order->items->contains(fn ($it) => (int) $it->menu_item_id === (int) $item->id);
            if (!$containsMenu || ($order && !$order->isPaid())) {
                return response()->json([
                    'success' => false,
                    'message' => 'Rating hanya bisa diberikan untuk menu pada pesanan yang sudah lunas.',
                ], 422);
            }
        }

        $rating = $item->ratings()->create([
            'order_id' => $orderId,
            'customer_name' => $request->input('customer_name') ?: 'Pelanggan',
            'rating' => (int) $request->input('rating'),
            'review' => $request->input('review'),
        ]);

        $item->refreshRatingStats();

        app(\App\Services\NoSqlSyncService::class)->recordReview($item, [
            'order_number' => $request->input('order_number'),
            'customer_name' => $rating->customer_name,
            'rating' => $rating->rating,
            'review' => $rating->review,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Rating berhasil dikirim.',
            'data' => [
                'id' => $rating->id,
                'menu_item_id' => $item->id,
                'customer_name' => $rating->customer_name,
                'rating' => $rating->rating,
                'review' => $rating->review,
                'average_rating' => (float) $item->rating,
                'rating_count' => $item->rating_count,
                'created_at' => $rating->created_at?->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * GET /api/menu/{id}/reviews
     * Mengambil daftar review untuk menu.
     */
    public function reviews(Request $request, string|int $id): JsonResponse
    {
        $item = is_numeric($id)
            ? MenuItem::find($id)
            : MenuItem::where('slug', $id)->first();

        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Menu tidak ditemukan.',
            ], 404);
        }

        $perPage = (int) $request->query('per_page', 15);
        $reviews = $item->ratings()
            ->latest()
            ->paginate(max(1, min(50, $perPage)));

        return response()->json([
            'success' => true,
            'data' => [
                'menu' => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'slug' => $item->slug,
                    'average_rating' => (float) $item->rating,
                    'rating_count' => $item->rating_count,
                ],
                'reviews' => $reviews->map(fn($r) => [
                    'id' => $r->id,
                    'customer_name' => $r->customer_name,
                    'rating' => $r->rating,
                    'review' => $r->review,
                    'created_at' => $r->created_at?->toIso8601String(),
                ]),
                'pagination' => [
                    'current_page' => $reviews->currentPage(),
                    'last_page' => $reviews->lastPage(),
                    'total' => $reviews->total(),
                    'per_page' => $reviews->perPage(),
                ],
            ],
        ]);
    }

    /**
     * GET /api/merchants/{id}
     * Detail merchant.
     */
    public function merchant(int $id): JsonResponse
    {
        $merchant = Merchant::with('menuItems:id,merchant_id,name,slug,base_price,image,rating')
            ->find($id);

        if (!$merchant) {
            return response()->json([
                'success' => false,
                'message' => 'Merchant tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $merchant,
        ]);
    }

    /**
     * GET /api/spice-levels
     * Daftar semua tingkat pedas.
     */
    public function spiceLevels(): JsonResponse
    {
        $levels = SpiceLevel::orderBy('sort_order')->get();

        return response()->json([
            'success' => true,
            'data' => $levels,
        ]);
    }
}

