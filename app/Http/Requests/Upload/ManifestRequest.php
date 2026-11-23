<?php

namespace App\Http\Requests\Upload;

use App\Support\Exceptions\ApiException;
use App\Support\Manifest;
use App\Support\PartTool;

/**
 * POST /api/v1/upload/manifest: one zstd frame of JSON, `{character, groups, parts: [{id, session, group,
 * lines, raw_bytes}]}` (the shape of the `manifest.json` that `scarlet-part pack` writes).
 */
class ManifestRequest extends UploadRequest
{
    public const string CONTENT_TYPE = 'application/vnd.scarlet.manifest+zstd';

    /** The manifest object, its groups list and its parts list, and room for a bracket inside a group name. */
    private const int ENVELOPE_CONTAINERS = 16;

    private ?Manifest $manifest = null;

    public function manifest(PartTool $tool): Manifest
    {
        if ($this->manifest !== null) {
            return $this->manifest;
        }
        if (! str_starts_with(strtolower((string) $this->header('Content-Type')), self::CONTENT_TYPE)) {
            throw ApiException::unsupportedType(self::CONTENT_TYPE);
        }

        $raw = $tool->inflate($this->getContent());
        $maxBytes = (int) config('scarlet.upload.max_manifest_bytes');
        if (strlen($raw) > $maxBytes) {
            throw ApiException::invalidRequest("The manifest is larger than {$maxBytes} bytes inflated.");
        }
        $maxContainers = (int) config('scarlet.upload.max_manifest_parts') + self::ENVELOPE_CONTAINERS;
        if (substr_count($raw, '{') + substr_count($raw, '[') > $maxContainers) {
            throw ApiException::invalidRequest("The manifest carries more than {$maxContainers} JSON objects and lists.");
        }

        $data = json_decode($raw, associative: true);
        unset($raw);
        if (! is_array($data)) {
            throw ApiException::invalidRequest('The manifest is not a JSON object.');
        }

        return $this->manifest = $this->parse($data);
    }

    /** @param  array<mixed>  $data */
    private function parse(array $data): Manifest
    {
        $character = $data['character'] ?? null;
        if (! is_string($character) || preg_match(self::CHARACTER_PATTERN, $character) !== 1) {
            throw ApiException::invalidRequest('character must be Name-Surname.', ['character' => ['Not a character name.']]);
        }

        $groups = $data['groups'] ?? null;
        if (! is_array($groups) || $groups === [] || count($groups) > 64 || array_filter($groups, fn ($group): bool => ! is_string($group)) !== []) {
            throw ApiException::invalidRequest('groups must list the group keys that are on.', ['groups' => ['A list of strings.']]);
        }

        $parts = $data['parts'] ?? null;
        $max = (int) config('scarlet.upload.max_manifest_parts');
        if (! is_array($parts) || count($parts) > $max) {
            throw ApiException::invalidRequest("parts must be a list of at most {$max} parts.", ['parts' => ['A list of parts.']]);
        }

        $entries = [];
        $perSession = [];
        $bytesPerSession = [];
        $maxParts = (int) config('scarlet.ingest.max_parts_per_session');
        $maxBytes = (int) config('scarlet.ingest.max_session_bytes');
        foreach ($parts as $index => $part) {
            if (! is_array($part)
                || ! is_string($part['id'] ?? null) || preg_match('/^[0-9a-f]{64}$/', $part['id']) !== 1
                || ! is_int($part['session'] ?? null) || $part['session'] < 0
                || ! is_string($part['group'] ?? null)
                || ! is_int($part['lines'] ?? null) || $part['lines'] < 0
                || ! is_int($part['raw_bytes'] ?? null) || $part['raw_bytes'] < 0) {
                throw ApiException::invalidRequest("parts[{$index}] needs id (64 lower-case hex), session, group, lines and raw_bytes.", ['parts.'.$index => ['Not a part.']]);
            }
            $perSession[$part['session']] = ($perSession[$part['session']] ?? 0) + 1;
            $bytesPerSession[$part['session']] = ($bytesPerSession[$part['session']] ?? 0) + $part['raw_bytes'];
            if ($perSession[$part['session']] > $maxParts || $bytesPerSession[$part['session']] > $maxBytes) {
                throw ApiException::sessionTooLarge('manifest parts', $maxParts);
            }
            $entries[$part['id']] = ['id' => $part['id'], 'session' => $part['session'], 'group' => $part['group'], 'lines' => $part['lines'], 'raw_bytes' => $part['raw_bytes']];
        }

        return new Manifest($character, array_values(array_unique($groups)), $entries);
    }
}
