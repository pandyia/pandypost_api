<?php

use App\Enums\SubscriptionChangeKind;
use App\Models\Price;

/*
| Spec 024, tarefa 2: RN-1 (vigência da troca). Puro, sem banco e sem Stripe.
| Contrato assumido (ver pergunta no relatório): `SubscriptionChangeKind::for(Price $current, Price $target, bool $onTrial)`
| devolve um case cujo `->value` é 'immediate' ou 'scheduled'.
*/

function kindPrice(string $frequency, int $amount): Price
{
    return new Price(['frequency' => $frequency, 'amount' => $amount, 'currency' => 'brl']);
}

describe('SubscriptionChangeKind::for (RN-1)', function () {
    it('classifica cada combinação', function (string $from, int $fromAmount, string $to, int $toAmount, bool $trial, string $expected) {
        $kind = SubscriptionChangeKind::for(kindPrice($from, $fromAmount), kindPrice($to, $toAmount), $trial);

        expect($kind->value)->toBe($expected);
    })->with([
        'mensal para anual (ciclo mais longo), valor maior' => ['monthly', 4990, 'yearly', 49900, false, 'immediate'],
        'mensal para anual, valor menor por ciclo' => ['monthly', 4990, 'yearly', 3000, false, 'immediate'],
        'anual para mensal (ciclo mais curto), valor menor' => ['yearly', 49900, 'monthly', 4990, false, 'scheduled'],
        'anual para mensal, valor maior' => ['yearly', 49900, 'monthly', 99900, false, 'scheduled'],
        'mesma frequência mensal, valor maior' => ['monthly', 2990, 'monthly', 4990, false, 'immediate'],
        'mesma frequência mensal, valor menor' => ['monthly', 4990, 'monthly', 2990, false, 'scheduled'],
        'mesma frequência mensal, valor igual' => ['monthly', 4990, 'monthly', 4990, false, 'scheduled'],
        'mesma frequência anual, valor maior' => ['yearly', 29900, 'yearly', 49900, false, 'immediate'],
        'mesma frequência anual, valor menor' => ['yearly', 49900, 'yearly', 29900, false, 'scheduled'],
        'trial, valor menor' => ['monthly', 4990, 'monthly', 2990, true, 'immediate'],
        'trial, anual para mensal' => ['yearly', 49900, 'monthly', 4990, true, 'immediate'],
        'trial, valor igual' => ['monthly', 4990, 'monthly', 4990, true, 'immediate'],
    ]);
});
