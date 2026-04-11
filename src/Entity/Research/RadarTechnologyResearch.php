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
            cost: 40000,
            timestamp: 43200,
            description: 'Unlock the ability to construct radar stations on your regions',
            enabled: true,
            prerequisites: [ResearchLevel2Research::class],
        );
    }

    public function getSlug(): string
    {
        return 'radar-technology';
    }
}
