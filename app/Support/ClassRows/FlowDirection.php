<?php

namespace App\Support\ClassRows;

/** The `dir` of an `item_flows` row: into the bags or out of them. */
enum FlowDirection: int
{
    case In = 1;
    case Out = -1;
}
