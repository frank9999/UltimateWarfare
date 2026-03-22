<?php

declare(strict_types=1);

namespace FrankProjects\UltimateWarfare\Entity\Research;

use FrankProjects\UltimateWarfare\Entity\Research;

final readonly class ResearchLevel5Research extends Research
{
    public function __construct()
    {
        parent::__construct(
            name: 'Research Level 5',
            image: 'research.gif',
            cost: 4000000,
            timestamp: 160000,
            description: 'Research new technological advancements',
            enabled: true,
            prerequisites: [ResearchLevel4Research::class],
        );
    }

    public function getSlug(): string
    {
        return 'research-level-5';
    }
}
