<?php

declare(strict_types=1);

namespace App\Characters\Infrastructure\Presenter;

use App\Characters\Domain\Expeditions\Difficulty;
use App\Characters\Domain\Expeditions\Expedition;
use App\Characters\Domain\Expeditions\Zone;

final class ExpeditionPresenter
{
    public static function zone(Zone $z): array
    {
        $boss = $z->boss();

        return ['id' => $z->value, 'name' => $z->label(), 'boss' => ['id' => $boss->value, 'name' => $boss->name(), 'emoji' => $boss->emoji()], 'level' => $boss->recommendedLevel()];
    }

    public static function difficulty(Difficulty $d): array
    {
        return ['id' => $d->value, 'name' => $d->label(), 'extra_levels' => $d->extraLevels(), 'multiplier' => $d->rewardMultiplier()];
    }

    public static function expedition(Expedition $e, \DateTimeImmutable $now): array
    {
        return [
            'id'          => $e->getId(),
            'zone'        => self::zone($e->getZone()),
            'difficulty'  => self::difficulty($e->getDifficulty()),
            'limit_hours' => $e->getLimitHours(),
            'autosell'    => $e->getAutosell() === null ? null : GearPresenter::rarity($e->getAutosell()),
            'started_at'  => $e->getStartedAt()->format(DATE_ATOM),
            'elapsed'     => $e->elapsedSeconds($now),
            'claimed_at'  => $e->getClaimedAt()?->format(DATE_ATOM),
            'summary'     => $e->getSummary(),
        ];
    }
}
