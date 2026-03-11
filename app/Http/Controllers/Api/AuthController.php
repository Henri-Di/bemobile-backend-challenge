<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

final class AuthController extends Controller
{
    /**
     * Authenticate the user and return a personal access token.
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string', 'min:8'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        $email = mb_strtolower(trim($validated['email']));
        $password = $validated['password'];
        $deviceName = trim((string) ($validated['device_name'] ?? 'api-token'));

        $user = User::query()
            ->where('email', $email)
            ->first();

        if (! $user || ! Hash::check($password, (string) $user->password)) {
            return response()->json([
                'message' => 'The provided credentials are invalid.',
                'errors' => [
                    'email' => ['The provided credentials are invalid.'],
                ],
            ], Response::HTTP_UNAUTHORIZED);
        }

        if (! (bool) $user->is_active) {
            return response()->json([
                'message' => 'User account is inactive.',
            ], Response::HTTP_FORBIDDEN);
        }

        $token = $user->createToken($deviceName)->plainTextToken;

        return response()->json([
            'message' => 'Authenticated successfully.',
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
                'user' => $this->userPayload($user),
            ],
        ], Response::HTTP_OK);
    }

    /**
     * Revoke the current authenticated access token.
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $currentToken = $user->currentAccessToken();

        if ($currentToken !== null) {
            $currentToken->delete();
        }

        return response()->json([
            'message' => 'Logged out successfully.',
        ], Response::HTTP_OK);
    }

    /**
     * Build a standardized user payload for authentication responses.
     *
     * @return array{
     *     id:int,
     *     name:string,
     *     email:string,
     *     role:string,
     *     is_active:bool
     * }
     */
    private function userPayload(User $user): array
    {
        return [
            'id' => (int) $user->id,
            'name' => (string) $user->name,
            'email' => (string) $user->email,
            'role' => is_object($user->role) && property_exists($user->role, 'value')
                ? (string) $user->role->value
                : (string) $user->role,
            'is_active' => (bool) $user->is_active,
        ];
    }
}