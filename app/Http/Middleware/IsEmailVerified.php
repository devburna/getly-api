<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class IsEmailVerified
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        if (!$request->user()->email_address_verified_at) {
            abort(401, 'Email has not been verified, please check your mail for email verification link.');
        }

        return $next($request);
    }
}
