<?php

use Illuminate\Support\Facades\Schedule;

// The Forever Armory names the ruleset of a character it holds; a new character is looked up within ten minutes.
Schedule::command('ingest:sweep')->everyFiveMinutes()->withoutOverlapping()->onOneServer();
Schedule::command('horizon:snapshot')->everyFiveMinutes()->onOneServer();
Schedule::command('rulesets:detect')->everyTenMinutes()->withoutOverlapping()->onOneServer();
Schedule::command('search:scores')->hourly()->withoutOverlapping()->onOneServer();
// A safety net: sessions that sit under another ruleset than their character's are decoded again.
Schedule::command('rulesets:refile')->dailyAt('04:30')->withoutOverlapping()->onOneServer();
