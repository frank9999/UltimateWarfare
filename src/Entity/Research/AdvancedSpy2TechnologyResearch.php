<?php

declare(strict_types=1);

namespace FrankProjects\UltimateWarfare\Entity\Research;

use FrankProjects\UltimateWarfare\Entity\Research;

final readonly class AdvancedSpy2TechnologyResearch extends Research
{
    public function __construct()
    {
        parent::__construct(
            name: 'Advanced Spy Technology 2',
            image: 'spy2.gif',
            cost: 10000000,
            timestamp: 300000,
            description: 'Advanced Spy Technology 2 allows you to get even more information about an enemy country',
            enabled: true,
            prerequisites: [AdvancedSpyTechnologyResearch::class, SpecialOperations3Research::class],
        );
    }

    public function getSlug(): string
    {
        return 'advanced-spy-2-technology';
    }
}
