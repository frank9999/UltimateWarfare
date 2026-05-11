<?php

declare(strict_types=1);

namespace FrankProjects\UltimateWarfare\Entity\Research;

use FrankProjects\UltimateWarfare\Entity\Research;

final readonly class StrategicBomberAttackResearch extends Research
{
    public function __construct()
    {
        parent::__construct(
            name: 'Special Operation: Strategic Bomber Attack',
            image: 'tech_special_operations.gif',
            description: 'Unlock the ability to launch strategic bomber attack operations',
            enabled: true,
            costPerLevel: [1 => 2500000],
            timestampPerLevel: [1 => 45000],
            prerequisitesPerLevel: [
                1 => [ResearchTierResearch::class => 4],
            ],
        );
    }

    public function getSlug(): string
    {
        return 'special-operation-strategic-bomber-attack';
    }
}
