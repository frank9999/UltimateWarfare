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
            cost: 20000,
            timestamp: 7200,
            description: 'Spy technology can be used to spy on enemy countries',
            enabled: true,
            prerequisites: [SpecialOperationsResearch::class],
        );
    }

    public function getSlug(): string
    {
        return 'spy-technology';
    }
}
