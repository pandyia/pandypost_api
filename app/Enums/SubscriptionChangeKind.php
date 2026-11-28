<?php

namespace App\Enums;

use App\Models\Price;

/**
 * Vigência de uma troca de assinatura (RN-1 da spec 024).
 */
enum SubscriptionChangeKind: string
{
    case IMMEDIATE = 'immediate';
    case SCHEDULED = 'scheduled';

    /**
     * Decide pela duração do ciclo e, se igual, pelo valor. Em trial a troca é sempre imediata.
     */
    public static function for(Price $current, Price $target, bool $onTrial): self
    {
        if ($onTrial) {
            return self::IMMEDIATE;
        }

        $currentRank = self::cycleRank($current->frequency);
        $targetRank = self::cycleRank($target->frequency);

        if ($targetRank !== $currentRank) {
            return $targetRank > $currentRank ? self::IMMEDIATE : self::SCHEDULED;
        }

        return $target->amount > $current->amount ? self::IMMEDIATE : self::SCHEDULED;
    }

    private static function cycleRank(BillingFrequency|string $frequency): int
    {
        $value = $frequency instanceof BillingFrequency ? $frequency->value : $frequency;

        return $value === BillingFrequency::YEARLY->value ? 2 : 1;
    }
}
