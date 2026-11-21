<?php

namespace App\Actions\Release;

use App\Models\Account;
use Medalink\AppVersion\ReleaseNotes\ReleaseNotesFeed;

class DismissUpdates
{
    public function __construct(private readonly ReleaseNotesFeed $feed) {}

    /** Hides the banner for the current release. Nothing is marked read. */
    public function handle(Account $account): void
    {
        $this->feed->dismiss($account);
    }
}
