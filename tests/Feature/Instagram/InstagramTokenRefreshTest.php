<?php

use App\Models\SocialAccount;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = createUserWithPermissions();
});

describe('renovação diária dos tokens do Instagram', function () {

    it('renova só os tokens do Instagram que vencem nos próximos 7 dias', function () {
        Http::fake(['https://graph.instagram.com/refresh_access_token*' => Http::response(['access_token' => 'IG.renovado', 'expires_in' => 5184000])]);
        $expiring = createSocialAccount('instagram', $this->user, ['expires_at' => now()->addDays(5)]);
        $healthy = createSocialAccount('instagram', $this->user, ['expires_at' => now()->addDays(30)]);
        $expired = createSocialAccount('instagram', $this->user, ['expires_at' => now()->subDay()]);
        $youtube = createSocialAccount('youtube', $this->user, ['expires_at' => now()->addDays(2)]);

        $this->artisan('instagram:refresh-tokens')->assertSuccessful();

        Http::assertSentCount(1);
        expect($expiring->fresh()->access_token)->toBe('IG.renovado')
            ->and($expiring->fresh()->expires_at->greaterThan(now()->addDays(59)))->toBeTrue()
            ->and($healthy->fresh()->access_token)->toBe('instagram_access_token')
            ->and($expired->fresh()->access_token)->toBe('instagram_access_token')
            ->and($youtube->fresh()->access_token)->toBe('youtube_access_token');
    });

    it('continua com as outras contas quando uma renovação falha', function () {
        Http::fake(['https://graph.instagram.com/refresh_access_token*' => Http::sequence()
            ->push(['error' => ['message' => 'invalid token']], 400)
            ->push(['access_token' => 'IG.renovado', 'expires_in' => 5184000])]);
        createSocialAccount('instagram', $this->user, ['expires_at' => now()->addDays(2)]);
        createSocialAccount('instagram', $this->user, ['expires_at' => now()->addDays(3)]);

        $this->artisan('instagram:refresh-tokens')->assertSuccessful();

        Http::assertSentCount(2);
        expect(SocialAccount::where('access_token', 'IG.renovado')->count())->toBe(1);
    });

    it('está agendada para rodar todo dia', function () {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event) => str_contains($event->command ?? '', 'instagram:refresh-tokens'));

        expect($event)->not->toBeNull()
            ->and($event->expression)->toBe('0 3 * * *');
    });

    it('não envia o token para renovação de contas fora da janela', function () {
        Http::fake();
        createSocialAccount('instagram', $this->user, ['expires_at' => now()->addDays(20)]);

        $this->artisan('instagram:refresh-tokens')->assertSuccessful();

        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'refresh_access_token'));
    });
});
