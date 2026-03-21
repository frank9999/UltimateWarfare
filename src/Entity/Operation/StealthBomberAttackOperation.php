<?php

declare(strict_types=1);

namespace FrankProjects\UltimateWarfare\Entity\Operation;

use FrankProjects\UltimateWarfare\Entity\Operation;
use FrankProjects\UltimateWarfare\Service\OperationEngine\OperationProcessor\StealthBomberAttack;

final readonly class StealthBomberAttackOperation extends Operation
{
    public function __construct()
    {
        parent::__construct(
            name: 'Stealth Bombing',
            image: 'op_stealthbombing.gif',
            cost: 15000,
            description: "Launch an Bombing run against an enemy country with your Stealth planes."
                . " Every Stealth Bomber is able to hit 5 buildings"
                . " (train stations, aiports or harbors).\n(Airforce Only)\n\n"
                . "\"Your enemy will recieve an report about this attack if you succeed and fail."
                . " But it will hidde your name if you succeed.\"",
            enabled: true,
            difficulty: 0.5,
            maxDistance: 10,
            researchId: 100,
            gameUnitId: 404,
        );
    }

    public function getSlug(): string
    {
        return 'stealth-bomber-attack';
    }

    public function getProcessorClass(): string
    {
        return StealthBomberAttack::class;
    }
}
