<?php

declare(strict_types=1);

namespace FrankProjects\UltimateWarfare\Entity\Research;

use FrankProjects\UltimateWarfare\Entity\Research;

final readonly class SpecialOperationsResearch extends Research
{
    public function __construct()
    {
        parent::__construct(
            name: 'Special Operations',
            image: 'tech_special_operations.gif',
            cost: 5000,
            timestamp: 3600,
            description: 'Unlock the ability of special operations',
            enabled: true,
            prerequisites: [ResearchLevel1Research::class],
        );
    }

    public function getSlug(): string
    {
        return 'special-operations';
    }
}
