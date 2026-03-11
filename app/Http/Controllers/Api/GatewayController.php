<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Contracts\GatewayRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\GatewayIndexRequest;
use App\Http\Requests\SetGatewayActiveRequest;
use App\Http\Requests\UpdateGatewayPriorityRequest;
use App\Http\Resources\GatewayResource;
use App\Models\Gateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

final class GatewayController extends Controller
{
    private const DEFAULT_PER_PAGE = 15;
    private const MIN_PER_PAGE = 1;
    private const MAX_PER_PAGE = 100;

    public function __construct(
        private readonly GatewayRepositoryInterface $gatewayRepository,
    ) {
    }

    /**
     * List gateways using optional filters.
     */
    public function index(GatewayIndexRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();
        $perPage = $this->normalizePerPage($filters['per_page'] ?? self::DEFAULT_PER_PAGE);

        $gateways = Gateway::query()
            ->when(
                array_key_exists('is_active', $filters) && $filters['is_active'] !== null,
                fn ($query) => $query->where('is_active', (bool) $filters['is_active'])
            )
            ->orderBy('priority')
            ->orderBy('id')
            ->paginate($perPage)
            ->appends($request->query());

        return GatewayResource::collection($gateways);
    }

    /**
     * Display a single gateway resource.
     */
    public function show(Gateway $gateway): GatewayResource
    {
        return new GatewayResource($gateway);
    }

    /**
     * Update the priority of the provided gateway.
     */
    public function updatePriority(UpdateGatewayPriorityRequest $request, Gateway $gateway): JsonResponse
    {
        $validated = $request->validated();

        $updated = $this->gatewayRepository->updatePriority(
            $gateway,
            $this->normalizePositiveInt($validated['priority']) ?? 1,
        );

        return $this->resourceResponse(
            resource: new GatewayResource($updated->refresh()),
            status: Response::HTTP_OK,
            meta: [
                'message' => 'Gateway priority updated successfully.',
            ]
        );
    }

    /**
     * Update the active state of the provided gateway.
     */
    public function setActive(SetGatewayActiveRequest $request, Gateway $gateway): JsonResponse
    {
        $validated = $request->validated();

        $updated = $this->gatewayRepository->setActive(
            $gateway,
            $this->normalizeBoolean($validated['is_active'])
        );

        return $this->resourceResponse(
            resource: new GatewayResource($updated->refresh()),
            status: Response::HTTP_OK,
            meta: [
                'message' => 'Gateway status updated successfully.',
            ]
        );
    }

    /**
     * Build a standard JSON resource response.
     *
     * @param array<string, mixed> $meta
     */
    private function resourceResponse(GatewayResource $resource, int $status, array $meta = []): JsonResponse
    {
        $response = $resource
            ->additional($meta)
            ->response();

        $response->setStatusCode($status);

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

            if ($value === '' || preg_match('/^\d+$/', $value) !== 1) {
                return null;
            }

            $normalized = (int) $value;

            return $normalized > 0 ? $normalized : null;
        }

        if (is_float($value)) {
            $normalized = (int) $value;

            return $normalized > 0 ? $normalized : null;
        }

        return null;
    }

    /**
     * Normalize a boolean-like input.
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
            return in_array(
                mb_strtolower(trim($value)),
                ['1', 'true', 'yes', 'on'],
                true
            );
        }

        return false;
    }
}