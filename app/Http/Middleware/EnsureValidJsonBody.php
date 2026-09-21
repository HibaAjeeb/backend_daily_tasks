<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureValidJsonBody
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isJson() && ! $this->isValidJson($request->getContent())) {
            throw new ApiException(
                'INVALID_JSON',
                'The request body must be valid JSON. Send it with Content-Type: application/json.',
                400,
            );
        }

        return $next($request);
    }

    private function isValidJson(string $content): bool
    {
        if (trim($content) === '') {
            return true;
        }

        json_decode($content);

        return json_last_error() === JSON_ERROR_NONE;
    }
}
