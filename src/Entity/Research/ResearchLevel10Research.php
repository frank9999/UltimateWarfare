<?php

declare(strict_types=1);

namespace FrankProjects\UltimateWarfare\Entity\Research;

use FrankProjects\UltimateWarfare\Entity\Research;

final readonly class ResearchLevel10Research extends Research
{
    public function __construct()
    {
        parent::__construct(
            name: 'Research Level 10',
            image: 'research.gif',
            cost: 325000000,
            timestamp: 12000000,
            description: 'Research new technological advancements',
            enabled: true,
            prerequisites: [ResearchLevel9Research::class],
        );
    }

    public function getSlug(): string
    {
        return 'research-level-10';
    }
}
