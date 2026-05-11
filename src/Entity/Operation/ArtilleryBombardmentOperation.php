<?php

declare(strict_types=1);

namespace FrankProjects\UltimateWarfare\Entity\Operation;

use FrankProjects\UltimateWarfare\Entity\Enum\GameUnitEnum;
use FrankProjects\UltimateWarfare\Entity\Operation;
use FrankProjects\UltimateWarfare\Entity\Research\ArtilleryBombardmentResearch;
use FrankProjects\UltimateWarfare\Service\OperationEngine\OperationProcessor\ArtilleryBombardment;

final readonly class ArtilleryBombardmentOperation extends Operation
{
    public function __construct()
    {
        parent::__construct(
            name: 'Artillery Bombardment',
            image: 'op_rocket.gif',
            cost: 25,
            description: "Bombard an adjacent enemy region with artillery fire."
                . " Damages all units in the target region, with military targets taking heavier losses."
                . " Artillery units are not consumed but enter a 10-minute cooldown after firing.\n\n"
                . "\"Your enemy will receive a report about this bombardment.\"",
            enabled: true,
            difficulty: 0,
            maxDistance: 2,
            researchClass: ArtilleryBombardmentResearch::class,
            gameUnit: GameUnitEnum::ARTILLERY,
            researchMinLevel: 1,
        );
    }

    public function getSlug(): string
    {
        return 'artillery-bombardment';
    }

    public function getProcessorClass(): string
    {
        return ArtilleryBombardment::class;
    }

    public function hasCooldown(): bool
    {
        return true;
    }
}
