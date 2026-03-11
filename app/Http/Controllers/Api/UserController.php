<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Requests\UserIndexRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

final class UserController extends Controller
{
    private const DEFAULT_PER_PAGE = 15;
    private const MIN_PER_PAGE = 1;
    private const MAX_PER_PAGE = 100;

    /**
     * List users using optional filters.
     */
    public function index(UserIndexRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();

        $perPage = $this->normalizePerPage($filters['per_page'] ?? self::DEFAULT_PER_PAGE);
        $search = $this->normalizeNullableString($filters['search'] ?? null);
        $role = $this->normalizeRole($filters['role'] ?? null);
        $isActive = array_key_exists('is_active', $filters) ? $filters['is_active'] : null;

        $users = User::query()
            ->when(
                $search !== null,
                function (Builder $query) use ($search): Builder {
                    return $query->where(function (Builder $builder) use ($search): void {
                        $builder
                            ->where('name', 'like', '%' . $search . '%')
                            ->orWhere('email', 'like', '%' . $search . '%');
                    });
                }
            )
            ->when(
                $role !== null,
                fn (Builder $query): Builder => $query->where('role', $role)
            )
            ->when(
                $isActive !== null,
                fn (Builder $query): Builder => $query->where('is_active', (bool) $isActive)
            )
            ->orderByDesc('id')
            ->paginate($perPage)
            ->appends($request->query());

        return UserResource::collection($users);
    }

    /**
     * Display a single user resource.
     */
    public function show(User $user): UserResource
    {
        return new UserResource($user);
    }

    /**
     * Store a newly created user.
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::query()->create([
            'name' => $this->normalizePersonName($validated['name']),
            'email' => $this->sanitizeEmail($validated['email']),
            'password' => $validated['password'],
            'role' => $this->normalizeRole($validated['role']),
            'is_active' => $this->normalizeBoolean($validated['is_active'] ?? true),
        ]);

        return $this->resourceResponse(
            resource: new UserResource($user),
            status: Response::HTTP_CREATED,
            meta: [
                'message' => 'User created successfully.',
            ]
        );
    }

    /**
     * Update an existing user.
     */
    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $validated = $request->validated();
        $payload = $this->buildUpdatePayload($validated);

        if ($payload !== []) {
            $user->fill($payload);

            if ($user->isDirty()) {
                $user->save();
            }
        }

        return $this->resourceResponse(
            resource: new UserResource($user->refresh()),
            status: Response::HTTP_OK,
            meta: [
                'message' => 'User updated successfully.',
            ]
        );
    }

    /**
     * Delete an existing user.
     */
    public function destroy(User $user): JsonResponse
    {
        $user->delete();

        return $this->jsonResponse(
            data: [
                'message' => 'User deleted successfully.',
            ],
            status: Response::HTTP_OK
        );
    }

    /**
     * Build a normalized update payload.
     *
     * @param array<string, mixed> $validated
     * @return array<string, mixed>
     */
    private function buildUpdatePayload(array $validated): array
    {
        $payload = [];

        if (array_key_exists('name', $validated)) {
            $normalizedName = $this->normalizePersonName($validated['name']);

            if ($normalizedName !== null) {
                $payload['name'] = $normalizedName;
            }
        }

        if (array_key_exists('email', $validated)) {
            $normalizedEmail = $this->sanitizeEmail($validated['email']);

            if ($normalizedEmail !== null) {
                $payload['email'] = $normalizedEmail;
            }
        }

        if (array_key_exists('password', $validated)) {
            $normalizedPassword = $this->normalizeNullableString($validated['password']);

            if ($normalizedPassword !== null) {
                $payload['password'] = $normalizedPassword;
            }
        }

        if (array_key_exists('role', $validated)) {
            $normalizedRole = $this->normalizeRole($validated['role']);

            if ($normalizedRole !== null) {
                $payload['role'] = $normalizedRole;
            }
        }

        if (array_key_exists('is_active', $validated)) {
            $payload['is_active'] = $this->normalizeBoolean($validated['is_active']);
        }

        return $payload;
    }

    /**
     * Build a standard JSON resource response.
     *
     * @param array<string, mixed> $meta
     */
    private function resourceResponse(UserResource $resource, int $status, array $meta = []): JsonResponse
    {
        $response = $resource
            ->additional($meta)
            ->response();

        $response->setStatusCode($status);

        $this->applyDefaultSecurityHeaders($response);

        return $response;
    }

    /**
     * Build a standard JSON response.
     *
     * @param array<string, mixed> $data
     */
    private function jsonResponse(array $data, int $status): JsonResponse
    {
        $response = response()->json($data, $status);

        $this->applyDefaultSecurityHeaders($response);

        return $response;
    }

    /**
     * Apply baseline security and cache-control headers.
     */
    private function applyDefaultSecurityHeaders(JsonResponse $response): void
    {
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
    }

    /**
     * Normalize the requested pagination size.
     */
    private function normalizePerPage(mixed $value): int
    {
        $perPage = $this->normalizePositiveInt($value) ?? self::DEFAULT_PER_PAGE;

        return max(self::MIN_PER_PAGE, min($perPage, self::MAX_PER_PAGE));
    }

    /**
     * Normalize a role value.
     */
    private function normalizeRole(mixed $value): ?string
    {
        $role = $this->normalizeNullableString($value);

        if ($role === null) {
            return null;
        }

        return mb_strtoupper($role);
    }

    /**
     * Normalize a user display name.
     */
    private function normalizePersonName(mixed $value): ?string
    {
        $name = $this->normalizeNullableString($value);

        if ($name === null) {
            return null;
        }

        $name = preg_replace('/\s+/u', ' ', $name);

        if (! is_string($name)) {
            return null;
        }

        return mb_substr($name, 0, 255);
    }

    /**
     * Convert an input into a nullable trimmed string.
     */
    private function normalizeNullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $value = trim($value);

            return $value === '' ? null : $value;
        }

        if (is_scalar($value)) {
            $string = trim((string) $value);

            return $string === '' ? null : $string;
        }

        return null;
    }

    /**
     * Normalize a positive integer value.
     */
    private function normalizePositiveInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }

        if (is_string($value)) {
            $value = trim($value);

            if ($value === '' || ! preg_match('/^\d+$/', $value)) {
                return null;
            }

            $intValue = (int) $value;

            return $intValue > 0 ? $intValue : null;
        }

        return null;
    }

    /**
     * Normalize a boolean-like value.
     */
    private function normalizeBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        if (is_string($value)) {
            $normalized = mb_strtolower(trim($value));

            return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
        }

        return false;
    }

    /**
     * Sanitize and validate an email address.
     */
    private function sanitizeEmail(mixed $value): ?string
    {
        $email = $this->normalizeNullableString($value);

        if ($email === null) {
            return null;
        }

        $sanitized = filter_var($email, FILTER_SANITIZE_EMAIL);

        if (! is_string($sanitized) || $sanitized === '') {
            return null;
        }

        return filter_var($sanitized, FILTER_VALIDATE_EMAIL)
            ? mb_strtolower($sanitized)
            : null;
    }
}