<?php

namespace App\Actions\Admin;

use App\Models\Character;
use Illuminate\Database\Eloquent\Builder;

/**
 * Every character for /admin/characters, public and private, newest seen first (docs/DESIGN.md, Admin). A search matches
 * part of the name, or `#12` the characters of account 12; `public` true or false keeps one visibility.
 */
class ListCharacters
{
    public const int PER_PAGE = 50;

    /**
     * @return array{rows: list<array<string, mixed>>, matched: int, public: int, private: int, page: int, pages: int}
     */
    public function handle(string $search, ?bool $public, int $page): array
    {
        $counts = self::matching($search)->toBase()
            ->selectRaw('count(*) filter (where is_public) as public, count(*) filter (where not is_public) as private')->first();
        $shownPublic = (int) ($counts->public ?? 0);
        $shownPrivate = (int) ($counts->private ?? 0);
        $matched = match ($public) {
            true => $shownPublic,
            false => $shownPrivate,
            null => $shownPublic + $shownPrivate,
        };
        $pages = max(1, (int) ceil($matched / self::PER_PAGE));
        $page = min(max(1, $page), $pages);

        $query = self::matching($search)->leftJoin('realms', 'realms.id', '=', 'characters.home_realm_id');
        if ($public !== null) {
            $query->where('characters.is_public', $public);
        }
        $rows = $query->toBase()
            ->select(['characters.id', 'characters.name', 'characters.account_id', 'characters.class', 'characters.race', 'characters.faction', 'characters.level',
                'characters.is_public', 'characters.plausibility', 'characters.sessions_decoded', 'characters.played_seconds', 'realms.name as realm'])
            ->selectRaw('(select count(*) from install_character ic where ic.character_id = characters.id) as installs')
            ->selectRaw('characters.first_seen_at::text as first_seen_at, characters.last_seen_at::text as last_seen_at')
            ->orderByRaw('characters.last_seen_at desc nulls last')->orderByDesc('characters.id')
            ->forPage($page, self::PER_PAGE)->get();

        return [
            'rows' => array_values($rows->map(fn (object $row): array => [
                'name' => (string) $row->name,
                'account_id' => $row->account_id === null ? null : (int) $row->account_id,
                'class' => $row->class === null ? null : (string) $row->class,
                'race' => $row->race === null ? null : (string) $row->race,
                'faction' => $row->faction === null ? null : (string) $row->faction,
                'level' => $row->level === null ? null : (int) $row->level,
                'public' => (bool) $row->is_public,
                'flagged' => $row->plausibility === Character::FLAGGED,
                'realm' => $row->realm === null ? null : (string) $row->realm,
                'installs' => (int) $row->installs,
                'sessions' => (int) $row->sessions_decoded,
                'played' => (int) $row->played_seconds,
                'first_seen_at' => $row->first_seen_at === null ? null : (string) $row->first_seen_at,
                'last_seen_at' => $row->last_seen_at === null ? null : (string) $row->last_seen_at,
            ])->all()),
            'matched' => $matched,
            'public' => $shownPublic,
            'private' => $shownPrivate,
            'page' => $page,
            'pages' => $pages,
        ];
    }

    /** @return Builder<Character> */
    private static function matching(string $search): Builder
    {
        $query = Character::query();
        if (preg_match('/^#(\d{1,18})$/', $search, $account) === 1) {
            return $query->where('characters.account_id', (int) $account[1]);
        }
        if ($search !== '') {
            $query->where('characters.name_key', 'like', '%'.addcslashes(Character::keyFor($search), '%_\\').'%');
        }

        return $query;
    }
}
