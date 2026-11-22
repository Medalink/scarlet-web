<?php

namespace App\Support\ClassPage\Common;

use App\Support\ClassPage\Module;
use App\Support\ClassPage\Row;

/** The rows of a sheet, one for each line of modules that has at least one. */
final class Sheet
{
    /**
     * @param  list<list<Module|null>>  $lines
     * @return list<Row>
     */
    public static function rows(array $lines): array
    {
        $rows = [];
        foreach ($lines as $line) {
            $modules = array_values(array_filter($line));
            if ($modules !== []) {
                $rows[] = new Row($modules);
            }
        }

        return $rows;
    }
}
