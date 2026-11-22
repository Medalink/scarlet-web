{{-- One glyph of the site's icon set: 24 units, drawn with one round stroke, coloured by the text around it.
     name: characters, level, hours, kills, deaths, quests, dungeons, gold, zones, distance, skills, fish, gather,
     crown, shield, clock, pin, talents, creature, item. --}}
@props(['name'])
@php
    $glyphs = [
        'characters' => '<circle cx="12" cy="8" r="3.6"/><path d="M4.8 20c.7-4 3.5-6.1 7.2-6.1s6.5 2.1 7.2 6.1"/>',
        'level' => '<path d="M2.5 20 9 9.5l3.6 5.2 2.4-3.4L21.5 20z"/><path d="M9 9.5V3.6l4.2 1.6L9 6.9"/>',
        'hours' => '<path d="M7 3.5h10M7 20.5h10M8.2 3.5v3.6c0 2.2 3.8 3.1 3.8 4.9s-3.8 2.7-3.8 4.9v3.6M15.8 3.5v3.6c0 2.2-3.8 3.1-3.8 4.9s3.8 2.7 3.8 4.9v3.6"/>',
        'kills' => '<path d="m4 4 10 10m-2.2 2.2 4.4-4.4M15 15l4.5 4.5M20 4 10 14m2.2 2.2-4.4-4.4M9 15l-4.5 4.5"/>',
        'deaths' => '<path d="M12 3.2a7.3 7.3 0 0 0-7.3 7.3c0 2.5 1.2 4.4 2.8 5.6v2.7a1 1 0 0 0 1 1h7a1 1 0 0 0 1-1v-2.7c1.6-1.2 2.8-3.1 2.8-5.6A7.3 7.3 0 0 0 12 3.2z"/><circle cx="9.2" cy="11.2" r="1.5"/><circle cx="14.8" cy="11.2" r="1.5"/><path d="M10.4 19.8v-2.2m3.2 2.2v-2.2"/>',
        'quests' => '<path d="M6 3.8h11.2v13.6a2.8 2.8 0 0 0 2.8 2.8H8.8A2.8 2.8 0 0 1 6 17.4z"/><path d="M9.6 8.4h4.2M9.6 12h4.2"/>',
        'dungeons' => '<path d="M4.8 20.4v-9.2a7.2 7.2 0 0 1 14.4 0v9.2M3.2 20.4h17.6M9.6 20.4v-8.6m4.8 8.6v-8.6M12 20.4V8.2"/>',
        'gold' => '<circle cx="12" cy="12" r="8.4"/><circle cx="12" cy="12" r="4.4"/>',
        'zones' => '<circle cx="12" cy="12" r="8.6"/><path d="m15.6 8.4-2.1 5.1-5.1 2.1 2.1-5.1z"/>',
        'distance' => '<circle cx="6" cy="18" r="2.1"/><circle cx="18" cy="6" r="2.1"/><path d="M8.1 18H15a3 3 0 0 0 0-6H9a3 3 0 0 1 0-6h6.9"/>',
        'skills' => '<path d="m14.2 3.8 6 6-2.6 2.6-6-6zM12.7 8.7 4 17.4l2.6 2.6 8.7-8.7"/>',
        'fish' => '<path d="M3 12c3.1-4.6 9.2-5.6 13.2-2.3L20.4 7v10l-4.2-2.7C12.2 17.6 6.1 16.6 3 12z"/><path d="M7.8 11.2h.01"/>',
        'gather' => '<path d="M5 19c0-8.2 5.2-13.2 14.2-14.2C19.2 14 14 19 5 19zm0 0 8.4-8.4"/>',
        'crown' => '<path d="M4 18.6h16M4.6 18.6 3.4 8l5 4.2L12 5l3.6 7.2 5-4.2-1.2 10.6"/>',
        'shield' => '<path d="m12 3 7.2 3v5.6c0 4.3-3 7.6-7.2 9.4-4.2-1.8-7.2-5.1-7.2-9.4V6z"/>',
        'clock' => '<circle cx="12" cy="12" r="8.6"/><path d="M12 7v5.2l3.4 2"/>',
        'pin' => '<path d="M12 21s6.6-6.3 6.6-11.1a6.6 6.6 0 0 0-13.2 0C5.4 14.7 12 21 12 21z"/><circle cx="12" cy="9.9" r="2.3"/>',
        'creature' => '<path d="M6.5 13.2a5.5 5.5 0 0 1 11 0c0 3.7-2.4 6.6-5.5 6.6s-5.5-2.9-5.5-6.6z"/><path d="M7.6 10.2 4.8 4.6m11.6 5.6 2.8-5.6M10 13.6h.01m3.98 0H14"/>',
        'item' => '<path d="M8 8.6h8l2.2 9.1a2 2 0 0 1-2 2.5H7.8a2 2 0 0 1-2-2.5z"/><path d="M9 8.6 7.9 4.8h8.2L15 8.6M9.6 12.4h4.8"/>',
        'talents' => '<circle cx="12" cy="5" r="2.2"/><circle cx="6" cy="18.6" r="2.2"/><circle cx="18" cy="18.6" r="2.2"/><path d="M12 7.2v4.4m0 0-4.9 5.2M12 11.6l4.9 5.2"/>',
    ];
@endphp
<svg {{ $attributes->class('ico') }} viewBox="0 0 24 24" aria-hidden="true">{!! $glyphs[$name] ?? '' !!}</svg>
