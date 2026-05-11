<?php

declare(strict_types=1);

namespace FrankProjects\UltimateWarfare\Entity\Research;

use FrankProjects\UltimateWarfare\Entity\Research;

final readonly class SpecialOperationsResearch extends Research
{
    public function __construct()
    {
        parent::__construct(
            name: 'Special Operations',
            image: 'tech_special_operations.gif',
            description: 'Unlock the ability of more advanced special operations',
            enabled: true,
            costPerLevel: [
                1 => 5000,
                2 => 25000,
                3 => 100000,
                4 => 2500000,
            ],
            timestampPerLevel: [
                1 => 3600,
                2 => 7200,
                3 => 15000,
                4 => 45000,
            ],
            prerequisitesPerLevel: [
                1 => [ResearchLevelResearch::class => 1],
                2 => [ResearchLevelResearch::class => 2],
                3 => [ResearchLevelResearch::class => 3],
                4 => [ResearchLevelResearch::class => 4],
            ],
        );
    }

    public function getSlug(): string
    {
        return 'special-operations';
    }
}
