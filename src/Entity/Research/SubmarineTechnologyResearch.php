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
            cost: 50000,
            timestamp: 86400,
            description: 'Unlock the ability to construct submarines in harbors'
                . ' and perform submarine attack operations',
            enabled: true,
            prerequisites: [ResearchLevel2Research::class],
        );
    }

    public function getSlug(): string
    {
        return 'submarine-technology';
    }
}
