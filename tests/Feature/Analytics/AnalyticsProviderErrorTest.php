<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Caracterização — erro inesperado dos providers de analytics (spec 022, 3.0)
|--------------------------------------------------------------------------
|
| Espelho do caso já coberto para o YouTube (YouTubeAnalyticsTest): resposta
| inesperada da API do TikTok e do Instagram vira 502
| analytics_provider_unavailable, sem vazar o corpo de terceiro ao cliente.
| Trava o contrato antes da troca dos RuntimeException por exceção de domínio
| (RF-12) e da classe base dos providers (RF-13).
*/

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    $this->user = createUserWithPermissions([]);
});

describe('erro inesperado da API de analytics', function () {

    it('TikTok fora do ar responde 502 genérico sem vazar detalhes', function () {
        config(['services.tiktok' => ['client_key' => 'ck', 'client_secret' => 'cs', 'redirect' => 'https://api.test/api/social-accounts/tiktok/callback']]);
        $account = connectedSocialAccount($this->user, 'tiktok');

        Http::fake(['open.tiktokapis.com/*' => Http::response('segredo-interno do backend do TikTok', 500)]);

        $response = $this->withToken($this->user->test_token)
            ->getJson("/api/analytics/{$account->uuid}/dashboard")
            ->assertStatus(502)
            ->assertJsonPath('error', 'analytics_provider_unavailable');

        expect($response->getContent())->not->toContain('segredo-interno');
    });

    it('Instagram fora do ar responde 502 genérico sem vazar detalhes', function () {
        $account = connectedSocialAccount($this->user, 'instagram', ['platform_id' => '1789']);

        Http::fake(['graph.instagram.com/*' => Http::response('segredo-interno do backend da Meta', 500)]);

        $response = $this->withToken($this->user->test_token)
            ->getJson("/api/analytics/{$account->uuid}/dashboard")
            ->assertStatus(502)
            ->assertJsonPath('error', 'analytics_provider_unavailable');

        expect($response->getContent())->not->toContain('segredo-interno');
    });
});
