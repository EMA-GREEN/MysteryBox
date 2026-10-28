<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class ApiTokenMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json([
                'status' => 'error',
                'message' => 'Access token required'
            ], 401);
        }

        try {
            // Validate JWT token
            $payload = User::validateJwtToken($token);
            
            if (!$payload) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid or expired access token'
                ], 401);
            }

            // Get user from database
            $user = User::find($payload['user_id']);
            
            if (!$user || $user->msisdn !== $payload['msisdn']) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid token - user not found'
                ], 401);
            }

            // Check if token is still valid in database
            if (!$user->isUserTokenValid()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Access token has been revoked'
                ], 401);
            }

            // Add user to request for use in controllers
            $request->merge(['user' => $user]);

            return $next($request);

        } catch (\Exception $e) {
            Log::error('API token middleware error', [
                'token' => substr($token, 0, 50) . '...',
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Token validation failed'
            ], 500);
        }
    }
} 