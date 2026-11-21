<?php

namespace App\Http\Requests\Web;

use App\Support\Cursor;
use App\Support\SearchKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** The text, the kind and the page of a search, as query parameters. */
class SearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:80'],
            'kind' => ['nullable', Rule::enum(SearchKind::class)],
            'cursor' => ['nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9_-]+$/'],
        ];
    }

    /** The text to look for; empty while it is shorter than two characters. */
    public function term(): string
    {
        $term = $this->validated('q');
        $term = is_string($term) ? trim($term) : '';

        return mb_strlen($term) >= 2 ? $term : '';
    }

    public function shortTerm(): string
    {
        $term = $this->validated('q');

        return is_string($term) ? trim($term) : '';
    }

    public function kind(): ?SearchKind
    {
        $kind = $this->validated('kind');

        return is_string($kind) ? SearchKind::from($kind) : null;
    }

    public function offset(): int
    {
        $cursor = $this->validated('cursor');

        return Cursor::offset(is_string($cursor) ? $cursor : null);
    }
}
