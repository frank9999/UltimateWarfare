<?php

declare(strict_types=1);

namespace FrankProjects\UltimateWarfare\Entity\Research;

use FrankProjects\UltimateWarfare\Entity\Research;

final readonly class ResearchLevel4Research extends Research
{
    public function __construct()
    {
        parent::__construct(
            name: 'Research Level 4',
            image: 'research.gif',
            cost: 900000,
            timestamp: 72000,
            description: 'Research new technological advancements',
            enabled: true,
            prerequisites: [ResearchLevel3Research::class],
        );
    }

    public function getSlug(): string
    {
        return 'research-level-4';
    }
}
