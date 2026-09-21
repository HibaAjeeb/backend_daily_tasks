<?php

namespace App\Traits;

use App\Exceptions\ApiErrorResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

trait ApiResponse
{
    protected function success($data = null, int $status = 200, array $extraMeta = []): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => array_merge(['timestamp' => now()->toIso8601String()], $extraMeta),
        ], $status, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
    }

    protected function paginated(AnonymousResourceCollection $resource): \Illuminate\Http\JsonResponse
    {
        $paginator = $resource->resource;

        return response()->json([
            'success' => true,
            'data' => $resource->collection,
            'meta' => [
                'page' => $paginator->currentPage(),
                'pageSize' => $paginator->perPage(),
                'totalItems' => $paginator->total(),
                'totalPages' => $paginator->lastPage(),
            ],
        ], 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
    }

    protected function error(string $code, string $message, int $status, array $details = []): \Illuminate\Http\JsonResponse
    {
        return ApiErrorResponse::make($code, $message, $status, $details);
    }
}
