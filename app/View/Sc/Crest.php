<?php

namespace App\View\Sc;

use App\Support\Feat;

/** The feat crest of design/crests/CRESTS.md as one inline SVG, laid out layer by layer as `crest(el)` in crests.js lays it out. */
final class Crest
{
    public const array TIER_NAMES = ['', ...Feat::TIERS];

    public const array SCOPES = ['character', 'class', 'account'];

    private const array SHIELD = ['character' => 'shield-character', 'class' => 'shield-class', 'account' => 'shield-account'];

    private const array SIDE = ['character' => 'wings', 'class' => 'spurs', 'account' => 'laurels'];

    private const array GLYPH_Y = ['character' => 45.5, 'class' => 47, 'account' => 47];

    private const array ATTACHES = [null, 'shield', 'crown', 'banner', 'side', 'rays'];

    private const string INK = '#141416';

    private const string LIGHT = '#f0f1f3';

    /**
     * A material is lit from above: `top` and `bottom` are the ends of a vertical gradient across a piece's box, `flat` the tint of a
     * ghost. Mirrors `MATERIAL` in design/crests/crests.js.
     *
     * @var array<string, array{top: string, bottom: string, flat: string}>
     */
    public const array MATERIAL = [
        'steel' => ['top' => '#e6e9ef', 'bottom' => '#767c88', 'flat' => '#aeb4bf'],
        'pewter' => ['top' => '#a9aeb9', 'bottom' => '#4b4f58', 'flat' => '#787d89'],
        'parchment' => ['top' => '#f3ead6', 'bottom' => '#c2b391', 'flat' => '#ddd0b3'],
        'gold' => ['top' => '#ffeaa6', 'bottom' => '#b8872a', 'flat' => '#e8c060'],
    ];

    /**
     * The tier table, by tier (0 is not earned): `color` is the accent (the field's top, the inlay, the band, the pips); `deep` the foot of
     * the field; `frame`, `crown`, `banner`, `side` and `rays` the MATERIAL each piece is made of; `field` the field's opacity (1 is the
     * solid field inside a dark gap); `plain` the field's opacity under 22 px; `glyph` the glyph's stroke; `gem` the gem's colour; `ghost`
     * and `near` the opacity of a ghost drawn in this tier's colour at rest and from NEAR_AT. Mirrors `QUALITY` in design/crests/crests.js.
     *
     * @var array<int, array{color: string, deep: string|null, frame: string|null, field: float|int, plain: float|int, glyph: string, crown: string|null, banner: string|null, side: string|null, rays: string|null, gem: string|null, ghost: float|int, near: float|int}>
     */
    public const array QUALITY = [
        ['color' => '#9d9d9d', 'deep' => null, 'frame' => null, 'field' => 0, 'plain' => 0, 'glyph' => '#9d9d9d', 'crown' => null, 'banner' => null, 'side' => null, 'rays' => null, 'gem' => null, 'ghost' => 0, 'near' => 0],
        ['color' => '#1eff00', 'deep' => '#14b800', 'frame' => 'steel', 'field' => 0.22, 'plain' => 0.55, 'glyph' => self::LIGHT, 'crown' => null, 'banner' => null, 'side' => null, 'rays' => null, 'gem' => null, 'ghost' => 0.12, 'near' => 0.3],
        ['color' => '#0070dd', 'deep' => '#004f9e', 'frame' => 'steel', 'field' => 0.45, 'plain' => 1, 'glyph' => self::LIGHT, 'crown' => 'steel', 'banner' => null, 'side' => null, 'rays' => null, 'gem' => '#0070dd', 'ghost' => 0.3, 'near' => 0.55],
        ['color' => '#a335ee', 'deep' => '#7220b0', 'frame' => 'steel', 'field' => 0.45, 'plain' => 1, 'glyph' => self::LIGHT, 'crown' => 'steel', 'banner' => 'parchment', 'side' => null, 'rays' => null, 'gem' => '#a335ee', 'ghost' => 0.3, 'near' => 0.55],
        ['color' => '#ff8000', 'deep' => '#c75a00', 'frame' => 'steel', 'field' => 1, 'plain' => 1, 'glyph' => self::INK, 'crown' => 'steel', 'banner' => 'parchment', 'side' => 'pewter', 'rays' => null, 'gem' => '#ff8000', 'ghost' => 0.16, 'near' => 0.36],
        ['color' => '#ff2d55', 'deep' => '#c4123a', 'frame' => 'gold', 'field' => 1, 'plain' => 1, 'glyph' => self::INK, 'crown' => 'gold', 'banner' => 'parchment', 'side' => 'pewter', 'rays' => 'gold', 'gem' => '#ff2d55', 'ghost' => 0.14, 'near' => 0.34],
    ];

    private const array BAND = [15, 69.9, 81, 73.1];

    private const array GEM_AT = [48, 24.6];

    private const float GEM_SETTING = 1.5;

    private const float NEAR_AT = 0.9;

    private const float UNEARNED_FILL = 0.3;

    private const float UNEARNED_RIM = 0.5;

    private const float UNEARNED_GLYPH = 0.75;

    private const int CX = 48;

    private const int CY = 50;

    private const float GLYPH = 28;

    private static int $made = 0;

    private static ?string $salt = null;

    /** @var list<string> */
    private array $defs = [];

    /** @var array<string, true> */
    private array $paints = [];

    private function __construct(
        private readonly string $id,
        private readonly string $scope,
        private readonly int $tier,
        private readonly float $progress,
        private readonly bool $compact,
        private readonly bool $tiny,
        private readonly bool $outline,
        private readonly bool $earning,
    ) {}

    /** The shield of a feat: its own for a catalog key, else the class shield for the class and role families and the character shield for the rest. */
    public static function scopeOf(?string $key, ?string $family = null): string
    {
        $feat = $key === null ? null : Feat::tryFrom($key);
        if ($feat !== null) {
            return $feat->crest()->value;
        }

        return in_array($family, ['class', 'role'], true) ? 'class' : 'character';
    }

    /**
     * @param  string  $scope  character, class or account
     * @param  string  $state  `earned` plays the attach on the tier held; `next` draws the next tier with an empty field, for the nudge
     * @param  ?string  $icon  the address of a spell icon engraved in place of the glyph
     */
    public static function svg(string $glyph, string $scope, int $tier, float $progress, int $size, string $label = '', string $state = '', ?string $icon = null): string
    {
        $scope = in_array($scope, self::SCOPES, true) ? $scope : 'character';
        $held = max(0, min(5, $tier));
        $outline = $state === 'next';
        $drawn = $outline ? min(5, $held + 1) : $held;
        $p = max(0, min(1, $progress));
        self::$salt ??= bin2hex(random_bytes(3));
        $crest = new self('cr'.self::$salt.++self::$made, $scope, $drawn, $p, $size < 44, $size < 22, $outline, $state === 'earned');

        return '<svg viewBox="'.($crest->compact ? '13 8.5 70 70' : '6 5 84 84').'" width="'.$size.'" height="'.$size.'" role="img" aria-label="'.e($label).'">'.$crest->layers($glyph, $icon).'</svg>';
    }

    /** The five tier pips on their own, for a collection's tier. */
    public static function pips(int $tier): string
    {
        $name = $tier > 0 ? (self::TIER_NAMES[$tier] ?? '').' collection' : 'No tier yet';
        $color = (self::QUALITY[$tier] ?? self::QUALITY[0])['color'];
        $off = self::QUALITY[0]['color'];
        $out = '<svg viewBox="0 0 46 10" width="46" height="10" role="img" aria-label="'.$name.'">';
        for ($i = 0; $i < 5; $i++) {
            $on = $i < $tier;
            $out .= '<rect x="'.(2.5 + $i * 9).'" y="2" width="6" height="6" transform="rotate(45 '.(5.5 + $i * 9).' 5)" fill="'.($on ? $color : 'none').'" stroke="'.($on ? 'none' : $off).'" stroke-opacity="'.self::UNEARNED_RIM.'" stroke-width="1"/>';
        }

        return $out.'</svg>';
    }

    private function layers(string $glyph, ?string $icon): string
    {
        $tier = $this->tier;
        $side = self::SIDE[$this->scope];
        $q = self::QUALITY[$tier];
        $next = self::QUALITY[$tier + 1] ?? null;
        $want = $tier < 5 && ! $this->tiny && ! $this->outline ? self::ATTACHES[$tier + 1] : null;
        $hidden = $this->compact && ($want === 'side' || $want === 'rays');
        $out = '';
        if ($tier >= 4 && ! $this->compact) {
            $out .= $this->group('side', $this->path($side, ['fill' => $this->made((string) $q['side'])]).$this->flash($side));
        }
        if ($next !== null && $want === 'side' && ! $hidden) {
            $out .= $this->group('side ghost', $this->ghost($side, $next['color'], false, $next));
        }
        if ($tier === 5 && ! $this->compact) {
            $out .= $this->group('rays', $this->backed('rays', ['fill' => $this->made((string) $q['rays'])]).$this->flash('rays'));
        }
        if ($next !== null && $want === 'rays' && ! $hidden) {
            $out .= $this->group('rays ghost', $this->ghost('rays', self::MATERIAL['gold']['flat'], true, $next));
        }
        $out .= $this->group('shield', $this->shield($q, $next));
        if (! $this->tiny) {
            $out .= $this->glyph($glyph, $icon, $q);
        }
        if ($tier >= 3) {
            $clip = $this->id.'band';
            [$x0, $y0, $x1, $y1] = self::BAND;
            $this->defs[] = '<clipPath id="'.$clip.'"><rect x="'.self::n($x0).'" y="'.self::n($y0).'" width="'.self::n($x1 - $x0).'" height="'.self::n($y1 - $y0).'"/></clipPath>';
            $out .= $this->group('banner', $this->backed('banner', ['fill' => $this->made((string) $q['banner'])]).$this->path('banner', ['fill' => $q['color'], 'clip-path' => 'url(#'.$clip.')']).$this->flash('banner'));
        }
        if ($next !== null && $want === 'banner') {
            $out .= $this->group('banner ghost', $this->ghost('banner', $next['color'], true, $next));
        }
        if ($tier >= 2) {
            $key = $tier === 5 ? 'great-crown' : 'crown';
            $setting = 'translate('.self::GEM_AT[0].' '.self::GEM_AT[1].') scale('.self::GEM_SETTING.') translate('.-self::GEM_AT[0].' '.-self::GEM_AT[1].')';
            $out .= $this->group('crown', $this->backed($key, ['fill' => $this->made((string) $q['crown'])]).$this->path('gem', ['fill' => self::INK, 'transform' => $setting]).$this->path('gem', ['fill' => $q['gem']]).$this->flash($key));
        }
        if ($next !== null && $want === 'crown') {
            $out .= $this->group('crown ghost', $this->ghost('crown', $next['color'], true, $next));
        }

        return ($this->defs === [] ? '' : '<defs>'.implode('', $this->defs).'</defs>').$out;
    }

    /**
     * @param  array<string, mixed>  $q
     * @param  array<string, mixed>|null  $next
     */
    private function shield(array $q, ?array $next): string
    {
        $shield = self::SHIELD[$this->scope];
        $ring = $this->scope === 'account' ? 'ring-account' : null;
        $color = $q['color'];
        $out = '';
        if ($this->tier === 0) {
            if (! $this->tiny && $next !== null) {
                $out .= $this->ghost($shield, $next['color'], false, ['ghost' => 0, 'near' => $next['near']], self::UNEARNED_FILL);
            }
            $out .= $this->path($shield, ['fill' => 'none', 'stroke' => $color, 'stroke-opacity' => self::UNEARNED_RIM, 'stroke-width' => $this->compact ? 2.8 : 2]);
            if ($ring !== null) {
                $out .= $this->path($ring, ['fill' => $color, 'fill-opacity' => self::UNEARNED_RIM]);
            }

            return $out;
        }
        $rim = $this->made((string) $q['frame']);
        $solid = $q['field'] == 1 || $this->tiny;
        $inset = $this->compact ? 0.86 : 0.9;
        $inner = $solid ? ($this->compact ? 0.8 : 0.84) : ($this->compact ? 0.78 : 0.85);
        if (! $this->compact) {
            $out .= $this->path($shield, ['fill' => self::INK, 'transform' => $this->about($ring !== null ? 1.3 : 1.09)]);
        }
        if ($ring !== null) {
            $out .= $this->path($ring, ['fill' => $rim]);
        }
        $out .= $this->path($shield, ['fill' => $rim]);
        $out .= $this->path($shield, ['fill' => $solid ? self::INK : $color, 'transform' => $this->about($inset)]);
        if (! $solid) {
            $out .= $this->path($shield, ['fill' => self::INK, 'transform' => $this->about($inner)]);
        }
        if (! $this->outline) {
            $out .= $this->path($shield, ['fill' => $this->gradient('enamel', $color, (string) $q['deep']), 'fill-opacity' => $this->tiny ? $q['plain'] : $q['field'], 'transform' => $this->about($inner)]);
        }

        return $out.$this->flash($shield);
    }

    /** @param  array<string, mixed>  $q */
    private function glyph(string $glyph, ?string $icon, array $q): string
    {
        $tier = $this->tier;
        $ink = $this->outline ? $q['color'] : $q['glyph'];
        $scale = ($this->compact ? 1.12 : 1) * (self::GLYPH / 24);
        $y = self::GLYPH_Y[$this->scope] + ($this->compact && $tier < 3 ? 1.5 : 0);
        $attrs = [
            'class' => 'cr-glyph', 'transform' => 'translate('.self::n(self::CX - 12 * $scale).' '.self::n($y - 12 * $scale).') scale('.self::n($scale).')',
            'fill' => 'none', 'stroke' => $ink, 'stroke-width' => $q['field'] == 1 && ! $this->outline ? 2.1 : ($this->compact ? 2 : 1.8),
            'stroke-linecap' => 'round', 'stroke-linejoin' => 'round', 'opacity' => $tier ? 1 : self::UNEARNED_GLYPH,
        ];
        if ($icon !== null) {
            $clip = $this->id.'icon';
            $this->defs[] = '<clipPath id="'.$clip.'"><rect x="2" y="2" width="20" height="20" rx="2.4"/></clipPath>';
            $inner = '<image href="'.e($icon).'" x="2" y="2" width="20" height="20" clip-path="url(#'.$clip.')" style="filter:grayscale(1) contrast(1.1)"/>'
                .'<rect x="2" y="2" width="20" height="20" rx="2.4" fill="'.$q['glyph'].'" stroke="none" style="mix-blend-mode:multiply"/>';

            return '<g'.self::attrs($attrs).'>'.$inner.'</g>';
        }
        $frame = str_starts_with($glyph, 'm-') ? '<rect x="1.2" y="1.2" width="21.6" height="21.6" rx="2.6" stroke-width="1" opacity="0.5"/>' : '';

        return '<g'.self::attrs($attrs).'>'.$frame.(Glyphs::PATHS[$glyph] ?? '').'</g>';
    }

    /** @param  array<string, mixed>  $of  the tier whose `ghost` and `near` opacity the ghost takes */
    private function ghost(string $key, ?string $color, bool $back, array $of, float $strength = 1): string
    {
        $piece = CrestPieces::PIECES[$key];
        [$x0, $y0, $x1, $y1] = $piece['box'];
        $p = $this->progress;
        $rect = match ($piece['fill']) {
            'up' => [$x0, $y1 - ($y1 - $y0) * $p, $x1 - $x0, ($y1 - $y0) * $p],
            'right' => [$x0, $y0, ($x1 - $x0) * $p, $y1 - $y0],
            default => [self::CX - 24 - (self::CX - 24 - $x0) * $p, $y0, 2 * (24 + (self::CX - 24 - $x0) * $p), $y1 - $y0],
        };
        $clip = $this->id.$key;
        $this->defs[] = '<clipPath id="'.$clip.'"><rect x="'.self::n($rect[0]).'" y="'.self::n($rect[1]).'" width="'.self::n($rect[2]).'" height="'.self::n($rect[3]).'"/></clipPath>';
        $dim = $p >= self::NEAR_AT ? $of['near'] : $of['ghost'];
        $out = $back ? $this->path($key, ['fill' => self::INK, 'stroke' => self::INK, 'stroke-width' => 3.6, 'stroke-linejoin' => 'round']) : '';

        return $out.$this->path($key, ['fill' => $color, 'opacity' => $dim]).$this->path($key, ['fill' => $color, 'opacity' => $strength, 'clip-path' => 'url(#'.$clip.')', 'class' => 'cr-fill']);
    }

    /** The paint of a MATERIAL: a vertical gradient from its `top` to its `bottom`, defined once for the crest. */
    private function made(string $material): string
    {
        return $this->gradient($material, self::MATERIAL[$material]['top'], self::MATERIAL[$material]['bottom']);
    }

    private function gradient(string $name, string $top, string $bottom): string
    {
        $id = $this->id.$name;
        if (! isset($this->paints[$name])) {
            $this->paints[$name] = true;
            $this->defs[] = '<linearGradient id="'.$id.'" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="'.$top.'"/><stop offset="1" stop-color="'.$bottom.'"/></linearGradient>';
        }

        return 'url(#'.$id.')';
    }

    /** @param  array<string, string|int|float|null>  $attrs */
    private function backed(string $key, array $attrs): string
    {
        return $this->path($key, ['fill' => self::INK, 'stroke' => self::INK, 'stroke-width' => $this->compact ? 4.4 : 3.6, 'stroke-linejoin' => 'round']).$this->path($key, $attrs);
    }

    private function flash(string $key): string
    {
        return $this->earning ? $this->path($key, ['fill' => '#fff', 'class' => 'cr-flash', 'opacity' => 0]) : '';
    }

    private function group(string $slot, string $kids): string
    {
        $new = $this->earning && self::ATTACHES[$this->tier] === $slot ? ' cr-new' : '';

        return '<g class="cr-p cr-'.$slot.$new.'">'.$kids.'</g>';
    }

    /** @param  array<string, string|int|float|null>  $attrs */
    private function path(string $key, array $attrs): string
    {
        return '<path d="'.CrestPieces::PIECES[$key]['d'].'"'.self::attrs($attrs).'/>';
    }

    private function about(float $k): string
    {
        return 'translate('.self::CX.' '.self::CY.') scale('.$k.') translate('.-self::CX.' '.-self::CY.')';
    }

    /** @param  array<string, string|int|float|null>  $attrs */
    private static function attrs(array $attrs): string
    {
        $out = '';
        foreach ($attrs as $name => $value) {
            if ($value !== null) {
                $out .= ' '.$name.'="'.(is_float($value) ? self::n($value) : $value).'"';
            }
        }

        return $out;
    }

    private static function n(float|int $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 3, '.', ''), '0'), '.');
    }
}
