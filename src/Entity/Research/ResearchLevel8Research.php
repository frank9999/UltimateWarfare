<?php

declare(strict_types=1);

namespace FrankProjects\UltimateWarfare\Entity\Research;

use FrankProjects\UltimateWarfare\Entity\Research;

final readonly class ResearchLevel8Research extends Research
{
    public function __construct()
    {
        parent::__construct(
            name: 'Research Level 8',
            image: 'research.gif',
            cost: 125000000,
            timestamp: 1500000,
            description: 'Research new technological advancements',
            enabled: true,
            prerequisites: [ResearchLevel7Research::class],
        );
    }

    public function getSlug(): string
    {
        return 'research-level-8';
    }
}
