<?php

declare(strict_types=1);

namespace FrankProjects\UltimateWarfare\Entity\Operation;

use FrankProjects\UltimateWarfare\Entity\Enum\GameUnitEnum;
use FrankProjects\UltimateWarfare\Entity\Operation;
use FrankProjects\UltimateWarfare\Entity\Research\SpecialOperations3Research;
use FrankProjects\UltimateWarfare\Service\OperationEngine\OperationProcessor\BomberAttack;

final readonly class BomberAttackOperation extends Operation
{
    public function __construct()
    {
        parent::__construct(
            name: 'Bomber Attack',
            image: 'op_stealthbombing.gif',
            cost: 15000,
            description: "Launch a bombing run against an enemy country with your Bombers."
                . " Every Bomber is able to hit 5 buildings"
                . " (train stations, airports or harbors).\n(Airforce Only)\n\n"
                . "\"Your enemy will receive a report about this attack if you succeed or fail."
                . " But it will hide your name if you succeed.\"",
            enabled: true,
            difficulty: 0.5,
            maxDistance: 4,
            researchClass: SpecialOperations3Research::class,
            gameUnit: GameUnitEnum::BOMBER,
        );
    }

    public function getSlug(): string
    {
        return 'bomber-attack';
    }

    public function getProcessorClass(): string
    {
        return BomberAttack::class;
    }
}
