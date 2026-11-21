<?php

namespace App\Actions\Release;

use Illuminate\Support\Collection;
use Medalink\AppVersion\Models\ReleaseNote;
use Medalink\AppVersion\ReleaseNotes\ReleaseNotesArchive;

class ShowReleaseNotes
{
    public function __construct(private readonly ReleaseNotesArchive $archive) {}

    /** @return Collection<int, ReleaseNote> every published release, newest version first */
    public function handle(): Collection
    {
        return $this->archive->all();
    }
}
