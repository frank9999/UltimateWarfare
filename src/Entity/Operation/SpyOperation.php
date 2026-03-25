<?php

declare(strict_types=1);

namespace FrankProjects\UltimateWarfare\Entity\Operation;

use FrankProjects\UltimateWarfare\Entity\Enum\GameUnitEnum;
use FrankProjects\UltimateWarfare\Entity\Operation;
use FrankProjects\UltimateWarfare\Entity\Research\SpyTechnologyResearch;
use FrankProjects\UltimateWarfare\Service\OperationEngine\OperationProcessor\Spy;

final readonly class SpyOperation extends Operation
{
    public function __construct()
    {
        parent::__construct(
            name: 'Spy Technology',
            image: 'spy.gif',
            cost: 150,
            description: "Spy on an enemy country and retrieve important data"
                . " like buildings and units.\n\n"
                . "\"If you fail, your enemy recieves an report about your spy attack\"",
            enabled: true,
            difficulty: 0.1,
            maxDistance: 3,
            researchClass: SpyTechnologyResearch::class,
            gameUnit: GameUnitEnum::SPY,
        );
    }

    public function getSlug(): string
    {
        return 'spy';
    }

    public function getProcessorClass(): string
    {
        return Spy::class;
    }
}
