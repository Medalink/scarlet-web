<?php

namespace App\Http\Requests\Upload;

/** GET /api/v1/installs/me/class-page/{character?}: all of the install's class pages, or the one character named in the path. */
class ClassPageRequest extends UploadRequest
{
    /** The route's constraint on `{character}`, the pattern of an upload's character name. */
    public const string NAME_PATTERN = '[^\s\-\/\\\\]{1,40}-[^\s\-\/\\\\]{1,40}';

    public function character(): ?string
    {
        $name = $this->route('character');

        return is_string($name) ? $name : null;
    }
}
