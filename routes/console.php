<?php

use Illuminate\Support\Facades\Schedule;

// The Forever Armory names the ruleset of a character it holds; a new character is looked up within ten minutes.
Schedule::command('ingest:sweep')->everyFiveMinutes()->withoutOverlapping()->onOneServer();
Schedule::command('horizon:snapshot')->everyFiveMinutes()->onOneServer();
Schedule::command('rulesets:detect')->everyTenMinutes()->withoutOverlapping()->onOneServer();
Schedule::command('search:scores')->hourly()->withoutOverlapping()->onOneServer();
Schedule::command('prices:pull')->everyFifteenMinutes()->withoutOverlapping()->onOneServer();
// A safety net: sessions that sit under another ruleset than their character's are decoded again.
Schedule::command('rulesets:refile')->dailyAt('04:30')->withoutOverlapping()->onOneServer();
Schedule::command('api-calls:prune')->dailyAt('03:50')->withoutOverlapping()->onOneServer();
Schedule::command('feats:refresh')->dailyAt('04:10')->withoutOverlapping()->onOneServer();
Schedule::command('loot:rebuild-keyed')->dailyAt('04:20')->withoutOverlapping()->onOneServer();
Schedule::command('ingest:redecode --outdated')->everyTenMinutes()->withoutOverlapping()->onOneServer();
Schedule::command('sessions:rates')->dailyAt('04:40')->withoutOverlapping()->onOneServer();
Schedule::command('feats:shares')->dailyAt('04:50')->withoutOverlapping()->onOneServer();
