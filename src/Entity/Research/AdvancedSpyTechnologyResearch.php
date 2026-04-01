<?php

declare(strict_types=1);

namespace FrankProjects\UltimateWarfare\Entity\Research;

use FrankProjects\UltimateWarfare\Entity\Research;

final readonly class AdvancedSpyTechnologyResearch extends Research
{
    public function __construct()
    {
        parent::__construct(
            name: 'Advanced Spy Technology',
            image: 'spy2.gif',
            cost: 5000000,
            timestamp: 150000,
            description: 'Advanced Spy Technology allows you to get more information about an enemy country',
            enabled: true,
            prerequisites: [SpyTechnologyResearch::class, SpecialOperations2Research::class],
        );
    }

    public function getSlug(): string
    {
        return 'advanced-spy-technology';
    }
}
