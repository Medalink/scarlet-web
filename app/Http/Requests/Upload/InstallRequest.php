<?php

namespace App\Http\Requests\Upload;

/** A signed request with nothing to validate beyond the install itself (status, link code, group switches, removals). */
class InstallRequest extends UploadRequest
{
    /** The group key of /upload/groups/{key}. */
    public function group(): string
    {
        return (string) $this->route('key');
    }
}
