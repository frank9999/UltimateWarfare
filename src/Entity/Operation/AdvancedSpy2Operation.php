<?php

declare(strict_types=1);

namespace FrankProjects\UltimateWarfare\Entity\Operation;

use FrankProjects\UltimateWarfare\Entity\Operation;
use FrankProjects\UltimateWarfare\Service\OperationEngine\OperationProcessor\AdvancedSpy2;

final readonly class AdvancedSpy2Operation extends Operation
{
    public function __construct()
    {
        parent::__construct(
            name: 'Advanced Spy Operation II',
            image: 'spy2.gif',
            cost: 500,
            description: "Spy on an enemy country and retrieve important data"
                . " like empire reports.\n\n"
                . "\"If you fail, your enemy recieves an report about your spy attack\"",
            enabled: true,
            difficulty: 0.5,
            maxDistance: 9,
            researchSlug: 'advanced-spy-technology',
            gameUnitId: 407,
        );
    }

    public function getSlug(): string
    {
        return 'advanced-spy-2';
    }

    public function getProcessorClass(): string
    {
        return AdvancedSpy2::class;
    }
}
