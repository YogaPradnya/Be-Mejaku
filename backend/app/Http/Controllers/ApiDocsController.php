<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Dokumentasi API interaktif (Scalar, bisa Try It langsung dari browser).
 */
class ApiDocsController extends Controller
{
    public function index(): Response
    {
        $html = <<<'HTML'
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>MejaKu API Docs — Testing Interaktif</title>
<style>
  body { margin: 0; font-family: system-ui, -apple-system, "Segoe UI", sans-serif; }
  .topbar {
    padding: 10px 16px; background: #0f172a; color: #e2e8f0;
    font-size: 13px; line-height: 1.6;
  }
  .topbar b { color: #fff; }
  .topbar code { background: #1e293b; padding: 1px 6px; border-radius: 6px; color: #fcd34d; }
</style>
</head>
<body>
<div class="topbar">
  <b>Cara testing:</b> 1) <code>POST /api/admin/auth/login</code> → salin <code>token</code>
  &nbsp;2) Klik <b>Authorize</b> / masukkan <code>Bearer &lt;token&gt;</code>
  &nbsp;3) Klik <b>Try It / Send</b> di tiap endpoint.
  &nbsp;Order butuh <code>table_code</code> (cth: <code>A12</code>), header <code>X-Idempotency-Key</code> opsional.
</div>
<script id="api-reference" data-url="/api/docs.yaml"></script>
<script src="https://cdn.jsdelivr.net/npm/@scalar/api-reference"></script>
</body>
</html>
HTML;

        return response($html)->header('Content-Type', 'text/html');
    }

    public function yaml(): Response
    {
        $path = base_path('swagger.yaml');

        if (!file_exists($path)) {
            return response('swagger.yaml tidak ditemukan.', 404);
        }

        return response(file_get_contents($path))
            ->header('Content-Type', 'application/yaml; charset=utf-8')
            ->header('Cache-Control', 'no-cache, must-revalidate');
    }

    public function json(): JsonResponse
    {
        $path = base_path('swagger.yaml');

        if (!file_exists($path)) {
            return response()->json(['success' => false, 'message' => 'swagger.yaml tidak ditemukan.'], 404);
        }

        try {
            $spec = \Symfony\Component\Yaml\Yaml::parseFile($path);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['success' => false, 'message' => 'Gagal parse swagger.yaml: '.$e->getMessage()], 500);
        }

        return response()->json($spec);
    }
}
