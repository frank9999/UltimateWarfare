<?php

declare(strict_types=1);

namespace FrankProjects\UltimateWarfare\Entity\Research;

use FrankProjects\UltimateWarfare\Entity\Research;

final readonly class BallisticMissileTechnologyResearch extends Research
{
    public function __construct()
    {
        parent::__construct(
            name: 'Ballistic Missile Technology',
            image: 'research.gif',
            cost: 75000,
            timestamp: 172800,
            description: 'Unlock the ability to construct missile silos, build rockets,'
                . ' and perform missile attack operations',
            enabled: true,
            prerequisites: [ResearchLevel2Research::class],
        );
    }

    public function getSlug(): string
    {
        return 'ballistic-missile-technology';
    }
}
