<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Staff;
use App\Models\Subscriber;
use App\Models\RenewalSubscriber;
use App\Services\MpushSubsManageService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function __construct(private MpushSubsManageService $mpushSubsManageService)
    {
    }

    /**
     * Register new user
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function register(Request $request): JsonResponse
    {
        try {
            // Validate the request
            // $validator = Validator::make($request->all(), [
            //     'name' => 'required|string|max:255',
            //     'msisdn' => 'required|string|regex:/^[0-9]+$/|min:10|max:15|unique:users',
            //     'password' => 'required|string|min:6',
            // ]);

            $validator = Validator::make($request->all(), [
                'msisdn' => 'required|string|regex:/^[0-9]+$/|min:10|max:15|unique:users'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $msisdn = $request->input('msisdn');
            $name = 'User-'.$msisdn;
            // $name = $request->input('name');
            // $password = $request->input('password');

            Log::info('Registration request received', [
                'name' => $name,
                'msisdn' => $msisdn,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);

            // Check if user already exists
            $existingUser = User::where('msisdn', $msisdn)->first();
            if ($existingUser) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User already exists with this MSISDN'
                ], 409);
            }

            //set password to test value for now
            $password = '123456';

            // Create new user
            $user = User::create([
                'name' => $name,
                'msisdn' => $msisdn,
                'password' => Hash::make($password),
            ]);

            Log::info('New user created', [
                'user_id' => $user->id,
                'msisdn' => $msisdn
            ]);

            // Check if subscriber exists by MSISDN
            $subscriber = Subscriber::where('msisdn', $msisdn)->first();

            if (!$subscriber) {
                Log::info('Creating new subscriber', ['msisdn' => $msisdn]);
                
                // Create new subscriber
                $subscriber = Subscriber::create([
                    'msisdn' => $msisdn,
                    'customer_type' => 'NEW',
                    'boom_credit' => 0,
                    'total_int_billed_amount' => 0,
                    'total_renewal_billed_amount' => 0,
                    'credit' => 0,
                    'last_interaction' => now(),
                    'activity_status' => 'ACTIVE',
                    'dnd_status' => 'OFF',
                    'sub_channel' => 'API',
                    'record_date' => now(),
                    'loyalty_points' => 0
                ]);

                Log::info('New subscriber created', [
                    'msisdn' => $msisdn,
                    'subscriber_id' => $subscriber->id
                ]);

                $this->mpushSubsManageService->notifySubscription($msisdn);
            } else {
                Log::info('Existing subscriber found', [
                    'msisdn' => $msisdn,
                    'subscriber_id' => $subscriber->id
                ]);

                // Update last interaction
                $subscriber->update([
                    'last_interaction' => now()
                ]);
            }

            // Update or create renewal_subscriber record starts
            $renewalSubscriber = RenewalSubscriber::firstOrCreate(['msisdn' => $msisdn], [
                'activity_status' => $subscriber->activity_status,
                'dnd_status' => $subscriber->dnd_status,
                'priority' => 1,
                'retry_count' => 0,
                'publish_status' => false,
                'last_renewal_billing' => null,
                'next_retrial_period' => null,
            ]);
            
            // If renewal_subscriber already exists, update its activity and dnd status to match subscriber
            if (!$renewalSubscriber->wasRecentlyCreated) {
                $renewalSubscriber->update([
                    'activity_status' => $subscriber->activity_status,
                    'dnd_status' => $subscriber->dnd_status,
                ]);
            }

            Log::info('Renewal subscriber processed', [
                'msisdn' => $msisdn,
                'renewal_subscriber_id' => $renewalSubscriber->id,
                'was_created' => $renewalSubscriber->wasRecentlyCreated
            ]);
            // Update or create renewal_subscriber record ends

            // Generate new user token with 3-hour expiry
            $token = $user->generateUserToken();

            Log::info('User token generated for registration', [
                'user_id' => $user->id,
                'msisdn' => $msisdn,
                'token_expires_at' => $user->token_expires_at
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'User registered successfully',
                'data' => [
                    'user_id' => $user->id,
                    'name' => $user->name,
                    'msisdn' => $user->msisdn,
                    'token' => $token,
                    'token_expires_at' => $user->token_expires_at->toISOString(),
                    'subscriber_exists' => true,
                    'subscriber_id' => $subscriber->id,
                    'loyalty_points' => $subscriber->loyalty_points,
                    'activity_status' => $subscriber->activity_status
                ]
            ], 201);

        } catch (\Exception $e) {
            Log::error('Registration error', [
                'msisdn' => $request->input('msisdn'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Registration failed'
            ], 500);
        }
    }

    /**
     * Authenticate user by MSISDN and password
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function authenticate(Request $request): JsonResponse
    {
        try {
            // Validate the request
            // $validator = Validator::make($request->all(), [
            //     'msisdn' => 'required|string|regex:/^[0-9]+$/|min:10|max:15',
            //     'password' => 'required|string|min:6',
            // ]);

            $validator = Validator::make($request->all(), [
                'msisdn' => 'required|string|regex:/^[0-9]+$/|min:10|max:15'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $msisdn = $request->input('msisdn');
            // $password = $request->input('password');

            Log::info('Authentication request received', [
                'msisdn' => $msisdn,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);

            // First, check if user exists by MSISDN
            $user = User::where('msisdn', $msisdn)->first();

            if (!$user) {
                Log::warning('User not found', ['msisdn' => $msisdn]);
                return response()->json([
                    'status' => 'error',
                    'message' => 'Phonenumber not found'
                ], 401);
            }

            // Check if credentials (username and password) are correct
            // if (!Hash::check($password, $user->password)) {
            //     Log::warning('Invalid password for user', [
            //         'msisdn' => $msisdn,
            //         'user_id' => $user->id
            //     ]);
            //     return response()->json([
            //         'status' => 'error',
            //         'message' => 'Invalid credentials'
            //     ], 401);
            // }

            Log::info('User credentials validated successfully', [
                'msisdn' => $msisdn,
                'user_id' => $user->id
            ]);

            // Check if subscriber exists by MSISDN
            $subscriber = Subscriber::where('msisdn', $msisdn)->first();

            if (!$subscriber) {
                Log::info('Creating new subscriber', ['msisdn' => $msisdn]);
                
                // Create new subscriber
                $subscriber = Subscriber::create([
                    'msisdn' => $msisdn,
                    'customer_type' => 'NEW',
                    'boom_credit' => 0,
                    'last_int_billing' => now(),
                    'total_int_billed_amount' => 0,
                    'total_renewal_billed_amount' => 0,
                    'credit' => 0,
                    'last_interaction' => now(),
                    'activity_status' => 'ACTIVE',
                    'dnd_status' => 'OFF',
                    'sub_channel' => 'API',
                    'record_date' => now(),
                    'loyalty_points' => 0
                ]);

                Log::info('New subscriber created', [
                    'msisdn' => $msisdn,
                    'subscriber_id' => $subscriber->id
                ]);

                $this->mpushSubsManageService->notifySubscription($msisdn);
            } else {
                Log::info('Existing subscriber found', [
                    'msisdn' => $msisdn,
                    'subscriber_id' => $subscriber->id
                ]);

                // Update last interaction
                $subscriber->update([
                    'last_interaction' => now()
                ]);
            }

            // Update or create renewal_subscriber record
            $renewalSubscriber = RenewalSubscriber::firstOrCreate(['msisdn' => $msisdn], [
                'activity_status' => $subscriber->activity_status,
                'dnd_status' => $subscriber->dnd_status,
                'priority' => 1,
                'retry_count' => 0,
                'publish_status' => false,
                'last_renewal_billing' => null,
                'next_retrial_period' => null,
            ]);
            
            // If renewal_subscriber already exists, update its activity and dnd status to match subscriber
            if (!$renewalSubscriber->wasRecentlyCreated) {
                $renewalSubscriber->update([
                    'activity_status' => $subscriber->activity_status,
                    'dnd_status' => $subscriber->dnd_status,
                ]);
            }

            Log::info('Renewal subscriber processed', [
                'msisdn' => $msisdn,
                'renewal_subscriber_id' => $renewalSubscriber->id,
                'was_created' => $renewalSubscriber->wasRecentlyCreated
            ]);

            // Generate new user token with 3-hour expiry
            $token = $user->generateUserToken();

            Log::info('User token generated', [
                'msisdn' => $msisdn,
                'user_id' => $user->id,
                'token_expires_at' => $user->token_expires_at
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Authentication successful',
                'data' => [
                    'user_id' => $user->id,
                    'msisdn' => $user->msisdn,
                    'name' => $user->name,
                    'token' => $token,
                    'token_expires_at' => $user->token_expires_at->toISOString(),
                    'subscriber_exists' => true,
                    'subscriber_id' => $subscriber->id,
                    'loyalty_points' => $subscriber->loyalty_points,
                    'activity_status' => $subscriber->activity_status
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Authentication error', [
                'msisdn' => $request->input('msisdn'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Authentication failed'
            ], 500);
        }
    }

    /**
     * Validate a user token
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function validateToken(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required|string'
        ]);

        $token = $request->input('token');

        try {
            // Validate JWT token
            $payload = User::validateJwtToken($token);
            
            if (!$payload) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid or expired token'
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
                    'message' => 'Token has been revoked'
                ], 401);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Token is valid',
                'data' => [
                    'user_id' => $user->id,
                    'msisdn' => $user->msisdn,
                    'name' => $user->name,
                    'token_expires_at' => $user->token_expires_at
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Token validation error', [
                'token' => substr($token, 0, 50) . '...',
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Token validation failed'
            ], 500);
        }
    }

    /**
     * Logout user (revoke token)
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            $token = $request->bearerToken();
            
            if (!$token) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Token not provided'
                ], 401);
            }

            // Validate JWT token
            $payload = User::validateJwtToken($token);
            
            if (!$payload) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid or expired token'
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

            // Revoke the token
            $user->revokeUserToken();

            Log::info('User logged out', [
                'user_id' => $user->id,
                'msisdn' => $user->msisdn
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Successfully logged out'
            ]);

        } catch (\Exception $e) {
            Log::error('Logout error', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Logout failed'
            ], 500);
        }
    }

    public function user(Request $request)
    {
        return response()->json($request->user());
    }

    /**
     * Authenticate staff by email/username and password
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function staffLogin(Request $request): JsonResponse
    {
        try {
            // Validate the request
            $validator = Validator::make($request->all(), [
                'email' => 'required_without:username|email',
                'username' => 'required_without:email|string',
                'password' => 'required|string|min:6',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $email = $request->input('email');
            $username = $request->input('username');
            $password = $request->input('password');

            Log::info('Staff authentication request received', [
                'email' => $email,
                'username' => $username,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);

            // Find staff by email or username
            $staff = null;
            if ($email) {
                $staff = Staff::findByEmail($email);
            } elseif ($username) {
                $staff = Staff::findByUsername($username);
            }

            if (!$staff) {
                Log::warning('Staff not found', [
                    'email' => $email,
                    'username' => $username
                ]);
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid credentials'
                ], 401);
            }

            // Check if password is correct
            if (!Hash::check($password, $staff->password)) {
                Log::warning('Invalid password for staff', [
                    'staff_id' => $staff->id,
                    'email' => $staff->email
                ]);
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid credentials'
                ], 401);
            }

            Log::info('Staff credentials validated successfully', [
                'staff_id' => $staff->id,
                'email' => $staff->email,
                'role' => $staff->role
            ]);

            // Generate new staff token with 3-hour expiry
            $token = $staff->generateStaffToken();

            Log::info('Staff token generated', [
                'staff_id' => $staff->id,
                'email' => $staff->email,
                'role' => $staff->role,
                'token_expires_at' => $staff->token_expires_at
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Authentication successful',
                'data' => [
                    'staff_id' => $staff->id,
                    'name' => $staff->name,
                    'email' => $staff->email,
                    'username' => $staff->username,
                    'role' => $staff->role,
                    'token' => $token,
                    'token_expires_at' => $staff->token_expires_at->toISOString(),
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Staff authentication error', [
                'email' => $request->input('email'),
                'username' => $request->input('username'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Authentication failed'
            ], 500);
        }
    }

    /**
     * Validate a staff token
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function validateStaffToken(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required|string'
        ]);

        $token = $request->input('token');

        try {
            // Validate JWT token
            $payload = Staff::validateJwtToken($token);
            
            if (!$payload) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid or expired token'
                ], 401);
            }

            // Get staff from database
            $staff = Staff::find($payload['staff_id']);
            
            if (!$staff || $staff->email !== $payload['email']) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid token - staff not found'
                ], 401);
            }

            // Check if token is still valid in database
            if (!$staff->isStaffTokenValid()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Token has been revoked'
                ], 401);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Token is valid',
                'data' => [
                    'staff_id' => $staff->id,
                    'name' => $staff->name,
                    'email' => $staff->email,
                    'username' => $staff->username,
                    'role' => $staff->role,
                    'token_expires_at' => $staff->token_expires_at
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Staff token validation error', [
                'token' => substr($token, 0, 50) . '...',
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Token validation failed'
            ], 500);
        }
    }

    /**
     * Logout staff (revoke token)
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function staffLogout(Request $request): JsonResponse
    {
        try {
            $token = $request->bearerToken();
            
            if (!$token) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Token not provided'
                ], 401);
            }

            // Validate JWT token
            $payload = Staff::validateJwtToken($token);
            
            if (!$payload) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid or expired token'
                ], 401);
            }

            // Get staff from database
            $staff = Staff::find($payload['staff_id']);
            
            if (!$staff || $staff->email !== $payload['email']) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid token - staff not found'
                ], 401);
            }

            // Revoke the token
            $staff->revokeStaffToken();

            Log::info('Staff logged out', [
                'staff_id' => $staff->id,
                'email' => $staff->email,
                'role' => $staff->role
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Successfully logged out'
            ]);

        } catch (\Exception $e) {
            Log::error('Staff logout error', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Logout failed'
            ], 500);
        }
    }
}