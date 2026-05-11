<?php

declare(strict_types=1);

namespace FrankProjects\UltimateWarfare\Entity\Research;

use FrankProjects\UltimateWarfare\Entity\Research;

final readonly class OreExtractionImprovementsResearch extends Research
{
    public function __construct()
    {
        parent::__construct(
            name: 'Ore Extraction Improvements',
            image: 'research.gif',
            description: 'Increase metal income by 10% per level',
            enabled: true,
            costPerLevel: [
                1 => 30000,
                2 => 60000,
                3 => 120000,
                4 => 250000,
                5 => 500000,
                6 => 1000000,
                7 => 2000000,
                8 => 4000000,
                9 => 8000000,
                10 => 15000000,
            ],
            timestampPerLevel: [
                1 => 21600,
                2 => 43200,
                3 => 86400,
                4 => 172800,
                5 => 345600,
                6 => 691200,
                7 => 1382400,
                8 => 2764800,
                9 => 5529600,
                10 => 11059200,
            ],
            prerequisitesPerLevel: [
                1 => [ResearchTierResearch::class => 2],
                2 => [ResearchTierResearch::class => 2],
                3 => [ResearchTierResearch::class => 2],
                4 => [ResearchTierResearch::class => 2],
                5 => [ResearchTierResearch::class => 2],
                6 => [ResearchTierResearch::class => 2],
                7 => [ResearchTierResearch::class => 2],
                8 => [ResearchTierResearch::class => 2],
                9 => [ResearchTierResearch::class => 2],
                10 => [ResearchTierResearch::class => 2],
            ],
        );
    }

    public function getSlug(): string
    {
        return 'ore-extraction-improvements';
    }
}
