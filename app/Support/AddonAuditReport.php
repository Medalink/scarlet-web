<?php

namespace App\Support;

/**
 * What `addons:audit` found: what each character holds per scope and kind, the violations that fail the audit, the
 * notes that do not and the tables that explain each total.
 */
final readonly class AddonAuditReport
{
    /**
     * @param  list<array{character: string, scope: string, kind: string, claims: int, amounts: array<string, int|float>}>  $totals
     * @param  list<string>  $violations
     * @param  list<string>  $notes
     * @param  list<array{character: string, kind: string, statuses: list<array{status: string, claims: int, claimed: array<string, int|float>, counted: array<string, int|float>, rule: string}>, scarlet: array<string, int>|null, total: array<string, int|float>|null}>  $explain
     */
    public function __construct(
        public array $totals,
        public array $violations,
        public array $notes,
        public array $explain,
    ) {}

    public function passes(): bool
    {
        return $this->violations === [];
    }
}
