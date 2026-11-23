<?php

namespace App\Actions\Upload;

use App\Models\Install;
use App\Support\Database\BinaryValue;
use App\Support\Exceptions\ApiException;
use Illuminate\Database\UniqueConstraintViolationException;

class RegisterInstall
{
    /**
     * Register an Ed25519 public key (32 bytes). Registering a key again answers the install it has,
     * refreshes the origin it registered on, and is safe to retry after a lost answer.
     */
    public function handle(string $publicKey, ?string $name, string $origin): RegisterInstallResult
    {
        $existing = $this->find($publicKey);
        if ($existing !== null) {
            if ($existing->origin !== $origin) {
                $existing->forceFill(['origin' => $origin])->save();
            }

            return $this->result($existing, created: false);
        }

        try {
            $install = Install::query()->create(['public_key' => BinaryValue::bind($publicKey), 'name' => $name, 'origin' => $origin]);
        } catch (UniqueConstraintViolationException) {
            return $this->result($this->find($publicKey) ?? throw ApiException::unknownInstall(), created: false);
        }

        return new RegisterInstallResult($install, created: true);
    }

    private function find(string $publicKey): ?Install
    {
        return Install::query()->whereRaw('public_key = ?', [BinaryValue::bind($publicKey)])->first();
    }

    private function result(Install $install, bool $created): RegisterInstallResult
    {
        if ($install->isRevoked()) {
            throw ApiException::installRevoked();
        }

        return new RegisterInstallResult($install, $created);
    }
}
