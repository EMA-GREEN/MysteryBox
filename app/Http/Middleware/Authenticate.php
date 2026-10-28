<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo(Request $request): ?string
    {
        // Check if the request expects a JSON response (API request)
        if ($request->expectsJson()) {
            return null; // Let the unauthenticated method handle it
        }

        // Web requests: Redirect to the login page (or any other page you want)
        return route('login');
    }

    /**
     * Handle an unauthenticated user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  array  $guards
     * @return void
     *
     * @throws \Illuminate\Auth\AuthenticationException
     */
    protected function unauthenticated($request, array $guards)
    {
        // Check if the request is an API request
        if ($request->expectsJson()) {
            // For API requests, return a JSON response
            return abort(response()->json([
                'message' => 'Unauthenticated.'
            ], 401));
        }

        // For web portal requests, redirect to the login page
        return redirect()->route('login');
    }
} 