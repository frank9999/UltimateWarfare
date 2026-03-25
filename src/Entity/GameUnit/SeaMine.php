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
use FrankProjects\UltimateWarfare\Entity\BattleStats\SeaBattleStats;

final class SeaMine extends GameUnit
{
    public function __construct()
    {
        parent::__construct(
            name: 'Sea Mine',
            nameMulti: 'Sea Mines',
            rowName: 'sea_mine',
            image: 'seamine.gif',
            netWorth: 0,
            timestamp: 7200,
            description: 'Seamines have 0.01% chance of sinking an enemy ship.',
            gameUnitCategory: GameUnitCategory::DEFENSE_BUILDINGS,
            behaviorClass: null,
            cost: new Cost(cash: 3500, food: 0, wood: 0, steel: 1),
            income: new Income(cash: 0, food: 0, wood: 0, steel: 0),
            upkeep: new Upkeep(cash: 0, food: 0, wood: 0, steel: 0),
            battleStats: new BattleStats(
                health: 10,
                armor: 1,
                seaBattleStats: new SeaBattleStats(defence: 5, defenceSpeed: 500),
            ),
        );
    }

    public function getGameUnitEnum(): GameUnitEnum
    {
        return GameUnitEnum::SEA_MINE;
    }
}
