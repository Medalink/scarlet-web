<?php

namespace App\Support;

/**
 * What `addons:audit` found: what each character holds per scope and kind, the violations that fail the audit and the
 * notes that do not.
 */
final readonly class AddonAuditReport
{
    /**
     * @param  list<array{character: string, scope: string, kind: string, claims: int, amounts: array<string, int|float>}>  $totals
     * @param  list<string>  $violations
     * @param  list<string>  $notes
     */
    public function __construct(
        public array $totals,
        public array $violations,
        public array $notes,
    ) {}

    public function passes(): bool
    {
        return $this->violations === [];
    }
}
