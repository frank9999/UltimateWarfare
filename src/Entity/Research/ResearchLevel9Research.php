<?php

declare(strict_types=1);

namespace FrankProjects\UltimateWarfare\Entity\Research;

use FrankProjects\UltimateWarfare\Entity\Research;

final readonly class ResearchLevel9Research extends Research
{
    public function __construct()
    {
        parent::__construct(
            name: 'Research Level 9',
            image: 'research.gif',
            cost: 185000000,
            timestamp: 4000000,
            description: 'Research new technological advancements',
            enabled: true,
            prerequisites: [ResearchLevel8Research::class],
        );
    }

    public function getSlug(): string
    {
        return 'research-level-9';
    }
}
