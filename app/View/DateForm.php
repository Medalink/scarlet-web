<?php

namespace App\View;

/** The forms a date takes on a page; the value is the `data-when` the browser script reads (resources/js/local-time.js). */
enum DateForm: string
{
    case Moment = 'moment';
    case Day = 'day';
    case Weekday = 'weekday';
    case Clock = 'clock';
    case ClockSeconds = 'clock-seconds';
    case DayTime = 'daytime';
}
