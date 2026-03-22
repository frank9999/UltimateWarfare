<?php

declare(strict_types=1);

namespace FrankProjects\UltimateWarfare\Entity\Research;

use FrankProjects\UltimateWarfare\Entity\Research;

final readonly class SpecialOperations4Research extends Research
{
    public function __construct()
    {
        parent::__construct(
            name: 'Special Operations 4',
            image: 'tech_special_operations.gif',
            cost: 2500000,
            timestamp: 45000,
            description: 'Unlock the ability of more advanced special operations',
            enabled: true,
            prerequisites: [ResearchLevel4Research::class, SpecialOperations3Research::class],
        );
    }

    public function getSlug(): string
    {
        return 'special-operations-4';
    }
}
