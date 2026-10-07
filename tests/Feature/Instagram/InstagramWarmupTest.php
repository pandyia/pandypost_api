<?php

use App\Jobs\CheckInstagramContainerJob;
use App\Services\Storage\StorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    Bus::fake();
    $this->partialMock(StorageService::class)
        ->shouldReceive('generateDownloadUrl')->andReturn('https://s3.test/midia-assinada');

    $user = createUserWithPermissions();
    $this->instagram = createSocialAccount('instagram', $user);
    $this->youtube = createSocialAccount('youtube', $user);
});

describe('warmup de posts do Instagram', function () {

    it('prepara só posts pendentes do Instagram agendados para a próxima hora', function () {
        Http::fake(['https://graph.instagram.com/*' => Http::response(['id' => 'container_novo'])]);
        $due = createScheduledPost($this->instagram, ['scheduled_at' => now()->addMinutes(30)]);
        createScheduledPost($this->instagram, ['scheduled_at' => now()->addMinutes(90)]);
        createScheduledPost($this->instagram, ['scheduled_at' => now()->addMinutes(30), 'status' => 'cancelled']);
        createScheduledPost($this->youtube, ['scheduled_at' => now()->addMinutes(30)]);

        $this->artisan('posts:warmup')->assertSuccessful();

        Http::assertSentCount(1);
        expect($due->fresh()->container_id)->toBe('container_novo')
            ->and($due->fresh()->status)->toBe('pending');
        Bus::assertDispatched(CheckInstagramContainerJob::class, fn ($job) => $job->post->is($due) && ! $job->shouldPublish);
    });

    it('não recria o container de posts que já têm um válido', function () {
        Http::fake();
        createScheduledPost($this->instagram, [
            'scheduled_at' => now()->addMinutes(30),
            'container_id' => 'container_existente',
            'container_created_at' => now()->subMinutes(10),
        ]);

        $this->artisan('posts:warmup')->assertSuccessful();

        Http::assertNothingSent();
    });

    it('continua com os outros posts quando um falha', function () {
        Http::fake(['https://graph.instagram.com/*' => Http::sequence()
            ->push(['error' => ['message' => 'falhou']], 500)
            ->push(['id' => 'container_novo'])]);
        createScheduledPost($this->instagram, ['scheduled_at' => now()->addMinutes(10)]);
        createScheduledPost($this->instagram, ['scheduled_at' => now()->addMinutes(20)]);

        $this->artisan('posts:warmup')->assertSuccessful();

        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/media'));
        Bus::assertDispatchedTimes(CheckInstagramContainerJob::class, 1);
    });
});
