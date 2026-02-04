<?php

declare(strict_types=1);

namespace FrankProjects\UltimateWarfare\Service\GameUnit\Behavior;

use FrankProjects\UltimateWarfare\Entity\Player;
use FrankProjects\UltimateWarfare\Entity\WorldRegion;
use FrankProjects\UltimateWarfare\Repository\WorldRegionRepository;
use FrankProjects\UltimateWarfare\Service\GameUnit\AbstractGameUnitBehavior;

class NavalUnitBehavior extends AbstractGameUnitBehavior
{
    private const REQUIRED_BUILDING = 'Harbor';

    public function __construct(
        private WorldRegionRepository $regionRepository
    ) {}

    public function canBuild(WorldRegion $region, Player $player): bool
    {
        // Check 1: Must have Harbor
        $hasHarbor = false;
        foreach ($region->getWorldRegionUnits() as $regionUnit) {
            if ($regionUnit->getGameUnit()->getName() === self::REQUIRED_BUILDING) {
                $hasHarbor = true;
                break;
            }
        }

        if (!$hasHarbor) {
            return false;
        }

        // Check 2: Must be adjacent to water
        return $this->isAdjacentToWater($region);
    }

    public function getBuildRequirementDescription(): string
    {
        return 'Requires a Harbor and must be adjacent to water';
    }

    private function isAdjacentToWater(WorldRegion $region): bool
    {
        $adjacentRegions = $this->regionRepository->findAdjacentRegions(
            $region->getX(),
            $region->getY(),
            $region->getWorld()
        );

        foreach ($adjacentRegions as $adjacent) {
            if ($adjacent->getType() === WorldRegion::TYPE_WATER) {
                return true;
            }
        }

        return false;
    }

    public function canMoveTo(WorldRegion $from, WorldRegion $to): bool
    {
        // Naval units can only move to water or coastal regions
        return $to->getType() === WorldRegion::TYPE_WATER
            || $to->getType() === WorldRegion::TYPE_BEACH;
    }
}