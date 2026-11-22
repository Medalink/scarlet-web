<?php

namespace App\Support;

/** The kind of instance a play span or dungeon run was inside (`instance_type`, docs/ROWS.md). */
enum InstanceType: string
{
    case Party = 'party';
    case Raid = 'raid';
}
