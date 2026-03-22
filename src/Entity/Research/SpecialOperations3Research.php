<?php

declare(strict_types=1);

namespace FrankProjects\UltimateWarfare\Entity\Research;

use FrankProjects\UltimateWarfare\Entity\Research;

final readonly class SpecialOperations3Research extends Research
{
    public function __construct()
    {
        parent::__construct(
            name: 'Special Operations 3',
            image: 'tech_special_operations.gif',
            cost: 100000,
            timestamp: 15000,
            description: 'Unlock the ability of more advanced special operations',
            enabled: true,
            prerequisites: [ResearchLevel3Research::class, SpecialOperations2Research::class],
        );
    }

    public function getSlug(): string
    {
        return 'special-operations-3';
    }
}
