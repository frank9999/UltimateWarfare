<?php

declare(strict_types=1);

namespace FrankProjects\UltimateWarfare\Entity\Operation;

use FrankProjects\UltimateWarfare\Entity\Enum\GameUnitEnum;
use FrankProjects\UltimateWarfare\Entity\Operation;
use FrankProjects\UltimateWarfare\Entity\Research\AdvancedOpticsResearch;
use FrankProjects\UltimateWarfare\Service\OperationEngine\OperationProcessor\SniperAttack;

final readonly class SniperAttackOperation extends Operation
{
    public function __construct()
    {
        parent::__construct(
            name: 'Sniper Team',
            image: 'sniper.gif',
            cost: 250,
            description: "Deploy a Sniper Team behind the enemy lines and take out an ammount"
                . " of enemy soldiers. Every sniper can kill 5 soldiers, but when you fail,"
                . " you lose 20% of your snipers!\n(Army Only)\n\n"
                . "\"Your enemy will recieve an report about this attack if you succeed or fail.\"",
            enabled: true,
            difficulty: 0.5,
            maxDistance: 2,
            researchClass: AdvancedOpticsResearch::class,
            gameUnit: GameUnitEnum::SNIPER,
        );
    }

    public function getSlug(): string
    {
        return 'sniper-attack';
    }

    public function getProcessorClass(): string
    {
        return SniperAttack::class;
    }
}
