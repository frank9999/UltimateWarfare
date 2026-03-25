<?php

declare(strict_types=1);

namespace FrankProjects\UltimateWarfare\Entity\Operation;

use FrankProjects\UltimateWarfare\Entity\Enum\GameUnitEnum;
use FrankProjects\UltimateWarfare\Entity\Operation;
use FrankProjects\UltimateWarfare\Entity\Research\SpecialOperationsResearch;
use FrankProjects\UltimateWarfare\Service\OperationEngine\OperationProcessor\SubmarineAttack;

final readonly class SubmarineAttackOperation extends Operation
{
    public function __construct()
    {
        parent::__construct(
            name: 'Submarine Attack',
            image: 'submarine.gif',
            cost: 25000,
            description: "Send an Submarine behind enemy lines and sink enemy ships!"
                . " Every submarine is able to sink at least 1 ship!\n(Navy Only)\n\n"
                . "\"Your enemy will recieve an report about this attack if you succeed and fail."
                . " The report includes your empire name if you fail, else not.\"",
            enabled: true,
            difficulty: 0.5,
            maxDistance: 4,
            researchClass: SpecialOperationsResearch::class,
            gameUnit: GameUnitEnum::SUBMARINE,
        );
    }

    public function getSlug(): string
    {
        return 'submarine-attack';
    }

    public function getProcessorClass(): string
    {
        return SubmarineAttack::class;
    }
}
