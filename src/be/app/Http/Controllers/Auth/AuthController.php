<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Token-based API authentication (Phase 9). Deliberately the simplest
 * shape that actually closes the P0 finding: one seeded actor type, no
 * registration endpoint (this is a small internal employee-registry tool,
 * not a multi-tenant SaaS -- KISS/YAGNI), Sanctum personal-access tokens
 * issued via the standard, documented Sanctum pattern (Auth::validate()
 * checks credentials without starting a session; a plain-text token is
 * then minted and returned once).
 */
class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();

        if (! Auth::validate($credentials)) {
            Log::warning('Failed login attempt', [
                'email' => $credentials['email'],
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials',
            ], 401);
        }

        $user = User::where('email', $credentials['email'])->first();
        $token = $user->createToken('api')->plainTextToken;

        Log::info('User logged in', [
            'user_id' => $user->id,
            'ip' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $userId = $request->user()?->getAuthIdentifier();

        $request->user()?->currentAccessToken()?->delete();

        Log::info('User logged out', ['user_id' => $userId]);

        return response()->json(['success' => true, 'message' => 'Logged out']);
    }
}
