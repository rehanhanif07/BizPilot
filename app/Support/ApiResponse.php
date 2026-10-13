<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use InvalidArgumentException;

/**
 * Builds every /api/v1 response so the envelope described in
 * docs/api-conventions.md cannot drift between endpoints.
 */
class ApiResponse
{
    /**
     * @param  JsonResource|array<array-key, mixed>|null  $data
     */
    public static function success(string $message, JsonResource|array|null $data = null, int $status = 200): JsonResponse
    {
        return new JsonResponse([
            'success' => true,
            'message' => $message,
            'data' => $data instanceof JsonResource ? $data->resolve(request()) : $data,
        ], $status);
    }

    /**
     * @param  array<string, mixed>  $errors
     */
    public static function error(string $message, array $errors = [], int $status = 400): JsonResponse
    {
        return new JsonResponse([
            'success' => false,
            'message' => $message,
            'errors' => (object) $errors,
        ], $status);
    }

    public static function paginated(string $message, ResourceCollection $collection): JsonResponse
    {
        $paginator = $collection->resource;

        if (! $paginator instanceof LengthAwarePaginator) {
            throw new InvalidArgumentException('ApiResponse::paginated() expects a collection built from a LengthAwarePaginator.');
        }

        return new JsonResponse([
            'success' => true,
            'message' => $message,
            'data' => $collection->resolve(request()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
            'links' => [
                'first' => $paginator->url(1),
                'last' => $paginator->url($paginator->lastPage()),
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
        ]);
    }
}
