<?php

declare(strict_types=1);

namespace FrankProjects\UltimateWarfare\Entity\Operation;

use FrankProjects\UltimateWarfare\Entity\Operation;
use FrankProjects\UltimateWarfare\Entity\Research\SpyTechnologyResearch;
use FrankProjects\UltimateWarfare\Entity\WorldRegion;
use FrankProjects\UltimateWarfare\Service\OperationEngine\OperationProcessor\AdvancedSpy;

final readonly class AdvancedSpyOperation extends Operation
{
    public function __construct()
    {
        parent::__construct(
            name: 'Advanced Spy Operation',
            image: 'spy2.gif',
            cost: 10000,
            description: "Spy on an enemy country and retrieve detailed empire data"
                . " (cash, resources, population, regions, net worth) along with"
                . " their recent reports from the last 24 hours.\n\n"
                . "Cost scales with the number of regions the target controls.\n\n"
                . "\"If you fail, your enemy recieves an report about your spy attack\"",
            enabled: true,
            difficulty: 0.3,
            maxDistance: 6,
            researchClass: SpyTechnologyResearch::class,
            gameUnit: null,
            researchMinLevel: 2,
        );
    }

    public function getSlug(): string
    {
        return 'advanced-spy';
    }

    public function getProcessorClass(): string
    {
        return AdvancedSpy::class;
    }

    public function calculateCost(WorldRegion $targetRegion, int $amount): int
    {
        $targetPlayer = $targetRegion->getPlayer();
        $regionCount = $targetPlayer === null ? 1 : max(1, count($targetPlayer->getWorldRegions()));

        return $this->getCost() * $regionCount;
    }
}
