<?php

declare(strict_types=1);

namespace FrankProjects\UltimateWarfare\Entity\Research;

use FrankProjects\UltimateWarfare\Entity\Research;

final readonly class AdvancedOpticsResearch extends Research
{
    public function __construct()
    {
        parent::__construct(
            name: 'Advanced Optics',
            image: 'research.gif',
            cost: 35000,
            timestamp: 86400,
            description: 'Unlock the ability to train snipers and perform sniper attack operations',
            enabled: true,
            prerequisites: [ResearchLevel2Research::class],
        );
    }

    public function getSlug(): string
    {
        return 'advanced-optics';
    }
}
