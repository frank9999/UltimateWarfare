<?php

declare(strict_types=1);

namespace FrankProjects\UltimateWarfare\Entity\Operation;

use FrankProjects\UltimateWarfare\Entity\Enum\GameUnitEnum;
use FrankProjects\UltimateWarfare\Entity\Operation;
use FrankProjects\UltimateWarfare\Entity\Research\NavalBombardmentResearch;
use FrankProjects\UltimateWarfare\Service\OperationEngine\OperationProcessor\NavalBombardment;

final readonly class NavalBombardmentOperation extends Operation
{
    public function __construct()
    {
        parent::__construct(
            name: 'Naval Bombardment',
            image: 'ship.gif',
            cost: 25,
            description: "Bombard an enemy coastal region with naval gunfire from your cruisers."
                . " Damages all units in the target region, with military targets taking heavier losses."
                . " Cruisers are not consumed but enter a 10-minute cooldown after firing.\n\n"
                . "\"Your enemy will receive a report about this bombardment.\"",
            enabled: true,
            difficulty: 0,
            maxDistance: 3,
            researchClass: NavalBombardmentResearch::class,
            gameUnit: GameUnitEnum::CRUISER,
        );
    }

    public function getSlug(): string
    {
        return 'naval-bombardment';
    }

    public function getProcessorClass(): string
    {
        return NavalBombardment::class;
    }

    public function hasCooldown(): bool
    {
        return true;
    }
}
