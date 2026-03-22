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
            cost: 500000000,
            timestamp: 604800,
            description: 'Unlocks the ability of Nuclear weapons',
            enabled: true,
            prerequisites: [ResearchLevel7Research::class],
        );
    }

    public function getSlug(): string
    {
        return 'nuclear-technology';
    }
}
