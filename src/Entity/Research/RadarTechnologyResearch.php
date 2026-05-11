<?php

declare(strict_types=1);

namespace FrankProjects\UltimateWarfare\Entity\Research;

use FrankProjects\UltimateWarfare\Entity\Research;

final readonly class RadarTechnologyResearch extends Research
{
    public function __construct()
    {
        parent::__construct(
            name: 'Radar Technology',
            image: 'research.gif',
            description: 'Unlock the ability to construct radar stations on your regions',
            enabled: true,
            costPerLevel: [1 => 40000],
            timestampPerLevel: [1 => 43200],
            prerequisitesPerLevel: [
                1 => [ResearchTierResearch::class => 2],
            ],
        );
    }

    public function getSlug(): string
    {
        return 'radar-technology';
    }
}
