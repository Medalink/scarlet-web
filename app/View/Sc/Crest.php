<?php

namespace App\View\Sc;

use App\Support\Feat;

/** The feat crest of design/crests/CRESTS.md as one inline SVG, laid out layer by layer as `crest(el)` in crests.js lays it out. */
final class Crest
{
    public const array TIER_NAMES = ['', ...Feat::TIERS];

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
        $scope = isset(CrestKit::SCOPES[$scope]) ? $scope : 'character';
        $held = max(0, min(5, $tier));
        $outline = $state === 'next';
        $drawn = $outline ? min(5, $held + 1) : $held;
        $p = max(0, min(1, $progress));
        self::$salt ??= bin2hex(random_bytes(3));
        $crest = new self('cr'.self::$salt.++self::$made, $scope, $drawn, $p, $size < 44, $size < 22, $outline, $state === 'earned');

        return '<svg viewBox="'.($crest->compact ? CrestKit::VIEW_COMPACT : CrestKit::VIEW_FULL).'" width="'.$size.'" height="'.$size.'" role="img" aria-label="'.e($label).'">'.$crest->layers($glyph, $icon).'</svg>';
    }

    private function layers(string $glyph, ?string $icon): string
    {
        $tier = $this->tier;
        $side = CrestKit::SCOPES[$this->scope]['side'];
        $q = CrestKit::QUALITY[$tier];
        $next = CrestKit::QUALITY[$tier + 1] ?? null;
        $want = $tier < 5 && ! $this->tiny && ! $this->outline ? CrestKit::ATTACHES[$tier + 1] : null;
        $hidden = $this->compact && ($want === 'side' || $want === 'rays');
        $out = '';
        if ($tier >= 4 && ! $this->compact) {
            $out .= $this->group('side', $this->path($side, ['fill' => $this->made((string) $q['side'])]).$this->flash($side));
        }
        if ($next !== null && $want === 'side' && ! $hidden) {
            $out .= $this->group('side ghost', $this->ghost($side, $next['color'], $next));
        }
        if ($tier === 5 && ! $this->compact) {
            $out .= $this->group('rays', $this->backed('rays', ['fill' => $this->made((string) $q['rays'])]).$this->flash('rays'));
        }
        if ($next !== null && $want === 'rays' && ! $hidden) {
            $out .= $this->group('rays ghost', $this->ghost('rays', CrestKit::MATERIAL['gold']['flat'], $next));
        }
        $out .= $this->group('shield', $this->shield($q, $next));
        if (! $this->tiny) {
            $out .= $this->glyph($glyph, $icon, $q);
        }
        if ($tier >= 3) {
            $clip = $this->id.'band';
            [$x0, $y0, $x1, $y1] = CrestKit::BAND;
            $this->defs[] = '<clipPath id="'.$clip.'"><rect x="'.self::n($x0).'" y="'.self::n($y0).'" width="'.self::n($x1 - $x0).'" height="'.self::n($y1 - $y0).'"/></clipPath>';
            $out .= $this->group('banner', $this->backed('banner', ['fill' => $this->made((string) $q['banner'])]).$this->path('banner', ['fill' => $q['color'], 'clip-path' => 'url(#'.$clip.')']).$this->flash('banner'));
        }
        if ($next !== null && $want === 'banner') {
            $out .= $this->group('banner ghost', $this->ghost('banner', $next['color'], $next));
        }
        if ($tier >= 2) {
            $key = $tier === 5 ? 'great-crown' : 'crown';
            $setting = 'translate('.CrestKit::GEM_AT[0].' '.CrestKit::GEM_AT[1].') scale('.CrestKit::GEM_SETTING.') translate('.-CrestKit::GEM_AT[0].' '.-CrestKit::GEM_AT[1].')';
            $out .= $this->group('crown', $this->backed($key, ['fill' => $this->made((string) $q['crown'])]).$this->path('gem', ['fill' => CrestKit::INK, 'transform' => $setting]).$this->path('gem', ['fill' => $q['gem']]).$this->flash($key));
        }
        if ($next !== null && $want === 'crown') {
            $out .= $this->group('crown ghost', $this->ghost('crown', $next['color'], $next));
        }

        return ($this->defs === [] ? '' : '<defs>'.implode('', $this->defs).'</defs>').$out;
    }

    /**
     * @param  array<string, mixed>  $q
     * @param  array<string, mixed>|null  $next
     */
    private function shield(array $q, ?array $next): string
    {
        $shield = CrestKit::SCOPES[$this->scope]['shield'];
        $ring = CrestKit::SCOPES[$this->scope]['ring'] ?? null;
        $color = $q['color'];
        $out = '';
        if ($this->tier === 0) {
            if (! $this->tiny && $next !== null) {
                $out .= $this->ghost($shield, $next['color'], ['ghost' => 0, 'near' => $next['near']], CrestKit::UNEARNED_FILL);
            }
            $out .= $this->path($shield, ['fill' => 'none', 'stroke' => $color, 'stroke-opacity' => CrestKit::UNEARNED_RIM, 'stroke-width' => $this->compact ? 2.8 : 2]);
            if ($ring !== null) {
                $out .= $this->path($ring, ['fill' => $color, 'fill-opacity' => CrestKit::UNEARNED_RIM]);
            }

            return $out;
        }
        $rim = $this->made((string) $q['frame']);
        $solid = $q['field'] == 1 || $this->tiny;
        $inset = $this->compact ? 0.86 : 0.9;
        $inner = $solid ? ($this->compact ? 0.8 : 0.84) : ($this->compact ? 0.78 : 0.85);
        if (! $this->compact) {
            $out .= $this->path($shield, ['fill' => CrestKit::INK, 'transform' => $this->about($ring !== null ? 1.3 : 1.09)]);
        }
        if ($ring !== null) {
            $out .= $this->path($ring, ['fill' => $rim]);
        }
        $out .= $this->path($shield, ['fill' => $rim]);
        $out .= $this->path($shield, ['fill' => $solid ? CrestKit::INK : $color, 'transform' => $this->about($inset)]);
        if (! $solid) {
            $out .= $this->path($shield, ['fill' => CrestKit::INK, 'transform' => $this->about($inner)]);
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
        $scale = ($this->compact ? 1.12 : 1) * (CrestKit::GLYPH / 24);
        $y = CrestKit::SCOPES[$this->scope]['glyphY'] + ($this->compact && $tier < 3 ? 1.5 : 0);
        $attrs = [
            'class' => 'cr-glyph', 'transform' => 'translate('.self::n(CrestKit::CX - 12 * $scale).' '.self::n($y - 12 * $scale).') scale('.self::n($scale).')',
            'fill' => 'none', 'stroke' => $ink, 'stroke-width' => $q['field'] == 1 && ! $this->outline ? 2.1 : ($this->compact ? 2 : 1.8),
            'stroke-linecap' => 'round', 'stroke-linejoin' => 'round', 'opacity' => $tier ? 1 : CrestKit::UNEARNED_GLYPH,
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
    private function ghost(string $key, ?string $color, array $of, float $strength = 1): string
    {
        $piece = CrestKit::PIECES[$key];
        [$x0, $y0, $x1, $y1] = $piece['box'];
        $p = $this->progress;
        $rect = match ($piece['fill']) {
            'up' => [$x0, $y1 - ($y1 - $y0) * $p, $x1 - $x0, ($y1 - $y0) * $p],
            'right' => [$x0, $y0, ($x1 - $x0) * $p, $y1 - $y0],
            default => [CrestKit::CX - 24 - (CrestKit::CX - 24 - $x0) * $p, $y0, 2 * (24 + (CrestKit::CX - 24 - $x0) * $p), $y1 - $y0],
        };
        $clip = $this->id.$key;
        $this->defs[] = '<clipPath id="'.$clip.'"><rect x="'.self::n($rect[0]).'" y="'.self::n($rect[1]).'" width="'.self::n($rect[2]).'" height="'.self::n($rect[3]).'"/></clipPath>';
        $dim = $p >= CrestKit::NEAR_AT ? $of['near'] : $of['ghost'];
        $out = ($piece['back'] ?? false) ? $this->path($key, ['fill' => CrestKit::INK, 'stroke' => CrestKit::INK, 'stroke-width' => 3.6, 'stroke-linejoin' => 'round']) : '';

        return $out.$this->path($key, ['fill' => $color, 'opacity' => $dim]).$this->path($key, ['fill' => $color, 'opacity' => $strength, 'clip-path' => 'url(#'.$clip.')', 'class' => 'cr-fill']);
    }

    /** The paint of a MATERIAL: a vertical gradient from its `top` to its `bottom`, defined once for the crest. */
    private function made(string $material): string
    {
        return $this->gradient($material, CrestKit::MATERIAL[$material]['top'], CrestKit::MATERIAL[$material]['bottom']);
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
        return $this->path($key, ['fill' => CrestKit::INK, 'stroke' => CrestKit::INK, 'stroke-width' => $this->compact ? 4.4 : 3.6, 'stroke-linejoin' => 'round']).$this->path($key, $attrs);
    }

    private function flash(string $key): string
    {
        return $this->earning ? $this->path($key, ['fill' => '#fff', 'class' => 'cr-flash', 'opacity' => 0]) : '';
    }

    private function group(string $slot, string $kids): string
    {
        $new = $this->earning && CrestKit::ATTACHES[$this->tier] === $slot ? ' cr-new' : '';

        return '<g class="cr-p cr-'.$slot.$new.'">'.$kids.'</g>';
    }

    /** @param  array<string, string|int|float|null>  $attrs */
    private function path(string $key, array $attrs): string
    {
        return '<path d="'.CrestKit::PIECES[$key]['d'].'"'.self::attrs($attrs).'/>';
    }

    private function about(float $k): string
    {
        return 'translate('.CrestKit::CX.' '.CrestKit::CY.') scale('.$k.') translate('.-CrestKit::CX.' '.-CrestKit::CY.')';
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
