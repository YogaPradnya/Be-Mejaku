<?php

namespace App\Http\Controllers;

use App\Http\Requests\ValidatePromoRequest;
use App\Services\PromoService;
use Illuminate\Http\JsonResponse;

class PromoController extends Controller
{
    protected PromoService $promoService;

    public function __construct(PromoService $promoService)
    {
        $this->promoService = $promoService;
    }

    /**
     * GET /api/promos
     * Daftar promo aktif dari database untuk chip promo checkout.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->promoService->activePromos(),
        ]);
    }

    /**
     * POST /api/promo/validate
     * Validasi kode promo.
     */
    public function validate(ValidatePromoRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $result = $this->promoService->validateAndCalculate(
            $validated['code'],
            $validated['subtotal']
        );

        if (!$result['valid']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
                'valid' => false,
            ]);
        }

        return response()->json([
            'success' => true,
            'valid' => true,
            'data' => [
                'code' => $result['promo']['code'],
                'type' => $result['promo']['type'],
                'value' => $result['promo']['value'],
                'discount' => $result['discount'],
                'description' => $result['promo']['description'],
            ],
        ]);
    }
}
