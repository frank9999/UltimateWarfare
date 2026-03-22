<?php

declare(strict_types=1);

namespace FrankProjects\UltimateWarfare\Entity\Operation;

use FrankProjects\UltimateWarfare\Entity\Operation;
use FrankProjects\UltimateWarfare\Service\OperationEngine\OperationProcessor\AdvancedSpy;

final readonly class AdvancedSpyOperation extends Operation
{
    public function __construct()
    {
        parent::__construct(
            name: 'Advanced Spy Operation',
            image: 'spy2.gif',
            cost: 250,
            description: "Spy on an enemy country and retrieve important data"
                . " like cash and other empire data.\n\n"
                . "\"If you fail, your enemy recieves an report about your spy attack\"",
            enabled: true,
            difficulty: 0.3,
            maxDistance: 6,
            researchSlug: 'advanced-spy-technology',
            gameUnitId: 407,
        );
    }

    public function getSlug(): string
    {
        return 'advanced-spy';
    }

    public function getProcessorClass(): string
    {
        return AdvancedSpy::class;
    }
}
