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
     * so a retry after a lost answer is safe.
     */
    public function handle(string $publicKey, ?string $name): RegisterInstallResult
    {
        $existing = $this->find($publicKey);
        if ($existing !== null) {
            return $this->result($existing, created: false);
        }

        try {
            $install = Install::query()->create(['public_key' => BinaryValue::bind($publicKey), 'name' => $name]);
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
