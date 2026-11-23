<?php

namespace App\View\Sc;

/** The feat seal of FEATS.md as one inline SVG: the family is the plate, the tier its metal, pips count the tier, a 20-tick dial shows the way to the next one. */
final class Seal
{
    public const array METAL = [1 => '#c58a63', 2 => '#c3c7cf', 3 => '#d9b25f', 4 => '#ff2d55', 5 => '#ff2d55'];

    public const array NEXT = ['#c58a63', '#c3c7cf', '#d9b25f', '#ff2d55', '#d9b25f', '#d9b25f'];

    public const array TIER_NAMES = ['', 'Copper', 'Silver', 'Gold', 'Scarlet', 'Legend'];

    private const array PLATE_OPACITY = [1 => 0.14, 2 => 0.18, 3 => 0.22];

    private const string GOLD = '#d9b25f';

    private const string SCARLET = '#ff2d55';

    private const string DARK = 'rgba(255,255,255,0.22)';

    private const array SHIFT = ['combat' => -2, 'quests' => -4, 'dungeons' => 2.5, 'role' => -1.5];

    private const array FAMILY_OF = ['level' => 'journey', 'hours' => 'journey', 'clock' => 'journey', 'crown' => 'journey', 'characters' => 'journey', 'emote' => 'journey', 'fire' => 'journey', 'kills' => 'combat', 'deaths' => 'combat', 'shield' => 'combat', 'quests' => 'quests', 'dungeons' => 'dungeons', 'zones' => 'world', 'distance' => 'world', 'jumps' => 'world', 'swim' => 'world', 'pin' => 'world', 'creature' => 'world', 'wing' => 'world', 'gold' => 'trade', 'skills' => 'trade', 'fish' => 'trade', 'gather' => 'trade', 'item' => 'trade', 'spell' => 'class', 'pet' => 'class', 'talents' => 'class', 'heal' => 'role'];

    /**
     * @param  ?string  $family  one of journey, combat, quests, dungeons, world, trade, class, role; read from the glyph when null
     * @param  int  $ghost  a tier to draw as a dashed outline of the next tier, for the nudge; 0 for none
     * @param  ?int  $spell  a spell id whose icon is engraved in place of the glyph
     */
    public static function svg(string $glyph, int $tier, float $progress, int $size, ?string $family = null, string $label = '', int $ghost = 0, ?int $spell = null): string
    {
        $family ??= self::FAMILY_OF[$glyph] ?? (str_starts_with($glyph, 'm-') ? 'class' : 'journey');
        $plate = self::plate($family);
        $full = $size >= 44;
        $tiny = $size < 22;
        $k = $full ? 1 : 1.36;
        $metal = self::METAL[$ghost ?: $tier] ?? self::DARK;
        $path = fn (float $scale, string $attrs): string => '<path d="'.$plate.'" transform="translate(32 32) scale('.round($scale * $k, 3).') translate(-32 -32)" '.$attrs.'/>';
        $out = '<svg viewBox="0 0 64 64" width="'.$size.'" height="'.$size.'" role="img" aria-label="'.e($label).'">';
        if ($full) {
            $out .= self::dial($tier, $progress).self::pips($tier);
        }
        $out .= '<g class="plate">'.self::body($path, $tier, $ghost, $metal, $full, $tiny);
        if (! $tiny) {
            $out .= self::glyph($glyph, $family, $tier, $ghost, $metal, $full, $spell);
        }

        return $out.'</g></svg>';
    }

    private static function body(\Closure $path, int $tier, int $ghost, string $metal, bool $full, bool $tiny): string
    {
        $s = $full ? 0.9 : 1;
        if ($tiny) {
            $fill = $tier ? ($tier >= 4 ? self::SCARLET : $metal) : 'none';
            $stroke = $tier === 5 ? self::GOLD : ($tier ? 'none' : 'rgba(255,255,255,0.3)');

            return $path(1, 'fill="'.$fill.'" stroke="'.$stroke.'" stroke-width="3"');
        }
        if ($ghost) {
            return $path($s, 'fill="none" stroke="'.$metal.'" stroke-width="'.($full ? 1.6 : 2.2).'" stroke-dasharray="3.5 3"');
        }
        if ($tier === 0) {
            return $path($s, 'fill="none" stroke="'.self::DARK.'" stroke-width="'.($full ? 1.4 : 2).'"');
        }
        if ($tier < 4) {
            $out = $path($s, 'fill="'.$metal.'" fill-opacity="'.(self::PLATE_OPACITY[$tier] ?? 0).'" stroke="'.$metal.'" stroke-width="'.($full ? 1.6 : 2.2).'"');

            return $tier === 3 ? $out.$path($s * 0.85, 'fill="none" stroke="'.$metal.'" stroke-width="'.($full ? 0.9 : 1.2).'" opacity="0.75"') : $out;
        }
        $rim = $tier === 5 ? self::GOLD : self::SCARLET;
        $width = $full ? ($tier === 5 ? 2.1 : 1.6) : 2.6;

        return $path($s, 'fill="none" stroke="'.$rim.'" stroke-width="'.$width.'"').$path($s * 0.84, 'fill="'.self::SCARLET.'"');
    }

    private static function glyph(string $glyph, string $family, int $tier, int $ghost, string $metal, bool $full, ?int $spell): string
    {
        $ink = $ghost ? $metal : ($tier >= 4 ? '#141416' : ($tier ? $metal : '#8a8a93'));
        $stand = str_starts_with($glyph, 'm-');
        $scale = ($full ? 0.9 : 1.2) * ($stand ? 0.92 : 1);
        $dy = (self::SHIFT[$family] ?? 0) * ($full ? 0.9 : 1.2);
        $at = 'translate('.round(32 - 12 * $scale, 3).' '.round(32 - 12 * $scale + $dy, 3).') scale('.round($scale, 3).')';
        $opacity = $tier || $ghost ? 1 : 0.75;
        if ($spell !== null) {
            return '<g transform="'.$at.'" opacity="'.$opacity.'"><clipPath id="c'.$spell.'"><rect width="24" height="24" rx="2.6"/></clipPath><g clip-path="url(#c'.$spell.')"><image href="/spell-icons/'.$spell.'.png" width="24" height="24" style="filter:grayscale(1) contrast(1.1)"/><rect width="24" height="24" fill="'.$ink.'" style="mix-blend-mode:multiply"/></g></g>';
        }
        $frame = $stand ? '<rect x="1.2" y="1.2" width="21.6" height="21.6" rx="2.6" stroke-width="1" opacity="0.5"/>' : '';

        return '<g transform="'.$at.'" fill="none" stroke="'.$ink.'" stroke-width="'.($tier >= 4 ? 2 : 1.75).'" stroke-linecap="round" stroke-linejoin="round" opacity="'.$opacity.'">'.$frame.(Glyphs::PATHS[$glyph] ?? '').'</g>';
    }

    private static function dial(int $tier, float $progress): string
    {
        $lit = $tier === 5 ? 20 : (int) round($progress * 20);
        $out = '';
        for ($i = 0; $i < 20; $i++) {
            $a = deg2rad(222 + (276 / 19) * $i);
            $on = $i < $lit;
            $sx = sin($a);
            $cy = -cos($a);
            $out .= '<line class="tick" style="--i:'.$i.'" x1="'.round(32 + $sx * 27.4, 2).'" y1="'.round(32 + $cy * 27.4, 2).'" x2="'.round(32 + $sx * 31, 2).'" y2="'.round(32 + $cy * 31, 2).'" stroke="'.($on ? self::NEXT[$tier] : 'rgba(255,255,255,0.16)').'" stroke-width="'.($on ? 2.3 : 1.4).'"/>';
        }

        return $out;
    }

    private static function pips(int $tier): string
    {
        $out = '';
        for ($i = 0; $i < 5; $i++) {
            $a = deg2rad(201 - 10.5 * $i);
            $x = round(32 + sin($a) * 29.4, 2);
            $y = round(32 - cos($a) * 29.4, 2);
            $on = $i < $tier;
            $fill = $on ? ($tier === 5 ? self::GOLD : (self::METAL[$tier] ?? 'none')) : 'none';
            $out .= '<rect class="pip" style="--i:'.$i.'" x="'.round($x - 1.8, 2).'" y="'.round($y - 1.8, 2).'" width="3.6" height="3.6" transform="rotate(45 '.$x.' '.$y.')" fill="'.$fill.'" stroke="'.($on ? 'none' : self::DARK).'" stroke-width="0.8"/>';
        }

        return $out;
    }

    /** The five tier pips on their own, for a collection's tier. */
    public static function pipRow(int $tier): string
    {
        $name = $tier > 0 ? (self::TIER_NAMES[$tier] ?? '').' collection' : 'No tier yet';
        $out = '<svg viewBox="0 0 46 10" width="46" height="10" role="img" aria-label="'.$name.'">';
        for ($i = 0; $i < 5; $i++) {
            $on = $i < $tier;
            $fill = $on ? ($tier === 5 ? self::GOLD : (self::METAL[$tier] ?? 'none')) : 'none';
            $out .= '<rect x="'.(2.5 + $i * 9).'" y="2" width="6" height="6" transform="rotate(45 '.(5.5 + $i * 9).' 5)" fill="'.$fill.'" stroke="'.($on ? 'none' : self::DARK).'" stroke-width="1"/>';
        }

        return $out.'</svg>';
    }

    private static function plate(string $family): string
    {
        return match ($family) {
            'combat' => 'M12 11.5H52V31C52 42.5 43.5 50 32 54.5C20.5 50 12 42.5 12 31Z',
            'quests' => 'M13.5 10.5H50.5V54L32 45.5L13.5 54Z',
            'dungeons' => 'M12 53.5V30.5A20 20 0 0 1 52 30.5V53.5Z',
            'world' => self::poly(4, 24, 0),
            'trade' => self::poly(6, 23.5, 30),
            'class' => self::poly(8, 23.5, 0, 18),
            'role' => self::poly(5, 24, 180),
            default => 'M32 10a22 22 0 1 0 0 44a22 22 0 1 0 0-44Z',
        };
    }

    private static function poly(int $n, float $r, float $start, float $inner = 0): string
    {
        $points = [];
        for ($i = 0; $i < $n; $i++) {
            $a = deg2rad($start + (360 / $n) * $i);
            $points[] = round(32 + sin($a) * $r, 2).' '.round(32 - cos($a) * $r, 2);
            if ($inner) {
                $b = deg2rad($start + (360 / $n) * ($i + 0.5));
                $points[] = round(32 + sin($b) * $inner, 2).' '.round(32 - cos($b) * $inner, 2);
            }
        }

        return 'M'.implode('L', $points).'Z';
    }
}
