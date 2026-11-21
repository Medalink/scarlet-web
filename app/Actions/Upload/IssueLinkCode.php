<?php

namespace App\Actions\Upload;

use App\Models\Install;
use App\Models\LinkCode;
use App\Support\Database\BinaryValue;
use App\Support\Exceptions\ApiException;
use App\Support\LinkCodeHash;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class IssueLinkCode
{
    /** Letters and digits without the pairs that look alike (0 O, 1 I L). */
    private const string ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    private const int GROUP = 4;

    /**
     * Make the code the app shows ("XXXX-XXXX"); a person signed in on the web confirms it and the install
     * becomes theirs. Only the hash is stored. A new code replaces the unused ones of the install.
     */
    public function handle(Install $install): IssueLinkCodeResult
    {
        if ($install->account_id !== null) {
            throw ApiException::alreadyLinked();
        }

        $code = $this->code();
        $expires = CarbonImmutable::now()->addMinutes((int) config('scarlet.upload.link_code_minutes'));

        DB::transaction(function () use ($install, $code, $expires): void {
            LinkCode::query()->where('install_id', $install->id)->whereNull('used_at')->delete();
            LinkCode::query()->create([
                'install_id' => $install->id,
                'code_hash' => BinaryValue::bind(LinkCodeHash::of($code)),
                'expires_at' => $expires,
            ]);
        });

        return new IssueLinkCodeResult($code, $expires);
    }

    private function code(): string
    {
        $characters = '';
        for ($i = 0; $i < self::GROUP * 2; $i++) {
            $characters .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return substr($characters, 0, self::GROUP).'-'.substr($characters, self::GROUP);
    }
}
