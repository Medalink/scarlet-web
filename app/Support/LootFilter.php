<?php

namespace App\Support;

/** The query parameters of `/loot`, as one value: which table, and what it is narrowed to (docs/LOOT.md, Loot index). */
final readonly class LootFilter
{
    public function __construct(
        public LootView $view = LootView::Creatures,
        public ?int $zone = null,
        public ?int $levelMin = null,
        public ?int $levelMax = null,
        public ?int $quality = null,
        public string $text = '',
        public LootSort $sort = LootSort::Windows,
    ) {}

    /** The text as a `like` pattern that holds it anywhere, or null with no text. */
    public function like(): ?string
    {
        $text = trim($this->text);

        return $text === '' ? null : '%'.addcslashes($text, '%_\\').'%';
    }

    /** The text as an id when it is only digits. */
    public function id(): ?int
    {
        $text = trim($this->text);

        return ctype_digit($text) && strlen($text) <= 9 ? (int) $text : null;
    }

    /**
     * The parameters that differ from the defaults, to build a link that keeps them.
     *
     * @return array<string, int|string>
     */
    public function params(): array
    {
        return array_filter([
            'view' => $this->view === LootView::Creatures ? null : $this->view->value,
            'zone' => $this->zone,
            'min' => $this->levelMin,
            'max' => $this->levelMax,
            'quality' => $this->quality,
            'q' => trim($this->text) === '' ? null : trim($this->text),
            'sort' => $this->sort === LootSort::Windows ? null : $this->sort->value,
        ], fn (mixed $value): bool => $value !== null);
    }
}
