<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Support\Exceptions\ApiException;
use Illuminate\Http\JsonResponse;

/** The envelope of the public API: `{"data": ..., "meta": {...}}`, with `next_cursor` in `meta` on a paginated answer. */
abstract class PublicController extends Controller
{
    /** @param  array<string, mixed>  $meta */
    protected function respond(mixed $data, array $meta = []): JsonResponse
    {
        return response()->json(['data' => $data, 'meta' => (object) $meta]);
    }

    /**
     * @param  list<mixed>  $rows  the rows of the page, already cut to the limit
     * @param  array<string, mixed>  $meta
     */
    protected function respondPage(array $rows, ?string $next, array $meta = []): JsonResponse
    {
        return $this->respond($rows, [...$meta, 'next_cursor' => $next]);
    }

    /** @throws ApiException */
    protected function missing(string $what): never
    {
        throw ApiException::notFound($what);
    }
}
