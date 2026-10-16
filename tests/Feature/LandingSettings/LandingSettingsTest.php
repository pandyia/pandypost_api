<?php

use App\Models\AppSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('GET /api/landing-settings (público)', function () {
    it('devolve o tema padrão quando nada foi salvo', function () {
        $this->getJson('/api/landing-settings')
            ->assertOk()
            ->assertExactJson(['data' => ['brand' => 'pop']]);
    });

    it('devolve o tema salvo, sem exigir login', function () {
        AppSetting::create(['key' => AppSetting::LANDING_BRAND, 'value' => 'coral']);

        $this->getJson('/api/landing-settings')
            ->assertOk()
            ->assertExactJson(['data' => ['brand' => 'coral']]);
    });

    it('cai no tema padrão quando o valor salvo não existe mais', function () {
        AppSetting::create(['key' => AppSetting::LANDING_BRAND, 'value' => 'tema-removido']);

        $this->getJson('/api/landing-settings')
            ->assertOk()
            ->assertJsonPath('data.brand', 'pop');
    });
});

describe('PUT /api/admin/landing-settings', function () {
    it('permite ao super admin trocar o tema e a rota pública reflete', function () {
        $admin = createSuperAdmin();

        $this->withToken($admin->test_token)
            ->putJson('/api/admin/landing-settings', ['brand' => 'bambu'])
            ->assertOk()
            ->assertJsonPath('message', 'Tema da landing atualizado.')
            ->assertJsonPath('data.brand', 'bambu');

        $this->getJson('/api/landing-settings')->assertJsonPath('data.brand', 'bambu');
    });

    it('é idempotente: repetir o mesmo tema mantém uma única linha', function () {
        $admin = createSuperAdmin();

        foreach (range(1, 2) as $_) {
            $this->withToken($admin->test_token)
                ->putJson('/api/admin/landing-settings', ['brand' => 'violeta'])
                ->assertOk();
        }

        expect(AppSetting::where('key', AppSetting::LANDING_BRAND)->count())->toBe(1)
            ->and(AppSetting::where('key', AppSetting::LANDING_BRAND)->value('value'))->toBe('violeta');
    });

    it('nega a quem não é super admin e não altera nada', function () {
        $user = createUserWithPermissions([]);

        $this->withToken($user->test_token)
            ->putJson('/api/admin/landing-settings', ['brand' => 'coral'])
            ->assertForbidden();

        expect(AppSetting::count())->toBe(0);
    });

    it('nega ao dono de workspace mesmo com permissões de billing', function () {
        $owner = createUserWithPermissions(['billing.view', 'billing.manage']);

        $this->withToken($owner->test_token)
            ->putJson('/api/admin/landing-settings', ['brand' => 'coral'])
            ->assertForbidden();

        expect(AppSetting::count())->toBe(0);
    });

    it('exige autenticação', function () {
        $this->putJson('/api/admin/landing-settings', ['brand' => 'coral'])
            ->assertUnauthorized();

        expect(AppSetting::count())->toBe(0);
    });

    it('rejeita tema ausente, desconhecido ou de tipo errado sem alterar nada', function (mixed $payload) {
        $admin = createSuperAdmin();

        $this->withToken($admin->test_token)
            ->putJson('/api/admin/landing-settings', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('brand');

        expect(AppSetting::count())->toBe(0);
    })->with([
        'ausente' => [[]],
        'desconhecido' => [['brand' => 'neon']],
        'tipo errado' => [['brand' => ['pop']]],
    ]);

    it('ignora campos extras e não os devolve', function () {
        $admin = createSuperAdmin();

        $this->withToken($admin->test_token)
            ->putJson('/api/admin/landing-settings', ['brand' => 'pop', 'user_id' => 99, 'key' => 'outra.chave'])
            ->assertOk()
            ->assertExactJson([
                'message' => 'Tema da landing atualizado.',
                'data' => ['brand' => 'pop'],
            ]);

        expect(AppSetting::pluck('key')->all())->toBe([AppSetting::LANDING_BRAND]);
    });
});
