<?php

namespace SlouchCMS\Client;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SlouchCMSMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {

        $key   = env('SLOUCH_CMS_KEY');        
        $token = $request->bearerToken();
        if (!$key || $token != $key) {
            return response()->json('Unauthorized', 401);
        }

        return $next($request);
    }
}
