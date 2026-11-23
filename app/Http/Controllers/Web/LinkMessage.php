<?php

namespace App\Http\Controllers\Web;

use App\Actions\Account\LinkState;

/** What /link says when a code cannot be used. */
final class LinkMessage
{
    public static function of(LinkState $state): string
    {
        return match ($state) {
            LinkState::Ready, LinkState::Linked => 'The app is linked to your account.',
            LinkState::Unknown => 'No app has asked for this code. Check it against the code in the app.',
            LinkState::Used => 'This code was already used. Ask the app for a new one.',
            LinkState::Expired => 'This code has expired. Ask the app for a new one.',
            LinkState::OtherAccount => 'This app is already linked to another account.',
            LinkState::AlreadyYours => 'This app is already linked to your account.',
            LinkState::Unsigned => 'This code does not match the link the app opened. Type the code the app shows, or ask it for a new one.',
        };
    }
}
