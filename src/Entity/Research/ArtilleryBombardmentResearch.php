<?php

declare(strict_types=1);

namespace FrankProjects\UltimateWarfare\Entity\Research;

use FrankProjects\UltimateWarfare\Entity\Research;

final readonly class ArtilleryBombardmentResearch extends Research
{
    public function __construct()
    {
        parent::__construct(
            name: 'Special Operation: Artillery Bombardment',
            image: 'tech_special_operations.gif',
            description: 'Unlock the ability to launch artillery bombardment operations',
            enabled: true,
            costPerLevel: [1 => 25000],
            timestampPerLevel: [1 => 7200],
            prerequisitesPerLevel: [
                1 => [ResearchTierResearch::class => 2],
            ],
        );
    }

    public function getSlug(): string
    {
        return 'special-operation-artillery-bombardment';
    }
}
