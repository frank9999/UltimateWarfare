<?php

declare(strict_types=1);

namespace FrankProjects\UltimateWarfare\Entity\GameUnit;

use FrankProjects\UltimateWarfare\Entity\Enum\GameUnitCategory;
use FrankProjects\UltimateWarfare\Entity\Enum\GameUnitEnum;
use FrankProjects\UltimateWarfare\Entity\GameUnit;
use FrankProjects\UltimateWarfare\Entity\BattleStats;
use FrankProjects\UltimateWarfare\Entity\GameResources\Cost;
use FrankProjects\UltimateWarfare\Entity\GameResources\Income;
use FrankProjects\UltimateWarfare\Entity\GameResources\Upkeep;

final class Guard extends GameUnit
{
    public function __construct()
    {
        parent::__construct(
            name: 'Guard',
            nameMulti: 'Guards',
            rowName: 'guard',
            image: 'guard.gif',
            netWorth: 1,
            timestamp: 1800,
            description: 'Guards will protect your countries against enemy Operations',
            gameUnitCategory: GameUnitCategory::SPECIAL_UNITS,
            behaviorClass: null,
            cost: new Cost(cash: 500, food: 0, wood: 1, steel: 2),
            income: new Income(cash: 0, food: 0, wood: 0, steel: 0),
            upkeep: new Upkeep(cash: 0, food: 0, wood: 0, steel: 0),
            battleStats: new BattleStats(health: 0, armor: 0),
        );
    }

    public function getGameUnitEnum(): GameUnitEnum
    {
        return GameUnitEnum::GUARD;
    }
}
