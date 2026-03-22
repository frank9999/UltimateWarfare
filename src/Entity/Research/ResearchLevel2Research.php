<?php

declare(strict_types=1);

namespace FrankProjects\UltimateWarfare\Entity\Research;

use FrankProjects\UltimateWarfare\Entity\Research;

final readonly class ResearchLevel2Research extends Research
{
    public function __construct()
    {
        parent::__construct(
            name: 'Research Level 2',
            image: 'research.gif',
            cost: 15000,
            timestamp: 7200,
            description: 'Research new technological advancements',
            enabled: true,
            prerequisites: [ResearchLevel1Research::class],
        );
    }

    public function getSlug(): string
    {
        return 'research-level-2';
    }
}
