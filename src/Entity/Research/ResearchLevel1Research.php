<?php

declare(strict_types=1);

namespace FrankProjects\UltimateWarfare\Entity\Research;

use FrankProjects\UltimateWarfare\Entity\Research;

final readonly class ResearchLevel1Research extends Research
{
    public function __construct()
    {
        parent::__construct(
            name: 'Research',
            image: 'research.gif',
            cost: 2500,
            timestamp: 180,
            description: 'Research new technological advancements',
            enabled: true,
        );
    }

    public function getSlug(): string
    {
        return 'research-level-1';
    }
}
