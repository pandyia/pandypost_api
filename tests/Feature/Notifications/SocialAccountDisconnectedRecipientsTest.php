<?php

use App\Exceptions\YouTubeException;
use App\Models\Notification;
use App\Models\User;
use App\Models\Workspace;
use App\Services\NotificationService;
use App\Services\YouTubeService;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Spec 018 RF-4 e RF-5.

function memberWith(Workspace $workspace, array $permissions): User
{
    $member = createUserWithPermissions($permissions, false, $workspace);

    return $member;
}

function disconnectedFor(int $userId): int
{
    return Notification::where('type', 'social_account_disconnected')->where('notifiable_id', $userId)->count();
}

describe('destinatários de social_account_disconnected', function () {

    it('avisa quem conectou e os membros com permissão de contas ou de agendar, uma vez cada', function () {
        $connector = createUserWithPermissions([]);
        $workspace = $connector->currentAccess->workspace;
        $manager = memberWith($workspace, ['social_accounts.disconnect']);
        $viewer = memberWith($workspace, ['social_accounts.view']);
        $scheduler = memberWith($workspace, ['posts.create']);
        $bystander = memberWith($workspace, ['billing.view']);
        $account = connectedSocialAccount($connector, 'youtube');

        app(NotificationService::class)->notifySocialAccountDisconnected($account);

        expect(disconnectedFor($connector->id))->toBe(1)
            ->and(disconnectedFor($manager->id))->toBe(1)
            ->and(disconnectedFor($viewer->id))->toBe(1)
            ->and(disconnectedFor($scheduler->id))->toBe(1)
            ->and(disconnectedFor($bystander->id))->toBe(0);
    });

    it('quem conectou e também tem a permissão recebe só uma', function () {
        $connector = createUserWithPermissions(['social_accounts.connect', 'posts.create']);
        $account = connectedSocialAccount($connector, 'youtube');

        app(NotificationService::class)->notifySocialAccountDisconnected($account);

        expect(disconnectedFor($connector->id))->toBe(1);
    });

    it('chamar de novo para a mesma desconexão não duplica', function () {
        $connector = createUserWithPermissions(['posts.create']);
        $account = connectedSocialAccount($connector, 'youtube');

        app(NotificationService::class)->notifySocialAccountDisconnected($account);
        app(NotificationService::class)->notifySocialAccountDisconnected($account);

        expect(Notification::where('type', 'social_account_disconnected')->count())->toBe(1);
    });

    it('não avisa membros de outro workspace nem quem conectou e saiu do workspace', function () {
        $owner = createUserWithPermissions(['posts.create']);
        $outsider = createUserWithPermissions(['posts.create', 'social_accounts.view']); // outro workspace
        $gone = createUserWithPermissions([]); // conectou, mas é de outro workspace agora
        $account = connectedSocialAccount($owner, 'youtube', ['user_id' => $gone->id]);

        app(NotificationService::class)->notifySocialAccountDisconnected($account);

        expect(disconnectedFor($owner->id))->toBe(1)
            ->and(disconnectedFor($outsider->id))->toBe(0)
            ->and(disconnectedFor($gone->id))->toBe(0);
    });

    it('o conteúdo não traz token nem erro da plataforma', function () {
        $user = createUserWithPermissions(['posts.create']);
        $account = connectedSocialAccount($user, 'youtube', ['access_token' => 'SEGREDO-TOKEN', 'refresh_token' => 'SEGREDO-REFRESH']);

        app(NotificationService::class)->notifySocialAccountDisconnected($account);

        expect(json_encode(Notification::all()->toArray()))->not->toContain('SEGREDO');
    });

});

describe('YouTube: refresh recusado', function () {

    function youtubeRefresh($account): void
    {
        (new ReflectionMethod(YouTubeService::class, 'getAuthenticatedClient'))->invoke(app(YouTubeService::class), $account);
    }

    it('invalid_grant notifica a desconexão e lança tokenRefreshFailed', function () {
        $user = createUserWithPermissions(['posts.create']);
        $teammate = memberWith($user->currentAccess->workspace, ['social_accounts.view']);
        $account = youtubeAccount($user, ['expires_at' => now()->subMinute()]);
        youtubeFakeSdk([new PsrResponse(400, ['Content-Type' => 'application/json'], json_encode(['error' => 'invalid_grant']))]);

        expect(fn () => youtubeRefresh($account))->toThrow(YouTubeException::class, 'Não foi possível renovar o acesso à conta do YouTube.');

        expect(disconnectedFor($user->id))->toBe(1)->and(disconnectedFor($teammate->id))->toBe(1);
        expect(json_encode(Notification::all()->toArray()))->not->toContain('invalid_grant');
    });

    it('falha de refresh que não é invalid_grant não manda o usuário reconectar', function () {
        $user = createUserWithPermissions(['posts.create']);
        $account = youtubeAccount($user, ['expires_at' => now()->subMinute()]);
        youtubeFakeSdk([new PsrResponse(401, ['Content-Type' => 'application/json'], json_encode(['error' => 'invalid_client']))]);

        expect(fn () => youtubeRefresh($account))->toThrow(YouTubeException::class);

        expect(Notification::where('type', 'social_account_disconnected')->count())->toBe(0);
    });

    it('repetir a falha com a mesma conta não duplica o aviso', function () {
        $user = createUserWithPermissions(['posts.create']);
        $account = youtubeAccount($user, ['expires_at' => now()->subMinute()]);
        $invalidGrant = fn () => new PsrResponse(400, ['Content-Type' => 'application/json'], json_encode(['error' => 'invalid_grant']));
        youtubeFakeSdk([$invalidGrant(), $invalidGrant()]);

        expect(fn () => youtubeRefresh($account))->toThrow(YouTubeException::class);
        expect(fn () => youtubeRefresh($account))->toThrow(YouTubeException::class);

        expect(disconnectedFor($user->id))->toBe(1);
    });

});
