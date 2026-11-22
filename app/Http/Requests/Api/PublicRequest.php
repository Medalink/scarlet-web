<?php

namespace App\Http\Requests\Api;

use App\Support\Cursor;
use App\Support\Exceptions\ApiException;
use App\Support\HeatGrid;
use App\Support\Server;
use App\Support\WorldLayer;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The query parameters of every /api/v1/public endpoint. A parameter an endpoint does not use is ignored; a bad
 * one answers `422 invalid_request` with the field names.
 */
class PublicRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'ruleset' => ['nullable', 'string', 'max:64', 'regex:/^[a-z0-9-]+$/'],
            'server' => ['nullable', Rule::enum(Server::class)],
            'layer' => ['nullable', Rule::enum(WorldLayer::class)],
            'class' => ['nullable', 'string', 'max:32', 'regex:/^[A-Za-z]+$/'],
            'creature' => ['nullable', 'integer', 'min:1'],
            'level' => ['nullable', 'integer', 'min:0', 'max:'.(HeatGrid::LEVELS - 1)],
            'cursor' => ['nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9_-]+$/'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.Cursor::MAX_LIMIT],
            'q' => ['nullable', 'string', 'min:2', 'max:80', 'regex:/^\P{Cc}*\z/u'],
            'kind' => ['nullable', 'string', 'max:16'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw ApiException::invalidRequest('A query parameter is not valid.', $validator->errors()->toArray());
    }

    public function rulesetSlug(): ?string
    {
        $slug = $this->validated('ruleset');

        return is_string($slug) ? $slug : null;
    }

    public function serverFilter(): Server
    {
        $server = $this->validated('server');

        return is_string($server) ? Server::from($server) : Server::All;
    }

    /** A class file name such as `WARRIOR`, any case. */
    public function characterClass(): ?string
    {
        $class = $this->validated('class');

        return is_string($class) ? strtoupper($class) : null;
    }

    public function layer(): WorldLayer
    {
        $layer = $this->validated('layer');

        return is_string($layer) ? WorldLayer::from($layer) : WorldLayer::Paths;
    }

    public function level(): int
    {
        $level = $this->validated('level');

        return is_numeric($level) ? (int) $level : 0;
    }

    public function creatureId(): ?int
    {
        $id = $this->validated('creature');

        return is_numeric($id) ? (int) $id : null;
    }

    public function offset(): int
    {
        $cursor = $this->validated('cursor');

        return Cursor::offset(is_string($cursor) ? $cursor : null);
    }

    public function limit(): int
    {
        $limit = $this->validated('limit');

        return is_numeric($limit) ? (int) $limit : Cursor::DEFAULT_LIMIT;
    }

    public function term(): ?string
    {
        $term = $this->validated('q');

        return is_string($term) ? trim($term) : null;
    }

    public function kind(): ?string
    {
        $kind = $this->validated('kind');

        return is_string($kind) ? $kind : null;
    }
}
