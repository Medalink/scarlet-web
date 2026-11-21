<?php

namespace App\Actions\Upload;

use App\Models\Install;

final readonly class RegisterInstallResult
{
    public function __construct(public Install $install, public bool $created) {}
}
