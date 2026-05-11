<?php

declare(strict_types=1);

namespace FrankProjects\UltimateWarfare\Entity\Operation;

use FrankProjects\UltimateWarfare\Entity\Enum\GameUnitEnum;
use FrankProjects\UltimateWarfare\Entity\Operation;
use FrankProjects\UltimateWarfare\Entity\Research\StrategicBomberAttackResearch;
use FrankProjects\UltimateWarfare\Service\OperationEngine\OperationProcessor\StrategicBomberAttack;

final readonly class StrategicBomberAttackOperation extends Operation
{
    public function __construct()
    {
        parent::__construct(
            name: 'Strategic Bomber Attack',
            image: 'op_stealthbombing.gif',
            cost: 45000,
            description: "Launch a strategic bombing run against an enemy country with your Strategic Bombers."
                . " Every Strategic Bomber is able to hit 5 buildings"
                . " (train stations, airfields or harbors).\n(Airforce Only)\n\n"
                . "\"Your enemy will receive a report about this attack if you succeed or fail."
                . " But it will hide your name if you succeed.\"",
            enabled: true,
            difficulty: 0.5,
            maxDistance: 10,
            researchClass: StrategicBomberAttackResearch::class,
            gameUnit: GameUnitEnum::STRATEGIC_BOMBER,
            researchMinLevel: 1,
        );
    }

    public function getSlug(): string
    {
        return 'strategic-bomber-attack';
    }

    public function getProcessorClass(): string
    {
        return StrategicBomberAttack::class;
    }
}
