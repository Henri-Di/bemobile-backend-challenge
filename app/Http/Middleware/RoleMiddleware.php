<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RoleMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string ...$roles): mixed
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $userRole = is_object($user->role) && property_exists($user->role, 'value')
            ? (string) $user->role->value
            : (string) $user->role;

        $allowedRoles = array_map(
            static fn (string $role): string => mb_strtoupper(trim($role)),
            $roles
        );

        if (! in_array(mb_strtoupper($userRole), $allowedRoles, true)) {
            return response()->json([
                'message' => 'Forbidden.',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}