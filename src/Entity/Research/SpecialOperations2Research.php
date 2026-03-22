<?php

declare(strict_types=1);

namespace FrankProjects\UltimateWarfare\Entity\Research;

use FrankProjects\UltimateWarfare\Entity\Research;

final readonly class SpecialOperations2Research extends Research
{
    public function __construct()
    {
        parent::__construct(
            name: 'Special Operations 2',
            image: 'tech_special_operations.gif',
            cost: 25000,
            timestamp: 7200,
            description: 'Unlock the ability of more advanced special operations',
            enabled: true,
            prerequisites: [ResearchLevel2Research::class, SpecialOperationsResearch::class],
        );
    }

    public function getSlug(): string
    {
        return 'special-operations-2';
    }
}
