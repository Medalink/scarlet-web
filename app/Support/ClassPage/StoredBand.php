<?php

namespace App\Support\ClassPage;

use App\Support\PlayerClass;
use InvalidArgumentException;
use JsonException;

/** How a `ClassBand` is kept in a `class_bands` row: the figures and the class icon as JSON. A row that does not read back as a band reads as no band. */
final class StoredBand
{
    /**
     * @param  list<Figure>  $figures
     * @return array{icon: string|null, figures: string} the columns that hold a band's figures and class icon
     */
    public static function columns(array $figures, ?Icon $icon): array
    {
        return [
            'icon' => $icon === null ? null : self::json(self::icon($icon)),
            'figures' => self::json(array_map(fn (Figure $figure): array => [
                'label' => $figure->label,
                'value' => $figure->value,
                'unit' => $figure->unit->name,
                'of' => $figure->of,
                'icon' => $figure->icon === null ? null : self::icon($figure->icon),
            ], $figures)),
        ];
    }

    public static function restore(PlayerClass $class, string $href, ?string $icon, string $figures): ?ClassBand
    {
        try {
            $rows = json_decode($figures, true, 8, JSON_THROW_ON_ERROR);
            $classIcon = $icon === null ? null : self::restoreIcon(json_decode($icon, true, 8, JSON_THROW_ON_ERROR));
            $restored = is_array($rows) ? array_map(self::restoreFigure(...), array_values($rows)) : [];
            if (in_array(null, $restored, true)) {
                return null;
            }

            return new ClassBand($class, $href, $restored, $classIcon);
        } catch (JsonException|InvalidArgumentException) {
            return null;
        }
    }

    private static function restoreFigure(mixed $row): ?Figure
    {
        if (! is_array($row) || ! is_string($row['label'] ?? null) || ! (is_int($row['value'] ?? null) || is_float($row['value'] ?? null) || is_string($row['value'] ?? null))) {
            return null;
        }
        $unit = array_find(Unit::cases(), fn (Unit $unit): bool => $unit->name === ($row['unit'] ?? null));
        $of = $row['of'] ?? null;

        return new Figure($row['label'], $row['value'], $unit ?? Unit::Count, is_string($of) ? $of : null, self::restoreIcon($row['icon'] ?? null));
    }

    private static function restoreIcon(mixed $icon): ?Icon
    {
        if (! is_array($icon) || ! is_int($icon['id'] ?? null)) {
            return null;
        }
        $kind = array_find(IconKind::cases(), fn (IconKind $kind): bool => $kind->name === ($icon['kind'] ?? null));

        return $kind === null ? null : new Icon($kind, $icon['id']);
    }

    /** @return array{kind: string, id: int} */
    private static function icon(Icon $icon): array
    {
        return ['kind' => $icon->kind->name, 'id' => $icon->id];
    }

    private static function json(mixed $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR);
    }
}
