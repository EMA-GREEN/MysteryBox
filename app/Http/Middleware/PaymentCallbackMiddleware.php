<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class PaymentCallbackMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        // Add any payment callback validation logic here
        // For example, you might want to validate the request signature
        // or check if the request is coming from a trusted source

        return $next($request);
    }
} 