<?php

use App\Enums\ScheduledPostStatus;

/*
|--------------------------------------------------------------------------
| QA — valores do ScheduledPostStatus (spec 022, RF-11)
|--------------------------------------------------------------------------
|
| A task 3.1 trocou ~16 literais de status por ScheduledPostStatus::X->value
| na esteira de publicação (services das redes, jobs e StorageService).
| Este teste trava os valores do enum: mudar um case agora mudaria o que é
| gravado e comparado no banco em toda a esteira.
*/

describe('valores do enum ScheduledPostStatus', function () {

    it('mantém os valores gravados no banco pela esteira de publicação', function () {
        expect(ScheduledPostStatus::AWAITING_UPLOAD->value)->toBe('awaiting_upload')
            ->and(ScheduledPostStatus::PENDING->value)->toBe('pending')
            ->and(ScheduledPostStatus::PROCESSING->value)->toBe('processing')
            ->and(ScheduledPostStatus::PUBLISHED->value)->toBe('published')
            ->and(ScheduledPostStatus::FAILED->value)->toBe('failed')
            ->and(ScheduledPostStatus::CANCELLED->value)->toBe('cancelled');
    });

    it('não ganhou nem perdeu casos sem revisão da esteira', function () {
        expect(array_map(fn (ScheduledPostStatus $c) => $c->value, ScheduledPostStatus::cases()))
            ->toBe(['awaiting_upload', 'pending', 'processing', 'published', 'failed', 'cancelled']);
    });
});
