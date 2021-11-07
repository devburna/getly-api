<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EmailVerified
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        if (!$request->user()->email_verified_at) {
            return response()->json([
                'status' => false,
                'message' => trans('auth.email-unverified'),
            ], 403);
        } else {
            return $next($request);
        }
    }
}
