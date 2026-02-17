<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectToWww
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment('production') && $request->getHost() === 'podcheck.dev') {
            return redirect()->to(
                'https://www.podcheck.dev' . $request->getRequestUri(),
                301
            );
        }

        return $next($request);
    }
}
