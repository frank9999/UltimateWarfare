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

final class Spy extends GameUnit
{
    public function __construct()
    {
        parent::__construct(
            name: 'Spy',
            nameMulti: 'Spies',
            rowName: 'spy',
            image: 'spy.gif',
            netWorth: 1,
            timestamp: 1800,
            description: 'Spies can be used to spy on enemy countries. '
                . 'The more spies you send, the more information you gain',
            gameUnitCategory: GameUnitCategory::SPECIAL_UNITS,
            behaviorClass: null,
            cost: new Cost(cash: 2500, food: 0, wood: 1, steel: 5),
            income: new Income(cash: 0, food: 0, wood: 0, steel: 0),
            upkeep: new Upkeep(cash: 0, food: 0, wood: 0, steel: 0),
            battleStats: new BattleStats(health: 0, armor: 0),
        );
    }

    public function getId(): GameUnitEnum
    {
        return GameUnitEnum::SPY;
    }
}
