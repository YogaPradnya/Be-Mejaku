<?php

namespace App\Http\Controllers;

use App\Models\Table;
use Illuminate\Http\JsonResponse;

class TableController extends Controller
{
    /**
     * GET /api/tables
     * Daftar meja aktif untuk aplikasi pelanggan (QR scanner).
     */
    public function index(): JsonResponse
    {
        $tables = Table::where('is_active', true)
            ->orderBy('code')
            ->get()
            ->map(fn (Table $table) => [
                'id' => $table->id,
                'code' => $table->code,
                'floor' => $table->floor,
            ]);

        return response()->json([
            'success' => true,
            'data' => $tables,
        ]);
    }

    /**
     * GET /api/tables/{code}
     * Validasi dan ambil info meja dari kode QR.
     */
    public function show(string $code): JsonResponse
    {
        $table = Table::where('code', strtoupper(trim($code)))
            ->where('is_active', true)
            ->first();

        if (!$table) {
            return response()->json([
                'success' => false,
                'message' => 'Meja tidak ditemukan atau tidak aktif.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $table->id,
                'code' => $table->code,
                'floor' => $table->floor,
            ],
        ]);
    }
}
