<?php

declare(strict_types=1);

namespace FrankProjects\UltimateWarfare\Entity\Research;

use FrankProjects\UltimateWarfare\Entity\Research;

final readonly class ResearchLevel6Research extends Research
{
    public function __construct()
    {
        parent::__construct(
            name: 'Research Level 6',
            image: 'research.gif',
            cost: 35000000,
            timestamp: 320000,
            description: 'Research new technological advancements',
            enabled: true,
            prerequisites: [ResearchLevel5Research::class],
        );
    }

    public function getSlug(): string
    {
        return 'research-level-6';
    }
}
