<?php

declare(strict_types=1);

namespace FrankProjects\UltimateWarfare\Entity\Operation;

use FrankProjects\UltimateWarfare\Entity\Operation;
use FrankProjects\UltimateWarfare\Service\OperationEngine\OperationProcessor\MissileAttack;

final readonly class MissileAttackOperation extends Operation
{
    public function __construct()
    {
        parent::__construct(
            name: 'Missile Attack',
            image: 'op_rocket.gif',
            cost: 50,
            description: "Launch an Missile attack against an enemy country."
                . " Every rocket has 50% chance in destroying an building.\n\n"
                . "\"Your enemy will recieve an report about this attack if you succeed and fail.\"",
            enabled: true,
            difficulty: 0.5,
            maxDistance: 3,
            researchId: 100,
            gameUnitId: 405,
        );
    }

    public function getSlug(): string
    {
        return 'missile-attack';
    }

    public function getProcessorClass(): string
    {
        return MissileAttack::class;
    }
}
