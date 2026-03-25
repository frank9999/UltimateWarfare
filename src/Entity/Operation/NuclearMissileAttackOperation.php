<?php

declare(strict_types=1);

namespace FrankProjects\UltimateWarfare\Entity\Operation;

use FrankProjects\UltimateWarfare\Entity\Enum\GameUnitEnum;
use FrankProjects\UltimateWarfare\Entity\Operation;
use FrankProjects\UltimateWarfare\Entity\Research\NuclearTechnologyResearch;
use FrankProjects\UltimateWarfare\Service\OperationEngine\OperationProcessor\NuclearMissileAttack;

final readonly class NuclearMissileAttackOperation extends Operation
{
    public function __construct()
    {
        parent::__construct(
            name: 'Nuclear Missile Attack',
            image: 'op_nuclear.gif',
            cost: 2500000,
            description: 'Launch an Nuclear Missile Attack against an enemy region.',
            enabled: true,
            difficulty: 0.9,
            maxDistance: 3,
            researchClass: NuclearTechnologyResearch::class,
            gameUnit: GameUnitEnum::NUCLEAR_MISSILE,
        );
    }

    public function getSlug(): string
    {
        return 'nuclear-missile-attack';
    }

    public function getProcessorClass(): string
    {
        return NuclearMissileAttack::class;
    }
}
