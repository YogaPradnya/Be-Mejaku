<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Dokumentasi API (Swagger UI, file divendor lokal agar bisa dibuka offline).
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
<title>MejaKu API Docs</title>
<link rel="stylesheet" href="/docs/swagger-ui.css">
</head>
<body>
<div id="swagger-ui"></div>
<script src="/docs/swagger-ui-bundle.js"></script>
<script src="/docs/swagger-ui-standalone-preset.js"></script>
<script>
window.onload = function () {
  SwaggerUIBundle({
    url: '/api/docs.yaml',
    dom_id: '#swagger-ui',
    presets: [SwaggerUIBundle.presets.apis, SwaggerUIStandalonePreset],
  });
};
</script>
</body>
</html>
HTML;

        return response($html)->header('Content-Type', 'text/html');
    }

    public function yaml(): \Symfony\Component\HttpFoundation\Response
    {
        $path = base_path('swagger.yaml');

        if (!file_exists($path)) {
            return response('swagger.yaml tidak ditemukan.', 404);
        }

        return response(file_get_contents($path))->header('Content-Type', 'application/yaml');
    }

    public function json(): JsonResponse
    {
        // Ringkas: jumlah path terdokumentasi agar mudah dicek otomatis.
        $content = @file_get_contents(base_path('swagger.yaml'));

        return response()->json([
            'success' => (bool) $content,
            'data' => [
                'spec' => '/api/docs.yaml',
                'paths' => $content ? substr_count($content, "\n  /api/") : 0,
            ],
        ]);
    }
}
