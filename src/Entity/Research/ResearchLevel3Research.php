<?php

declare(strict_types=1);

namespace FrankProjects\UltimateWarfare\Entity\Research;

use FrankProjects\UltimateWarfare\Entity\Research;

final readonly class ResearchLevel3Research extends Research
{
    public function __construct()
    {
        parent::__construct(
            name: 'Research Level 3',
            image: 'research.gif',
            cost: 200000,
            timestamp: 36000,
            description: 'Research new technological advancements',
            enabled: true,
            prerequisites: [ResearchLevel2Research::class],
        );
    }

    public function getSlug(): string
    {
        return 'research-level-3';
    }
}
