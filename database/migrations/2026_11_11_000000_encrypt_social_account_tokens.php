<?php

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Criptografa os tokens já gravados em texto puro (specs/002 VULN-SA-01), no mesmo formato do
 * cast `encrypted` do SocialAccount. Idempotente: valor que já decripta é mantido.
 */
return new class extends Migration
{
    private const COLUMNS = ['access_token', 'refresh_token'];

    public function up(): void
    {
        $this->transform(function (string $value): string {
            return $this->isEncrypted($value) ? $value : Crypt::encryptString($value);
        });
    }

    public function down(): void
    {
        $this->transform(function (string $value): string {
            return $this->isEncrypted($value) ? Crypt::decryptString($value) : $value;
        });
    }

    private function transform(callable $fn): void
    {
        DB::table('social_accounts')->orderBy('id')->chunkById(200, function ($rows) use ($fn) {
            foreach ($rows as $row) {
                $changes = [];

                foreach (self::COLUMNS as $column) {
                    if ($row->{$column} !== null && $row->{$column} !== '') {
                        $new = $fn($row->{$column});

                        if ($new !== $row->{$column}) {
                            $changes[$column] = $new;
                        }
                    }
                }

                if ($changes !== []) {
                    DB::table('social_accounts')->where('id', $row->id)->update($changes);
                }
            }
        });
    }

    private function isEncrypted(string $value): bool
    {
        try {
            Crypt::decryptString($value);

            return true;
        } catch (DecryptException) {
            return false;
        }
    }
};
