<?php

namespace App\Http\Controllers;

use App\Services\YasFreeBoxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use RuntimeException;

class YasFreeBoxController extends Controller
{
    public function __construct(private YasFreeBoxService $service)
    {
    }

    public function status(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'msisdn' => 'required|string|regex:/^[0-9]+$/|min:10|max:15',
            'source' => ['required', 'string', Rule::in($this->service->allowedSources())],
        ]);

        try {
            return response()->json([
                'status' => 'success',
                'data' => $this->service->getStatus($validated['msisdn'], $validated['source']),
            ]);
        } catch (RuntimeException $e) {
            if ($e->getMessage() === 'INVALID_SOURCE') {
                return response()->json([
                    'status' => 'error',
                    'code' => 'INVALID_SOURCE',
                    'message' => 'Invalid or disabled app source',
                ], 422);
            }
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Yas free box status failed: ' . $e->getMessage(), [
                'msisdn' => $validated['msisdn'] ?? null,
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get free box status',
            ], 500);
        }
    }

    public function open(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'msisdn' => 'required|string|regex:/^[0-9]+$/|min:10|max:15',
            'source' => ['required', 'string', Rule::in($this->service->allowedSources())],
            'request_id' => 'nullable|string|max:64',
        ]);

        try {
            $data = $this->service->open(
                $validated['msisdn'],
                $validated['source'],
                $validated['request_id'] ?? null
            );

            return response()->json([
                'status' => 'success',
                'data' => $data,
            ]);
        } catch (RuntimeException $e) {
            if ($e->getMessage() === 'DAILY_LIMIT_REACHED') {
                return response()->json([
                    'status' => 'error',
                    'code' => 'DAILY_LIMIT_REACHED',
                    'message' => 'Free box openings for today are finished',
                ], 429);
            }

            if ($e->getMessage() === 'YAS_FREE_BOX_DISABLED') {
                return response()->json([
                    'status' => 'error',
                    'code' => 'YAS_FREE_BOX_DISABLED',
                    'message' => 'Yas free box is disabled',
                ], 403);
            }

            if ($e->getMessage() === 'INVALID_SOURCE') {
                return response()->json([
                    'status' => 'error',
                    'code' => 'INVALID_SOURCE',
                    'message' => 'Invalid or disabled app source',
                ], 422);
            }

            throw $e;
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Yas free box open failed: ' . $e->getMessage(), [
                'payload' => $request->all(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to open free box',
            ], 500);
        }
    }
}
