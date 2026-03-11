<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductIndexRequest;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

final class ProductController extends Controller
{
    private const DEFAULT_PER_PAGE = 15;
    private const MIN_PER_PAGE = 1;
    private const MAX_PER_PAGE = 100;

    /**
     * List products using optional filters.
     */
    public function index(ProductIndexRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();
        $perPage = $this->normalizePerPage($filters['per_page'] ?? self::DEFAULT_PER_PAGE);

        $products = Product::query()
            ->when(
                $this->normalizeNullableString($filters['search'] ?? null) !== null,
                function (Builder $query) use ($filters): Builder {
                    $search = $this->normalizeNullableString($filters['search']);

                    return $query->where(function (Builder $builder) use ($search): void {
                        $builder->where('name', 'like', '%' . $search . '%');
                    });
                }
            )
            ->when(
                array_key_exists('is_active', $filters) && $filters['is_active'] !== null,
                fn (Builder $query): Builder => $query->where('is_active', (bool) $filters['is_active'])
            )
            ->orderByDesc('id')
            ->paginate($perPage)
            ->appends($request->query());

        return ProductResource::collection($products);
    }

    /**
     * Display a single product resource.
     */
    public function show(Product $product): ProductResource
    {
        return new ProductResource($product);
    }

    /**
     * Store a newly created product.
     */
    public function store(StoreProductRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $product = Product::query()->create([
            'name' => $this->normalizeProductName($validated['name']),
            'amount' => $this->normalizePositiveInt($validated['amount']),
            'is_active' => $this->normalizeBoolean($validated['is_active'] ?? true),
        ]);

        return $this->resourceResponse(
            resource: new ProductResource($product),
            status: Response::HTTP_CREATED,
            meta: [
                'message' => 'Product created successfully.',
            ]
        );
    }

    /**
     * Update an existing product.
     */
    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $validated = $request->validated();
        $payload = $this->buildUpdatePayload($validated);

        if ($payload !== []) {
            $product->fill($payload);

            if ($product->isDirty()) {
                $product->save();
            }
        }

        return $this->resourceResponse(
            resource: new ProductResource($product->refresh()),
            status: Response::HTTP_OK,
            meta: [
                'message' => 'Product updated successfully.',
            ]
        );
    }

    /**
     * Delete an existing product.
     */
    public function destroy(Product $product): JsonResponse
    {
        $product->delete();

        return $this->jsonResponse(
            data: [
                'message' => 'Product deleted successfully.',
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
            $normalizedName = $this->normalizeProductName($validated['name']);

            if ($normalizedName !== null) {
                $payload['name'] = $normalizedName;
            }
        }

        if (array_key_exists('amount', $validated)) {
            $normalizedAmount = $this->normalizePositiveInt($validated['amount']);

            if ($normalizedAmount !== null) {
                $payload['amount'] = $normalizedAmount;
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
    private function resourceResponse(ProductResource $resource, int $status, array $meta = []): JsonResponse
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
     * Normalize a product display name.
     */
    private function normalizeProductName(mixed $value): ?string
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

            if ($value === '' || preg_match('/^\d+$/', $value) !== 1) {
                return null;
            }

            $intValue = (int) $value;

            return $intValue > 0 ? $intValue : null;
        }

        if (is_float($value)) {
            $intValue = (int) $value;

            return $intValue > 0 ? $intValue : null;
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