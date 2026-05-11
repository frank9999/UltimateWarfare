<?php

declare(strict_types=1);

namespace FrankProjects\UltimateWarfare\Entity\Research;

use FrankProjects\UltimateWarfare\Entity\Research;

final readonly class SpyTechnologyResearch extends Research
{
    public function __construct()
    {
        parent::__construct(
            name: 'Spy Technology',
            image: 'spy.gif',
            description: 'Spy technology can be used to spy on enemy countries and retrieve increasingly detailed data',
            enabled: true,
            costPerLevel: [
                1 => 20000,
                2 => 5000000,
                3 => 10000000,
            ],
            timestampPerLevel: [
                1 => 7200,
                2 => 150000,
                3 => 300000,
            ],
            prerequisitesPerLevel: [
                1 => [SpecialOperationsResearch::class => 1],
                2 => [SpecialOperationsResearch::class => 2],
                3 => [SpecialOperationsResearch::class => 3],
            ],
        );
    }

    public function getSlug(): string
    {
        return 'spy-technology';
    }
}
