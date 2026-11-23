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

    private const string COPPER = '#c58a63';

    private const string GOLD = '#d9b25f';

    private const string SCARLET = '#ff2d55';

    private const string DEEP = '#c0102e';

    private const string BLANK = 'rgba(255,255,255,0.22)';

    private const array METAL = [null, self::COPPER, '#c3c7cf', self::GOLD, self::SCARLET, self::GOLD];

    private const array FIELD = [0, 0.16, 0.2, 0.24, 1, 1];

    private const array SIDE_COLOR = [null, null, null, null, self::DEEP, self::GOLD];

    private const float GHOST = 0.14;

    private const float GHOST_NEAR = 0.34;

    private const float NEAR_AT = 0.9;

    private const float UNEARNED_FILL = 0.34;

    private const int CX = 48;

    private const int CY = 50;

    private const float GLYPH = 28;

    /** @var list<string> */
    private array $defs = [];

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
        $id = 'cr'.substr(md5(implode('|', [$glyph, $scope, $held, $p, $size, $state, $icon ?? ''])), 0, 8);
        $crest = new self($id, $scope, $drawn, $p, $size < 44, $size < 22, $outline, $state === 'earned');

        return '<svg viewBox="'.($crest->compact ? '13 8.5 70 70' : '6 5 84 84').'" width="'.$size.'" height="'.$size.'" role="img" aria-label="'.e($label).'">'.$crest->layers($glyph, $icon).'</svg>';
    }

    /** The five tier pips on their own, for a collection's tier. */
    public static function pips(int $tier): string
    {
        $name = $tier > 0 ? (self::TIER_NAMES[$tier] ?? '').' collection' : 'No tier yet';
        $out = '<svg viewBox="0 0 46 10" width="46" height="10" role="img" aria-label="'.$name.'">';
        for ($i = 0; $i < 5; $i++) {
            $on = $i < $tier;
            $fill = $on ? ($tier === 5 ? self::GOLD : (self::METAL[$tier] ?? 'none')) : 'none';
            $out .= '<rect x="'.(2.5 + $i * 9).'" y="2" width="6" height="6" transform="rotate(45 '.(5.5 + $i * 9).' 5)" fill="'.$fill.'" stroke="'.($on ? 'none' : self::BLANK).'" stroke-width="1"/>';
        }

        return $out.'</svg>';
    }

    private function layers(string $glyph, ?string $icon): string
    {
        $tier = $this->tier;
        $side = self::SIDE[$this->scope];
        $metal = self::METAL[$tier] ?? self::BLANK;
        $next = self::METAL[$tier + 1] ?? null;
        $want = $tier < 5 && ! $this->tiny && ! $this->outline ? self::ATTACHES[$tier + 1] : null;
        $hidden = $this->compact && ($want === 'side' || $want === 'rays');
        $lead = $tier === 5 ? self::GOLD : $metal;
        $out = '';
        if ($tier >= 4 && ! $this->compact) {
            $out .= $this->group('side', $this->path($side, ['fill' => self::SIDE_COLOR[$tier]]).$this->flash($side));
        }
        if ($want === 'side' && ! $hidden) {
            $out .= $this->group('side ghost', $this->ghost($side, $next, false));
        }
        if ($tier === 5 && ! $this->compact) {
            $out .= $this->group('rays', $this->backed('rays', ['fill' => self::SCARLET]).$this->flash('rays'));
        }
        if ($want === 'rays' && ! $hidden) {
            $out .= $this->group('rays ghost', $this->ghost('rays', self::GOLD, true));
        }
        $out .= $this->group('shield', $this->shield($metal));
        if (! $this->tiny) {
            $out .= $this->glyph($glyph, $icon, $metal);
        }
        if ($tier >= 3) {
            $out .= $this->group('banner', $this->backed('banner', ['fill' => $lead]).$this->flash('banner'));
        }
        if ($want === 'banner') {
            $out .= $this->group('banner ghost', $this->ghost('banner', $next, true));
        }
        if ($tier >= 2) {
            $key = $tier === 5 ? 'great-crown' : 'crown';
            $gem = $tier === 5 ? $this->path('gem', ['fill' => self::SCARLET, 'stroke' => self::INK, 'stroke-width' => 1.2]) : '';
            $out .= $this->group('crown', $this->backed($key, ['fill' => $lead]).$gem.$this->flash($key));
        }
        if ($want === 'crown') {
            $out .= $this->group('crown ghost', $this->ghost('crown', $next, true));
        }

        return ($this->defs === [] ? '' : '<defs>'.implode('', $this->defs).'</defs>').$out;
    }

    private function shield(string $metal): string
    {
        $tier = $this->tier;
        $shield = self::SHIELD[$this->scope];
        $ring = $this->scope === 'account' ? 'ring-account' : null;
        $out = '';
        if ($tier === 0) {
            if (! $this->tiny) {
                $out .= $this->ghost($shield, self::COPPER, false, self::UNEARNED_FILL);
            }
            $out .= $this->path($shield, ['fill' => 'none', 'stroke' => self::BLANK, 'stroke-width' => $this->compact ? 2.8 : 2]);
            if ($ring !== null) {
                $out .= $this->path($ring, ['fill' => self::BLANK]);
            }

            return $out;
        }
        $rim = $tier === 5 ? self::GOLD : $metal;
        if (! $this->compact) {
            $out .= $this->path($shield, ['fill' => self::INK, 'transform' => $this->about($ring !== null ? 1.3 : 1.09)]);
        }
        if ($ring !== null) {
            $out .= $this->path($ring, ['fill' => $rim]);
        }
        $out .= $this->path($shield, ['fill' => $rim]);
        $out .= $this->path($shield, ['fill' => self::INK, 'transform' => $this->about($this->compact ? 0.86 : 0.9)]);
        $solid = $tier >= 4 || $this->tiny;
        if (! $this->outline) {
            $scale = $solid ? ($this->compact ? 0.8 : 0.84) : ($this->compact ? 0.86 : 0.9);
            $out .= $this->path($shield, ['fill' => $tier >= 4 ? self::SCARLET : $metal, 'fill-opacity' => $solid ? 1 : (self::FIELD[$tier] ?? 0), 'transform' => $this->about($scale)]);
        }

        return $out.$this->flash($shield);
    }

    private function glyph(string $glyph, ?string $icon, string $metal): string
    {
        $tier = $this->tier;
        $ink = $this->outline ? ($tier === 5 ? self::GOLD : $metal) : ($tier >= 4 ? self::INK : ($tier ? $metal : '#8a8a93'));
        $scale = ($this->compact ? 1.12 : 1) * (self::GLYPH / 24);
        $y = self::GLYPH_Y[$this->scope] + ($this->compact && $tier < 3 ? 1.5 : 0);
        $attrs = [
            'class' => 'cr-glyph', 'transform' => 'translate('.self::n(self::CX - 12 * $scale).' '.self::n($y - 12 * $scale).') scale('.self::n($scale).')',
            'fill' => 'none', 'stroke' => $ink, 'stroke-width' => $tier >= 4 && ! $this->outline ? 2.1 : ($this->compact ? 2 : 1.8),
            'stroke-linecap' => 'round', 'stroke-linejoin' => 'round', 'opacity' => $tier ? 1 : 0.75,
        ];
        if ($icon !== null) {
            $clip = $this->id.'icon';
            $this->defs[] = '<clipPath id="'.$clip.'"><rect x="2" y="2" width="20" height="20" rx="2.4"/></clipPath>';
            $inner = '<image href="'.e($icon).'" x="2" y="2" width="20" height="20" clip-path="url(#'.$clip.')" style="filter:grayscale(1) contrast(1.1)"/>'
                .'<rect x="2" y="2" width="20" height="20" rx="2.4" fill="'.($tier >= 4 ? self::INK : $metal).'" stroke="none" style="mix-blend-mode:multiply"/>';

            return '<g'.self::attrs($attrs).'>'.$inner.'</g>';
        }
        $frame = str_starts_with($glyph, 'm-') ? '<rect x="1.2" y="1.2" width="21.6" height="21.6" rx="2.6" stroke-width="1" opacity="0.5"/>' : '';

        return '<g'.self::attrs($attrs).'>'.$frame.(Glyphs::PATHS[$glyph] ?? '').'</g>';
    }

    private function ghost(string $key, ?string $color, bool $back, float $strength = 1): string
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
        $dim = $p >= self::NEAR_AT ? self::GHOST_NEAR : self::GHOST;
        $out = $back ? $this->path($key, ['fill' => self::INK, 'stroke' => self::INK, 'stroke-width' => 3.6, 'stroke-linejoin' => 'round']) : '';

        return $out.$this->path($key, ['fill' => $color, 'opacity' => $dim]).$this->path($key, ['fill' => $color, 'opacity' => $strength, 'clip-path' => 'url(#'.$clip.')', 'class' => 'cr-fill']);
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
