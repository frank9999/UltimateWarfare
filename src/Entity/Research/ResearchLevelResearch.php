<?php

declare(strict_types=1);

namespace FrankProjects\UltimateWarfare\Entity\Research;

use FrankProjects\UltimateWarfare\Entity\Research;

final readonly class ResearchLevelResearch extends Research
{
    public function __construct()
    {
        parent::__construct(
            name: 'Research',
            image: 'research.gif',
            description: 'Research new technological advancements',
            enabled: true,
            costPerLevel: [
                1 => 2500,
                2 => 15000,
                3 => 200000,
                4 => 900000,
                5 => 4000000,
                6 => 35000000,
                7 => 95000000,
                8 => 125000000,
                9 => 185000000,
                10 => 325000000,
            ],
            timestampPerLevel: [
                1 => 180,
                2 => 7200,
                3 => 36000,
                4 => 72000,
                5 => 160000,
                6 => 320000,
                7 => 700000,
                8 => 1500000,
                9 => 4000000,
                10 => 12000000,
            ],
        );
    }

    public function getSlug(): string
    {
        return 'research-level';
    }
}
