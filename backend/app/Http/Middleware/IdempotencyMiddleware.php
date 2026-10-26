<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IdempotencyMiddleware
{
    /**
     * Tangani request dengan idempotency key dari header.
     *
     * Jika client mengirim header X-Idempotency-Key, middleware ini
     * akan memasukkannya ke dalam request body sehingga controller
     * dapat menggunakannya untuk mencegah pembuatan order ganda.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $idempotencyKey = $request->header('X-Idempotency-Key');

        if ($idempotencyKey && !$request->has('idempotency_key')) {
            $request->merge(['idempotency_key' => $idempotencyKey]);
        }

        $response = $next($request);

        // Tambahkan header ke response agar FE tahu key yang digunakan
        if ($idempotencyKey) {
            $response->headers->set('X-Idempotency-Key', $idempotencyKey);
        }

        return $response;
    }
}
