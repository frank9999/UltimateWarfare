<?php

declare(strict_types=1);

namespace FrankProjects\UltimateWarfare\Entity\Research;

use FrankProjects\UltimateWarfare\Entity\Research;

final readonly class NuclearTechnologyResearch extends Research
{
    public function __construct()
    {
        parent::__construct(
            name: 'Nuclear Technology',
            image: 'tech_nuclear.gif',
            description: 'Unlocks the ability of Nuclear weapons',
            enabled: true,
            costPerLevel: [1 => 500000000],
            timestampPerLevel: [1 => 604800],
            prerequisitesPerLevel: [
                1 => [ResearchLevelResearch::class => 7],
            ],
        );
    }

    public function getSlug(): string
    {
        return 'nuclear-technology';
    }
}
