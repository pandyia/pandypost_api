<?php

use App\Exceptions\ScheduledPostException;
use App\Jobs\PublishPostJob;
use App\Models\User;
use App\Services\Factories\SocialMediaFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Caracterização (spec 014): as duas travas do PublishPostJob::handle antes de delegar à rede.

function guardedPost(string $platform = 'youtube', ?User $accountOwner = null): array
{
    $user = createUserWithPermissions(['posts.create']);
    $account = connectedSocialAccount($accountOwner ?? $user, $platform);
    $post = tiktokPost($user, $account, 'workspaces/w/videos/a.mp4', [], ['platform' => $platform]);

    return [$user, $account, $post];
}

describe('PublishPostJob::handle', function () {

    it('assinatura inativa lança a exceção tipada (403) com o texto atual e não chama a rede', function () {
        [$user, , $post] = guardedPost();
        $inactiveUser = Mockery::mock($user)->makePartial();
        $inactiveUser->shouldReceive('hasValidSubscriptionForPublishing')->andReturnFalse();
        $post->setRelation('user', $inactiveUser);
        $factory = Mockery::mock(SocialMediaFactory::class);
        $factory->shouldNotReceive('make');

        try {
            (new PublishPostJob($post))->handle($factory);
            $this->fail('deveria lançar ScheduledPostException');
        } catch (ScheduledPostException $e) {
            expect($e->getMessage())->toBe('Assinatura inativa ou expirada no momento da publicação.')
                ->and($e->getErrorCode())->toBe('subscription_inactive')
                ->and($e->getHttpCode())->toBe(403);
        }
    });

    it('conta de outro usuário lança a exceção tipada (404) com o texto atual e não chama a rede', function () {
        $other = createUserWithPermissions(['posts.create']);
        [, , $post] = guardedPost('youtube', $other);
        $factory = Mockery::mock(SocialMediaFactory::class);
        $factory->shouldNotReceive('make');

        try {
            (new PublishPostJob($post))->handle($factory);
            $this->fail('deveria lançar ScheduledPostException');
        } catch (ScheduledPostException $e) {
            expect($e->getMessage())->toBe('A conta vinculada ao agendamento não foi encontrada. Reconecte a conta e tente novamente.')
                ->and($e->getErrorCode())->toBe('account_not_found')
                ->and($e->getHttpCode())->toBe(404);
        }
    });

    it('conta removida lança account_not_found', function () {
        [, $account, $post] = guardedPost();
        $account->delete();
        $post = $post->fresh();

        expect(fn () => (new PublishPostJob($post))->handle(Mockery::mock(SocialMediaFactory::class)))
            ->toThrow(ScheduledPostException::class, 'A conta vinculada ao agendamento não foi encontrada.');
    });

    it('failed() grava a mensagem da exceção de domínio no error_message e marca failed', function () {
        [, , $post] = guardedPost();

        (new PublishPostJob($post))->failed(ScheduledPostException::accountNotFound());

        $fresh = $post->fresh();
        expect($fresh->status)->toBe('failed')
            ->and($fresh->error_message)->toBe('A conta vinculada ao agendamento não foi encontrada. Reconecte a conta e tente novamente.');
    });

    it('failed() com exceção de terceiros grava texto genérico, sem a mensagem original', function () {
        [, , $post] = guardedPost();

        (new PublishPostJob($post))->failed(new RuntimeException('GET https://x.test?access_token=SEGREDO'));

        $fresh = $post->fresh();
        expect($fresh->error_message)->toBe('Não foi possível publicar o post. Tente novamente em instantes.')
            ->and($fresh->error_message)->not->toContain('SEGREDO');
    });

});
