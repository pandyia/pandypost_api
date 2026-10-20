<?php

use App\Models\ContentPipeline;
use App\Models\Invite;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Price;
use App\Models\PriceHistory;
use App\Models\Role;
use App\Models\ScheduledPost;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

$uuidModels = [
    ContentPipeline::class, Invite::class, Payment::class, Plan::class, PriceHistory::class, Price::class,
    Role::class, ScheduledPost::class, SocialAccount::class, User::class, Workspace::class,
];

describe('uuid como chave de rota', function () use ($uuidModels) {

    foreach ($uuidModels as $modelClass) {
        $name = class_basename($modelClass);

        it("{$name} usa uuid como route key", function () use ($modelClass) {
            expect((new $modelClass)->getRouteKeyName())->toBe('uuid');
        });

        it("{$name} gera uuid ao criar e respeita o uuid informado", function () use ($modelClass) {
            $events = $modelClass::getEventDispatcher();

            $generated = new $modelClass;
            $events->dispatch('eloquent.creating: '.$modelClass, $generated);

            $explicit = new $modelClass;
            $explicit->uuid = '11111111-1111-4111-8111-111111111111';
            $events->dispatch('eloquent.creating: '.$modelClass, $explicit);

            expect(Str::isUuid($generated->uuid))->toBeTrue()
                ->and($explicit->uuid)->toBe('11111111-1111-4111-8111-111111111111');
        });
    }

    it('gera uuid de verdade ao persistir Plan, Role, Workspace e User', function () {
        $user = createUserWithPermissions([]);
        $workspace = Workspace::withoutGlobalScopes()->find($user->currentAccess->workspace_id);
        $role = Role::withoutGlobalScopes()->find($user->currentAccess->role_id);
        $plan = Plan::create(['name' => 'Pro']);
        $explicit = Plan::create(['name' => 'Fixo', 'uuid' => '22222222-2222-4222-8222-222222222222']);

        expect(Str::isUuid($user->uuid))->toBeTrue()
            ->and(Str::isUuid($workspace->uuid))->toBeTrue()
            ->and(Str::isUuid($role->uuid))->toBeTrue()
            ->and(Str::isUuid($plan->fresh()->uuid))->toBeTrue()
            ->and($explicit->fresh()->uuid)->toBe('22222222-2222-4222-8222-222222222222');
    });
});

describe('serialização de Plan, Price e PriceHistory', function () {

    it('oculta o id interno no toArray', function () {
        $plan = Plan::create(['name' => 'Pro']);
        $price = Price::create(['plan_id' => $plan->id, 'amount' => 1000, 'currency' => 'brl', 'frequency' => 'monthly']);
        $history = PriceHistory::create([
            'price_id' => $price->id, 'amount' => 900, 'currency' => 'brl', 'frequency' => 'monthly', 'archived_at' => now(),
        ]);

        foreach ([$plan, $price, $history] as $model) {
            expect($model->fresh()->toArray())->not->toHaveKey('id')->toHaveKey('uuid');
        }
    });
});
