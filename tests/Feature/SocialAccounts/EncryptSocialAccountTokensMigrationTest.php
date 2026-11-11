<?php

use App\Models\SocialAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/*
| Migration que criptografa os tokens já gravados em texto puro (specs/002 VULN-SA-01).
| Contas conectadas antes do deploy precisam continuar funcionando.
*/

function encryptTokensMigration(): object
{
    return require database_path('migrations/2026_11_11_000000_encrypt_social_account_tokens.php');
}

describe('migration: criptografar tokens das contas sociais', function () {

    it('criptografa tokens em texto puro e o model continua lendo o valor original', function () {
        $user = createUserWithPermissions([]);
        $conta = connectedSocialAccount($user, 'youtube');
        DB::table('social_accounts')->where('id', $conta->id)->update([
            'access_token' => 'TOKEN-LEGADO', 'refresh_token' => 'REFRESH-LEGADO',
        ]);

        encryptTokensMigration()->up();

        $raw = DB::table('social_accounts')->where('id', $conta->id)->first();
        expect($raw->access_token)->not->toBe('TOKEN-LEGADO')
            ->and(Crypt::decryptString($raw->access_token))->toBe('TOKEN-LEGADO');

        $fresh = SocialAccount::withoutGlobalScopes()->find($conta->id);
        expect($fresh->access_token)->toBe('TOKEN-LEGADO')
            ->and($fresh->refresh_token)->toBe('REFRESH-LEGADO');
    });

    it('é idempotente: rodar de novo não criptografa duas vezes nem mexe em refresh_token nulo', function () {
        $user = createUserWithPermissions([]);
        $conta = connectedSocialAccount($user, 'tiktok', ['access_token' => 'JA-CIFRADO', 'refresh_token' => null]);

        encryptTokensMigration()->up();
        encryptTokensMigration()->up();

        $fresh = SocialAccount::withoutGlobalScopes()->find($conta->id);
        expect($fresh->access_token)->toBe('JA-CIFRADO')
            ->and(DB::table('social_accounts')->where('id', $conta->id)->value('refresh_token'))->toBeNull();
    });

    it('down devolve os tokens para texto puro', function () {
        $user = createUserWithPermissions([]);
        $conta = connectedSocialAccount($user, 'instagram', ['access_token' => 'IG-TOKEN']);

        encryptTokensMigration()->down();

        expect(DB::table('social_accounts')->where('id', $conta->id)->value('access_token'))->toBe('IG-TOKEN');
    });
});
