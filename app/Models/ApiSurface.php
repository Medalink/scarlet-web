<?php

namespace App\Models;

/** The two halves of /api/v1 an `api_calls` row counts. */
enum ApiSurface: string
{
    case Public = 'public';
    case Upload = 'upload';
}
