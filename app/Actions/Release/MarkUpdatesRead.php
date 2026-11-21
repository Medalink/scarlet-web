<?php

namespace App\Actions\Release;

use App\Models\Account;
use Medalink\AppVersion\ReleaseNotes\ReleaseNotesFeed;

class MarkUpdatesRead
{
    public function __construct(private readonly ReleaseNotesFeed $feed) {}

    /** Every release the banner listed is read; the banner stays away until the next release. */
    public function handle(Account $account): void
    {
        $this->feed->markAsRead($account);
    }
}
