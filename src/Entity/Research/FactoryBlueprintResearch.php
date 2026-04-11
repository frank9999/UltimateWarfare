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
            cost: 25000,
            timestamp: 21600,
            description: 'Unlock the ability to construct factories on your regions',
            enabled: true,
            prerequisites: [ResearchLevel2Research::class],
        );
    }

    public function getSlug(): string
    {
        return 'factory-blueprint';
    }
}
