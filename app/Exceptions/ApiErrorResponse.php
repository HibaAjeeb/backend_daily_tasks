<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

final class ApiErrorResponse
{
    public static function make(string $code, string $message, int $status, array $details = []): JsonResponse
    {
        $key = "errors.{$code}";
        $translated = __($key);

        if ($translated !== $key) {
            $message = $translated;
        }

        return response()->json([
            'success' => false,
            'error' => array_filter([
                'code' => $code,
                'message' => $message,
                'details' => $details ?: null,
            ]),
            'meta' => [
                'timestamp' => now()->toIso8601String(),
                'requestId' => 'req_'.Str::random(8),
            ],
        ], $status, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
    }
}
