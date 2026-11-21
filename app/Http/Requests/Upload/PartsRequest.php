<?php

namespace App\Http\Requests\Upload;

use App\Support\Exceptions\ApiException;

/** POST /api/v1/upload/parts: one zstd frame of length-prefixed parts. */
class PartsRequest extends UploadRequest
{
    public const string CONTENT_TYPE = 'application/vnd.scarlet.parts+zstd';

    /** The compressed body, after the content type was checked. */
    public function batch(): string
    {
        if (! str_starts_with(strtolower((string) $this->header('Content-Type')), self::CONTENT_TYPE)) {
            throw ApiException::unsupportedType(self::CONTENT_TYPE);
        }
        $body = $this->getContent();
        if ($body === '') {
            throw ApiException::invalidBatch('the body is empty');
        }

        return $body;
    }
}
