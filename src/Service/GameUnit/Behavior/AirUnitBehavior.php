<?php

declare(strict_types=1);

namespace FrankProjects\UltimateWarfare\Service\GameUnit\Behavior;

use FrankProjects\UltimateWarfare\Entity\Enum\GameUnitEnum;
use FrankProjects\UltimateWarfare\Entity\Player;
use FrankProjects\UltimateWarfare\Entity\WorldRegion;
use FrankProjects\UltimateWarfare\Service\GameUnit\AbstractGameUnitBehavior;

class AirUnitBehavior extends AbstractGameUnitBehavior
{
    public function canBuild(WorldRegion $region, Player $player): bool
    {
        foreach ($region->getWorldRegionUnits() as $regionUnit) {
            if ($regionUnit->getGameUnit() === GameUnitEnum::AIRPORT) {
                return true;
            }
        }

        return false;
    }

    public function getBuildRequirementDescription(): string
    {
        return 'Requires an Airfield';
    }

    public function canMoveTo(WorldRegion $from, WorldRegion $to): bool
    {
        // Air units can move anywhere
        return true;
    }
}
