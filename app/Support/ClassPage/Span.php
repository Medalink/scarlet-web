<?php

namespace App\Support\ClassPage;

/** How many of the sheet's twelve columns a module takes; a row of three modules uses the third. */
enum Span: string
{
    case Third = '';
    case W5 = 'w5';
    case W7 = 'w7';
    case W8 = 'w8';
    case W12 = 'w12';
}
