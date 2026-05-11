<?php

declare(strict_types=1);

namespace FrankProjects\UltimateWarfare\Entity\Research;

use FrankProjects\UltimateWarfare\Entity\Research;

final readonly class FactoryBlueprintResearch extends Research
{
    public function __construct()
    {
        parent::__construct(
            name: 'Factory Blueprint',
            image: 'research.gif',
            description: 'Unlock the ability to construct factories on your regions',
            enabled: true,
            costPerLevel: [1 => 25000],
            timestampPerLevel: [1 => 21600],
            prerequisitesPerLevel: [
                1 => [ResearchLevelResearch::class => 2],
            ],
        );
    }

    public function getSlug(): string
    {
        return 'factory-blueprint';
    }
}
