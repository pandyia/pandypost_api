<?php

use App\Models\User;
use App\Services\Storage\StorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    // Parcial: a lógica de path roda de verdade; só o que toca o S3 é simulado por teste.
    $this->storage = $this->partialMock(StorageService::class);
});

/** Atualiza colunas direto no banco: o helper de teste põe `test_token` no model, e `update()` o gravaria. */
function setUserColumns(User $user, array $columns): void
{
    User::whereKey($user->id)->update($columns);
}

function validProfile(array $overrides = []): array
{
    return array_merge([
        'name' => 'Maria Souza',
        'phone' => '11912345678',
        'bio' => 'Crio conteúdo sobre receitas.',
        'timezone' => 'America/Sao_Paulo',
    ], $overrides);
}

describe('atualizar dados pessoais', function () {

    it('atualiza os dados e eles aparecem no GET /me', function () {
        $user = createUserWithPermissions([]);

        $this->withToken($user->test_token)->patchJson('/api/me', validProfile())
            ->assertOk()
            ->assertJsonPath('data.name', 'Maria Souza')
            ->assertJsonPath('data.phone', '11912345678')
            ->assertJsonPath('data.bio', 'Crio conteúdo sobre receitas.')
            ->assertJsonPath('data.timezone', 'America/Sao_Paulo');

        $this->withToken($user->test_token)->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.name', 'Maria Souza')
            ->assertJsonPath('data.phone', '11912345678')
            ->assertJsonPath('data.avatar', null);
    });

    it('apaga telefone e bio quando vêm nulos e aceita máscara no telefone', function () {
        $user = createUserWithPermissions([]);
        setUserColumns($user, ['phone' => '11912345678', 'bio' => 'antiga']);

        $this->withToken($user->test_token)->patchJson('/api/me', validProfile(['phone' => null, 'bio' => null]))
            ->assertOk()
            ->assertJsonPath('data.phone', null)
            ->assertJsonPath('data.bio', null);

        $this->withToken($user->test_token)->patchJson('/api/me', validProfile(['phone' => '(11) 91234-5678']))
            ->assertOk()
            ->assertJsonPath('data.phone', '11912345678');
    });

    it('ignora campos protegidos enviados no corpo', function () {
        $user = createUserWithPermissions([]);
        $email = $user->email;
        $uuid = $user->uuid;

        $this->withToken($user->test_token)->patchJson('/api/me', validProfile([
            'email' => 'invasor@exemplo.com',
            'uuid' => 'outro-uuid',
            'is_super_admin' => true,
            'is_active' => false,
            'access_id' => 9999,
            'avatar_path' => 'users/outro/avatars/x.jpg',
        ]))->assertOk()->assertJsonPath('data.is_super_admin', false);

        $user->refresh();
        expect($user->email)->toBe($email)
            ->and($user->uuid)->toBe($uuid)
            ->and($user->is_super_admin)->toBeFalse()
            ->and($user->is_active)->toBeTrue()
            ->and($user->access_id)->not->toBe(9999)
            ->and($user->avatar_path)->toBeNull();
    });

    it('valida os campos', function (array $overrides, string $field) {
        $user = createUserWithPermissions([]);

        $this->withToken($user->test_token)->patchJson('/api/me', validProfile($overrides))
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);

        expect($user->fresh()->name)->toBe($user->name);
    })->with([
        'nome vazio' => [['name' => ''], 'name'],
        'nome curto' => [['name' => 'Al'], 'name'],
        'nome longo' => [['name' => str_repeat('a', 81)], 'name'],
        'telefone curto' => [['phone' => '1234'], 'phone'],
        'bio longa' => [['bio' => str_repeat('a', 161)], 'bio'],
        'fuso inexistente' => [['timezone' => 'Marte/Olympus'], 'timezone'],
        'fuso ausente' => [['timezone' => null], 'timezone'],
    ]);

    it('exige autenticação', function () {
        $this->patchJson('/api/me', validProfile())->assertUnauthorized();
    });
});

describe('URL de upload da foto', function () {

    beforeEach(function () {
        // Disco s3 de mentira: assinar a URL é conta local, não faz chamada de rede.
        config(['filesystems.disks.s3' => [
            'driver' => 's3',
            'key' => 'chave-de-teste',
            'secret' => 'segredo-de-teste',
            'region' => 'us-east-1',
            'bucket' => 'pandypost-teste',
            'endpoint' => 'https://storage.exemplo.test',
            'use_path_style_endpoint' => true,
            'throw' => false,
        ]]);
        Storage::forgetDisk('s3');
    });

    it('devolve a URL pré-assinada com o path do próprio usuário', function (string $contentType, string $extension) {
        $user = createUserWithPermissions([]);

        $response = $this->withToken($user->test_token)
            ->postJson('/api/me/avatar/upload-url', ['content_type' => $contentType])
            ->assertOk()
            ->assertJsonStructure(['url', 'headers', 'path', 'content_type', 'max_size', 'expires_in'])
            ->assertJsonPath('content_type', $contentType)
            ->assertJsonPath('max_size', 5242880);

        expect($response->json('path'))->toStartWith("users/{$user->uuid}/avatars/")->toEndWith(".{$extension}")
            ->and($response->json('url'))->toContain('storage.exemplo.test');
    })->with([
        'jpeg' => ['image/jpeg', 'jpg'],
        'png' => ['image/png', 'png'],
        'webp' => ['image/webp', 'webp'],
    ]);

    it('rejeita tipo de arquivo que não é imagem permitida', function (mixed $payload) {
        $user = createUserWithPermissions([]);

        $this->withToken($user->test_token)->postJson('/api/me/avatar/upload-url', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('content_type');
    })->with([
        'ausente' => [[]],
        'vídeo' => [['content_type' => 'video/mp4']],
        'gif' => [['content_type' => 'image/gif']],
    ]);

    it('exige autenticação', function () {
        $this->postJson('/api/me/avatar/upload-url', ['content_type' => 'image/jpeg'])->assertUnauthorized();
    });
});

describe('confirmar a foto enviada', function () {

    it('grava a foto, apaga a antiga e devolve a URL assinada no usuário', function () {
        $user = createUserWithPermissions([]);
        $old = "users/{$user->uuid}/avatars/antiga.jpg";
        $new = "users/{$user->uuid}/avatars/nova.jpg";
        setUserColumns($user, ['avatar_path' => $old]);

        $this->storage->shouldReceive('exists')->with($new)->andReturnTrue();
        $this->storage->shouldReceive('mimeType')->with($new)->andReturn('image/jpeg');
        $this->storage->shouldReceive('size')->with($new)->andReturn(150_000);
        $this->storage->shouldReceive('delete')->once()->with($old)->andReturnTrue();
        $this->storage->shouldReceive('generateDownloadUrl')
            ->with($new, 518400)
            ->andReturn('https://storage.exemplo.test/assinada?sig=1');

        $this->withToken($user->test_token)->putJson('/api/me/avatar', ['path' => $new])
            ->assertOk()
            ->assertJsonPath('data.avatar', 'https://storage.exemplo.test/assinada?sig=1')
            ->assertJsonMissingPath('data.avatar_path');

        expect($user->fresh()->avatar_path)->toBe($new);
    });

    it('rejeita path de outro usuário, de outro diretório ou inexistente, sem alterar nada', function (string $path, bool $exists) {
        $user = createUserWithPermissions([]);
        $other = createUserWithPermissions([]);
        $path = str_replace(['{other}', '{me}'], [$other->uuid, $user->uuid], $path);

        $this->storage->shouldReceive('exists')->with($path)->andReturn($exists);
        $this->storage->shouldNotReceive('delete');

        $this->withToken($user->test_token)->putJson('/api/me/avatar', ['path' => $path])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('path');

        expect($user->fresh()->avatar_path)->toBeNull();
    })->with([
        'de outro usuário' => ['users/{other}/avatars/a.jpg', true],
        'de outro diretório' => ['workspaces/x/images/a.jpg', true],
        'de post do próprio usuário' => ['users/{me}/documents/a.jpg', true],
        'arquivo inexistente' => ['users/{me}/avatars/nao-existe.jpg', false],
    ]);

    it('apaga o arquivo e recusa quando não é uma imagem permitida', function () {
        $user = createUserWithPermissions([]);
        $path = "users/{$user->uuid}/avatars/arquivo.jpg";

        $this->storage->shouldReceive('exists')->with($path)->andReturnTrue();
        $this->storage->shouldReceive('mimeType')->with($path)->andReturn('application/pdf');
        $this->storage->shouldReceive('delete')->once()->with($path)->andReturnTrue();

        $this->withToken($user->test_token)->putJson('/api/me/avatar', ['path' => $path])
            ->assertUnprocessable()
            ->assertJsonPath('error', 'profile_invalid_avatar_file');

        expect($user->fresh()->avatar_path)->toBeNull();
    });

    it('apaga o arquivo e recusa quando passa de 5 MB', function () {
        $user = createUserWithPermissions([]);
        $path = "users/{$user->uuid}/avatars/grande.png";

        $this->storage->shouldReceive('exists')->with($path)->andReturnTrue();
        $this->storage->shouldReceive('mimeType')->with($path)->andReturn('image/png');
        $this->storage->shouldReceive('size')->with($path)->andReturn(5_242_881);
        $this->storage->shouldReceive('delete')->once()->with($path)->andReturnTrue();

        $this->withToken($user->test_token)->putJson('/api/me/avatar', ['path' => $path])
            ->assertUnprocessable()
            ->assertJsonPath('error', 'profile_avatar_too_large');

        expect($user->fresh()->avatar_path)->toBeNull();
    });

    it('exige autenticação', function () {
        $this->putJson('/api/me/avatar', ['path' => 'users/x/avatars/a.jpg'])->assertUnauthorized();
    });
});

describe('remover a foto', function () {

    it('apaga o arquivo e limpa o campo', function () {
        $user = createUserWithPermissions([]);
        $path = "users/{$user->uuid}/avatars/foto.jpg";
        setUserColumns($user, ['avatar_path' => $path]);

        $this->storage->shouldReceive('delete')->once()->with($path)->andReturnTrue();

        $this->withToken($user->test_token)->deleteJson('/api/me/avatar')
            ->assertOk()
            ->assertJsonPath('data.avatar', null);

        expect($user->fresh()->avatar_path)->toBeNull();
    });

    it('é idempotente quando não há foto', function () {
        $user = createUserWithPermissions([]);

        $this->storage->shouldNotReceive('delete');

        $this->withToken($user->test_token)->deleteJson('/api/me/avatar')
            ->assertOk()
            ->assertJsonPath('data.avatar', null);
    });

    it('exige autenticação', function () {
        $this->deleteJson('/api/me/avatar')->assertUnauthorized();
    });
});

describe('trocar a senha', function () {

    function userWithPassword(string $password = 'SenhaAntiga1'): User
    {
        $user = createUserWithPermissions([]);
        setUserColumns($user, ['password' => Hash::make($password)]);

        return $user;
    }

    it('troca a senha, encerra as outras sessões e mantém a atual', function () {
        $user = userWithPassword();
        $otherToken = $user->createToken('outro-aparelho')->plainTextToken;

        $this->withToken($user->test_token)->putJson('/api/me/password', [
            'current_password' => 'SenhaAntiga1',
            'password' => 'NovaSenha123',
            'password_confirmation' => 'NovaSenha123',
        ])->assertOk()->assertJsonPath('message', 'Senha atualizada com sucesso.');

        expect(Hash::check('NovaSenha123', $user->fresh()->password))->toBeTrue()
            ->and($user->tokens()->count())->toBe(1);

        // O token em uso continua valendo; o do outro aparelho foi revogado.
        $this->app['auth']->forgetGuards();
        $this->withToken($user->test_token)->getJson('/api/me')->assertOk();
        $this->app['auth']->forgetGuards();
        $this->withToken($otherToken)->getJson('/api/me')->assertUnauthorized();
    });

    it('rejeita a senha atual errada com o erro no campo certo', function () {
        $user = userWithPassword();

        $this->withToken($user->test_token)->putJson('/api/me/password', [
            'current_password' => 'errada',
            'password' => 'NovaSenha123',
            'password_confirmation' => 'NovaSenha123',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('current_password')
            ->assertJsonPath('errors.current_password.0', 'A senha atual está incorreta.');

        expect(Hash::check('SenhaAntiga1', $user->fresh()->password))->toBeTrue();
    });

    it('rejeita senha nova fraca, sem confirmação ou igual à atual', function (array $payload, string $field) {
        $user = userWithPassword();

        $this->withToken($user->test_token)->putJson('/api/me/password', array_merge(['current_password' => 'SenhaAntiga1'], $payload))
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);

        expect(Hash::check('SenhaAntiga1', $user->fresh()->password))->toBeTrue();
    })->with([
        'curta' => [['password' => 'Ab1', 'password_confirmation' => 'Ab1'], 'password'],
        'sem maiúscula' => [['password' => 'novasenha123', 'password_confirmation' => 'novasenha123'], 'password'],
        'sem número' => [['password' => 'NovaSenhaAqui', 'password_confirmation' => 'NovaSenhaAqui'], 'password'],
        'sem confirmação' => [['password' => 'NovaSenha123'], 'password'],
        'confirmação diferente' => [['password' => 'NovaSenha123', 'password_confirmation' => 'Outra123'], 'password'],
        'igual à atual' => [['password' => 'SenhaAntiga1', 'password_confirmation' => 'SenhaAntiga1'], 'password'],
    ]);

    it('exige autenticação', function () {
        $this->putJson('/api/me/password', [])->assertUnauthorized();
    });
});
