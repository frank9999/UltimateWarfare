<?php

declare(strict_types=1);

namespace FrankProjects\UltimateWarfare\Entity\Research;

use FrankProjects\UltimateWarfare\Entity\Research;

final readonly class ResearchLevel7Research extends Research
{
    public function __construct()
    {
        parent::__construct(
            name: 'Research Level 7',
            image: 'research.gif',
            cost: 95000000,
            timestamp: 700000,
            description: 'Research new technological advancements',
            enabled: true,
            prerequisites: [ResearchLevel6Research::class],
        );
    }

    public function getSlug(): string
    {
        return 'research-level-7';
    }
}
