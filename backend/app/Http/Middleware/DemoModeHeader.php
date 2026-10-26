<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DemoModeHeader
{
    /**
     * Tambahkan header X-Demo-Mode ke setiap response API
     * agar FE selalu tahu apakah backend dalam mode demo.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->is('api/*')) {
            $demoMode = config('mejaku.demo_mode') ? 'true' : 'false';
            $response->headers->set('X-Demo-Mode', $demoMode);
        }

        return $response;
    }
}
