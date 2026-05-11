<?php

declare(strict_types=1);

namespace FrankProjects\UltimateWarfare\Entity\Research;

use FrankProjects\UltimateWarfare\Entity\Research;

final readonly class DefensiveNetworkResearch extends Research
{
    public function __construct()
    {
        parent::__construct(
            name: 'Defensive Network',
            image: 'research.gif',
            description: 'Build a defensive network that reduces incoming damage to your troops',
            enabled: true,
            costPerLevel: [
                1 => 5000,
                2 => 25000,
                3 => 100000,
                4 => 500000,
                5 => 2000000,
            ],
            timestampPerLevel: [
                1 => 3600,
                2 => 7200,
                3 => 15000,
                4 => 45000,
                5 => 100000,
            ],
            prerequisitesPerLevel: [
                1 => [ResearchTierResearch::class => 1],
                2 => [ResearchTierResearch::class => 1],
                3 => [ResearchTierResearch::class => 1],
                4 => [ResearchTierResearch::class => 1],
                5 => [ResearchTierResearch::class => 1],
            ],
        );
    }

    public function getSlug(): string
    {
        return 'defensive-network';
    }
}
