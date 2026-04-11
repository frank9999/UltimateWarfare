<?php

declare(strict_types=1);

namespace FrankProjects\UltimateWarfare\Entity\Research;

use FrankProjects\UltimateWarfare\Entity\Research;

final readonly class NavalBombardmentResearch extends Research
{
    public function __construct()
    {
        parent::__construct(
            name: 'Naval Bombardment',
            image: 'research.gif',
            cost: 60000,
            timestamp: 129600,
            description: 'Unlock the ability to perform naval bombardment operations using cruisers',
            enabled: true,
            prerequisites: [ResearchLevel2Research::class],
        );
    }

    public function getSlug(): string
    {
        return 'naval-bombardment';
    }
}
