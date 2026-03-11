<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClientIndexRequest;
use App\Http\Resources\ClientDetailResource;
use App\Http\Resources\ClientResource;
use App\Models\Client;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ClientController extends Controller
{
    private const DEFAULT_PER_PAGE = 15;
    private const MIN_PER_PAGE = 1;
    private const MAX_PER_PAGE = 100;

    /**
     * List clients using optional filters.
     */
    public function index(ClientIndexRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();
        $perPage = $this->normalizePerPage($filters['per_page'] ?? self::DEFAULT_PER_PAGE);

        $clients = Client::query()
            ->withCount('transactions')
            ->when(
                $this->normalizeNullableString($filters['search'] ?? null) !== null,
                function (Builder $query) use ($filters): Builder {
                    $search = $this->normalizeNullableString($filters['search']);

                    return $query->where(function (Builder $builder) use ($search): void {
                        $builder
                            ->where('name', 'like', '%' . $search . '%')
                            ->orWhere('document', 'like', '%' . $search . '%')
                            ->orWhere('email', 'like', '%' . $search . '%');
                    });
                }
            )
            ->orderByDesc('id')
            ->paginate($perPage)
            ->appends($request->query());

        return ClientResource::collection($clients);
    }

    /**
     * Display a single client with transaction details.
     */
    public function show(Client $client): ClientDetailResource
    {
        $client->load([
            'transactions.gateway',
            'transactions.products',
            'transactions.attempts',
            'transactions.refunds',
        ]);

        $client->loadCount('transactions');

        return new ClientDetailResource($client);
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
     * Convert a scalar input into a nullable trimmed string.
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

            $normalized = (int) $value;

            return $normalized > 0 ? $normalized : null;
        }

        if (is_float($value)) {
            $normalized = (int) $value;

            return $normalized > 0 ? $normalized : null;
        }

        return null;
    }
}