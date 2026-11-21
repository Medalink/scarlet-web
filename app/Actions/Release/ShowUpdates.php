<?php

namespace App\Actions\Release;

use App\Models\Account;
use Medalink\AppVersion\ReleaseNotes\ReleaseNotesFeed;

class ShowUpdates
{
    public function __construct(private readonly ReleaseNotesFeed $feed) {}

    /**
     * What the account has not read yet. The first call for an account records where it starts reading, so a new
     * account is shown the current release and nothing older.
     *
     * @return array{current: array<string, mixed>|null, title: string, unreadCount: int, showBanner: bool, releases: list<array<string, mixed>>, hiddenCount: int}
     */
    public function handle(Account $account): array
    {
        return $this->feed->describe($account);
    }
}
