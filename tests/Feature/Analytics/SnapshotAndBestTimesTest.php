<?php

use App\Jobs\SnapshotSocialAccountMetricsJob;
use App\Models\SocialAccountMetricSnapshot;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    Carbon::setTestNow('2026-10-14 03:00:00');
    $this->user = createUserWithPermissions([]);
});

afterEach(fn () => Carbon::setTestNow());

describe('snapshot diário', function () {

    it('agenda um job por conta Instagram e TikTok, na fila da plataforma', function () {
        Queue::fake();
        $instagram = connectedSocialAccount($this->user, 'instagram');
        $tiktok = connectedSocialAccount($this->user, 'tiktok');
        connectedSocialAccount($this->user, 'youtube');

        Artisan::call('analytics:snapshot');

        Queue::assertPushed(SnapshotSocialAccountMetricsJob::class, 2);
        Queue::assertPushedOn('instagram', SnapshotSocialAccountMetricsJob::class, fn ($job) => $job->account->is($instagram));
        Queue::assertPushedOn('tiktok', SnapshotSocialAccountMetricsJob::class, fn ($job) => $job->account->is($tiktok));
    });

    it('grava um registro por dia, sem duplicar ao rodar duas vezes', function () {
        $account = connectedSocialAccount($this->user, 'tiktok');
        Http::fake(['open.tiktokapis.com/v2/user/info/*' => Http::sequence()
            ->push(['data' => ['user' => ['follower_count' => 100, 'following_count' => 5, 'likes_count' => 900, 'video_count' => 12]], 'error' => ['code' => 'ok']])
            ->push(['data' => ['user' => ['follower_count' => 110, 'following_count' => 5, 'likes_count' => 950, 'video_count' => 12]], 'error' => ['code' => 'ok']]),
        ]);

        SnapshotSocialAccountMetricsJob::dispatchSync($account);
        SnapshotSocialAccountMetricsJob::dispatchSync($account);

        expect(SocialAccountMetricSnapshot::count())->toBe(1)
            ->and(SocialAccountMetricSnapshot::first())
            ->followers->toBe(110)
            ->total_likes->toBe(950)
            ->media_count->toBe(12);
    });

    it('ignora a conta que precisa reconectar, sem falhar o job', function () {
        $account = connectedSocialAccount($this->user, 'instagram');
        Http::fake(['*' => Http::response(['error' => ['code' => 190, 'message' => 'Invalid token']], 400)]);

        SnapshotSocialAccountMetricsJob::dispatchSync($account);

        expect(SocialAccountMetricSnapshot::count())->toBe(0);
    });

    it('remove snapshots com mais de 400 dias', function () {
        $account = connectedSocialAccount($this->user, 'instagram');
        SocialAccountMetricSnapshot::create(['social_account_id' => $account->id, 'date' => '2025-09-01', 'followers' => 1]);
        SocialAccountMetricSnapshot::create(['social_account_id' => $account->id, 'date' => '2025-10-01', 'followers' => 2]);

        Artisan::call('model:prune', ['--model' => [SocialAccountMetricSnapshot::class]]);

        expect(SocialAccountMetricSnapshot::pluck('followers')->all())->toBe([2]);
    });

});

describe('melhores horários', function () {

    it('calcula pelo histórico do TikTok', function () {
        $account = connectedSocialAccount($this->user, 'tiktok');
        $videos = collect(range(1, 6))->map(fn (int $i) => [
            'id' => "v{$i}",
            // Três às 21:00 UTC (18h em São Paulo) com mais views.
            'create_time' => Carbon::parse("2026-10-0{$i} ".($i <= 3 ? '21:00:00' : '15:00:00'), 'UTC')->getTimestamp(),
            'view_count' => $i <= 3 ? 10000 : 100,
        ])->all();

        Http::fake(['open.tiktokapis.com/v2/video/list/*' => Http::response(['data' => ['videos' => $videos, 'has_more' => false], 'error' => ['code' => 'ok']])]);

        $this->withToken($this->user->test_token)
            ->getJson("/api/analytics/{$account->uuid}/best-times")
            ->assertOk()
            ->assertJsonPath('data.best_hours.0', 18)
            ->assertJsonPath('data.is_fallback', false)
            ->assertJsonPath('data.sample_size', 6)
            ->assertJsonCount(24, 'data.by_hour');
    });

    it('calcula pelo histórico do Instagram com as interações públicas', function () {
        $account = connectedSocialAccount($this->user, 'instagram', ['platform_id' => '1789']);
        $media = collect(range(1, 5))->map(fn (int $i) => [
            'id' => "m{$i}",
            'timestamp' => "2026-10-0{$i}T23:00:00+0000",
            'like_count' => 10 * $i,
            'comments_count' => 1,
        ])->all();

        Http::fake(['graph.instagram.com/*' => Http::response(['data' => $media])]);

        $this->withToken($this->user->test_token)
            ->getJson("/api/analytics/{$account->uuid}/best-times")
            ->assertOk()
            ->assertJsonPath('data.best_hours', [20])
            ->assertJsonPath('data.metric', 'interactions');
    });

    it('usa o fallback quando a API falha, sem guardar no cache', function () {
        $account = connectedSocialAccount($this->user, 'youtube');
        Http::fake(['*' => Http::response([], 500)]);

        $this->withToken($this->user->test_token)
            ->getJson("/api/analytics/{$account->uuid}/best-times")
            ->assertOk()
            ->assertJsonPath('data.best_hours', [14, 18, 20])
            ->assertJsonPath('data.is_fallback', true);

        expect(cache()->has("analytics:v1:best_times:{$account->id}"))->toBeFalse();
    });

    it('retorna 404 para conta de outro workspace', function () {
        $foreign = connectedSocialAccount(createUserWithPermissions([]), 'tiktok');

        $this->withToken($this->user->test_token)
            ->getJson("/api/analytics/{$foreign->uuid}/best-times")
            ->assertNotFound();
    });

});
