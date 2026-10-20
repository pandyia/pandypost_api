<?php

use App\Models\Role;
use App\Models\ScheduledPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function scopeTestPostFor(User $user): ScheduledPost
{
    $account = connectedSocialAccount($user, 'youtube');

    return ScheduledPost::withoutGlobalScopes()->create([
        'user_id' => $account->user_id,
        'social_account_id' => $account->id,
        'media_path' => 'workspaces/w/videos/video.mp4',
        'platform' => 'youtube',
        'status' => 'pending',
        'scheduled_at' => now()->addDay(),
        'title' => 'Post '.uniqid(),
    ]);
}

describe('escopo de workspace (BelongsToWorkspace)', function () {

    it('filtra pela coluna workspace_id nos models diretos (Role)', function () {
        $userA = createUserWithPermissions([]);
        $userB = createUserWithPermissions([]);

        $this->actingAs($userA);

        $roles = Role::query()->get();

        expect($roles)->not->toBeEmpty()
            ->and($roles->pluck('workspace_id')->unique()->all())->toBe([$userA->currentAccess->workspace_id]);
    });

    it('filtra via relacionamento nos models indiretos (User → accesses)', function () {
        $userA = createUserWithPermissions([]);
        $userB = createUserWithPermissions([]);

        $this->actingAs($userA);

        $ids = User::query()->pluck('id')->all();

        expect($ids)->toContain($userA->id)->not->toContain($userB->id);
    });

    it('filtra via relacionamento nos models indiretos (ScheduledPost → socialAccount)', function () {
        $userA = createUserWithPermissions([]);
        $userB = createUserWithPermissions([]);
        $postA = scopeTestPostFor($userA);
        $postB = scopeTestPostFor($userB);

        $this->actingAs($userA);

        $ids = ScheduledPost::query()->pluck('id')->all();

        expect($ids)->toContain($postA->id)->not->toContain($postB->id);
    });

    it('não filtra nada sem currentAccess (comportamento atual, ver VULN-09)', function () {
        $userA = createUserWithPermissions([]);
        $userB = createUserWithPermissions([]);
        $semAcesso = User::factory()->create();

        $this->actingAs($semAcesso);

        $ids = User::query()->pluck('id')->all();

        expect($ids)->toContain($userA->id, $userB->id)
            ->and(Role::query()->count())->toBe(Role::withoutGlobalScopes()->count());
    });

    it('não filtra nada sem usuário autenticado', function () {
        createUserWithPermissions([]);
        createUserWithPermissions([]);

        expect(Role::query()->count())->toBe(Role::withoutGlobalScopes()->count())
            ->and(Role::query()->count())->toBe(2);
    });
});
