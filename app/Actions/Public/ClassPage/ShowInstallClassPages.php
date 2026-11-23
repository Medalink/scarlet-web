<?php

namespace App\Actions\Public\ClassPage;

use App\Models\Character;
use App\Models\Install;
use App\Support\ClassPage\ClassIcons;
use App\Support\ClassPage\ClassPageJson;

class ShowInstallClassPages
{
    public function __construct(private readonly BuildClassPage $pages) {}

    /**
     * The class page of every character the install owns that has one, most recently seen first, public or not, as the
     * app draws it. A character with no class page is absent. A name limits the answer to that character.
     *
     * @return list<array<string, mixed>>
     */
    public function handle(Install $install, ?string $name = null): array
    {
        $characters = Character::query()->ownedBy($install)
            ->when($name !== null, fn ($query) => $query->where('characters.name_key', Character::keyFor((string) $name)))
            ->orderByRaw('characters.last_seen_at desc nulls last')->orderBy('characters.id')
            ->get();
        $origin = rtrim((string) config('app.url'), '/');
        $entries = [];
        foreach ($characters as $character) {
            $page = $this->pages->handle($character);
            if ($page !== null) {
                $entries[] = ['name' => $character->name] + (new ClassPageJson(ClassIcons::of($page), $origin))->page($page);
            }
        }

        return $entries;
    }
}
