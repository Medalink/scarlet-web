<?php

namespace App\Http\Requests\Web;

use App\Support\Cursor;
use App\Support\LootFilter;
use App\Support\LootSort;
use App\Support\LootView;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** The table, the filters, the order and the page of `/loot`, as query parameters. */
class LootRequest extends FormRequest
{
    /** The `view` of the tab that is not a table of sources: loot rules and group size. */
    public const string GROUPS = 'groups';

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'view' => ['nullable', Rule::in([...array_column(LootView::cases(), 'value'), self::GROUPS])],
            'zone' => ['nullable', 'integer', 'between:-100000,1000000'],
            'min' => ['nullable', 'integer', 'between:-1,80'],
            'max' => ['nullable', 'integer', 'between:-1,80'],
            'quality' => ['nullable', 'integer', 'between:0,5'],
            'q' => ['nullable', 'string', 'max:60', 'regex:/^\P{Cc}*\z/u'],
            'sort' => ['nullable', Rule::enum(LootSort::class)],
            'cursor' => ['nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9_-]+$/'],
        ];
    }

    public function groups(): bool
    {
        return $this->validated('view') === self::GROUPS;
    }

    public function filter(): LootFilter
    {
        $view = $this->validated('view');
        $sort = $this->validated('sort');
        $text = $this->validated('q');

        return new LootFilter(
            is_string($view) ? LootView::tryFrom($view) ?? LootView::Creatures : LootView::Creatures,
            $this->whole('zone'),
            $this->whole('min'),
            $this->whole('max'),
            $this->whole('quality'),
            is_string($text) ? trim($text) : '',
            is_string($sort) ? LootSort::from($sort) : LootSort::Windows,
        );
    }

    public function offset(): int
    {
        $cursor = $this->validated('cursor');

        return Cursor::offset(is_string($cursor) ? $cursor : null);
    }

    private function whole(string $key): ?int
    {
        $value = $this->validated($key);

        return is_numeric($value) ? (int) $value : null;
    }
}
