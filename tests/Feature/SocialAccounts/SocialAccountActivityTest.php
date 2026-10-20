<?php

use App\Models\ScheduledPost;
use App\Models\SocialAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function activityTestPost(SocialAccount $account, $scheduledAt): void
{
    ScheduledPost::withoutGlobalScopes()->create([
        'user_id' => $account->user_id,
        'social_account_id' => $account->id,
        'media_path' => 'workspaces/w/videos/video.mp4',
        'platform' => 'youtube',
        'status' => 'pending',
        'scheduled_at' => $scheduledAt,
        'title' => 'Post '.uniqid(),
    ]);
}

describe('atividade da conta social', function () {

    beforeEach(function () {
        $this->account = connectedSocialAccount(createUserWithPermissions([]), 'youtube');
    });

    it('conta sem post é considerada parada e não tem dias de atividade', function () {
        expect($this->account->daysSinceLastActivity())->toBeNull()
            ->and($this->account->isStale())->toBeTrue();
    });

    it('post agendado no futuro retorna 0 dias e não está parada', function () {
        activityTestPost($this->account, now()->addDays(3));

        expect($this->account->daysSinceLastActivity())->toBe(0)
            ->and($this->account->isStale())->toBeFalse();
    });

    it('post antigo conta os dias desde a atividade mais recente', function () {
        activityTestPost($this->account, now()->subDays(10));
        activityTestPost($this->account, now()->subDays(5));

        expect($this->account->daysSinceLastActivity())->toBe(5)
            ->and($this->account->isStale())->toBeTrue()
            ->and($this->account->isStale(6))->toBeFalse();
    });

    it('usa a relação carregada quando disponível', function () {
        activityTestPost($this->account, now()->subDays(4));

        $account = SocialAccount::withoutGlobalScopes()->with('scheduledPosts')->find($this->account->id);

        expect($account->relationLoaded('scheduledPosts'))->toBeTrue()
            ->and($account->daysSinceLastActivity())->toBe(4);
    });
});
