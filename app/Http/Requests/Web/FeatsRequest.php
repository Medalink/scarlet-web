<?php

namespace App\Http\Requests\Web;

use App\Actions\Feats\ShowFeatsHub;
use App\Support\FeatCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** The query of /feats and /feats/{key}: whose feats, and how the wall is cut. */
class FeatsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'scope' => ['nullable', Rule::in(ShowFeatsHub::SCOPES)],
            'as' => ['nullable', 'string', 'max:64'],
            'tier' => ['nullable', 'integer', 'between:0,5'],
            'family' => ['nullable', Rule::in(FeatCatalog::FAMILIES)],
            'q' => ['nullable', 'string', 'max:40'],
            'feat' => ['nullable', 'regex:/^[a-z-]{1,32}$/'],
        ];
    }

    /** The account the page is shown to, or null for a visitor. */
    public function viewerAccountId(): ?int
    {
        $user = $this->user();

        return $user === null ? null : (int) $user->getAuthIdentifier();
    }

    /** The scope asked for: signed in, the owner's own comparison unless they chose another. */
    public function scope(): string
    {
        $scope = $this->validated('scope');

        return is_string($scope) ? $scope : 'compare';
    }

    /**
     * @return array{scope: string|null, as: string|null, tier: string|null, family: string|null, q: string|null, feat: string|null}
     */
    public function cut(): array
    {
        $get = fn (string $key): ?string => is_scalar($this->validated($key)) ? (string) $this->validated($key) : null;

        return ['scope' => $get('scope'), 'as' => $get('as'), 'tier' => $get('tier'), 'family' => $get('family'), 'q' => $get('q'), 'feat' => $get('feat')];
    }

    public function as(): ?string
    {
        $as = $this->validated('as');

        return is_string($as) ? $as : null;
    }
}
