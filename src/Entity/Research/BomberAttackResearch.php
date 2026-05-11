<?php

declare(strict_types=1);

namespace FrankProjects\UltimateWarfare\Entity\Research;

use FrankProjects\UltimateWarfare\Entity\Research;

final readonly class BomberAttackResearch extends Research
{
    public function __construct()
    {
        parent::__construct(
            name: 'Special Operation: Bomber Attack',
            image: 'tech_special_operations.gif',
            description: 'Unlock the ability to launch bomber attack operations',
            enabled: true,
            costPerLevel: [1 => 100000],
            timestampPerLevel: [1 => 15000],
            prerequisitesPerLevel: [
                1 => [ResearchTierResearch::class => 3],
            ],
        );
    }

    public function getSlug(): string
    {
        return 'special-operation-bomber-attack';
    }
}
