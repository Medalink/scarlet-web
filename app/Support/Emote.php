<?php

namespace App\Support;

/** Emote tokens as `EM` lines carry them (Scarlet's docs/FORMAT.md). */
final class Emote
{
    /**
     * The pose the client plays while the world map is open. Scarlet before 7b066a1 recorded every map open as this
     * emote, and a stored row cannot tell those from a typed /read, so the emote rankings leave it out.
     */
    public const string READ = 'READ';
}
