<?php

declare(strict_types=1);

namespace FrankProjects\UltimateWarfare\Entity\Research;

use FrankProjects\UltimateWarfare\Entity\Research;

final readonly class SubmarineTechnologyResearch extends Research
{
    public function __construct()
    {
        parent::__construct(
            name: 'Submarine Technology',
            image: 'research.gif',
            description: 'Unlock the ability to construct submarines in harbors'
                . ' and perform submarine attack operations',
            enabled: true,
            costPerLevel: [1 => 50000],
            timestampPerLevel: [1 => 86400],
            prerequisitesPerLevel: [
                1 => [ResearchLevelResearch::class => 2],
            ],
        );
    }

    public function getSlug(): string
    {
        return 'submarine-technology';
    }
}
