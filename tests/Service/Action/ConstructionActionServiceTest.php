<?php

declare(strict_types=1);

namespace FrankProjects\UltimateWarfare\Tests\Service\Action;

use FrankProjects\UltimateWarfare\Entity\Enum\GameUnitCategory;
use FrankProjects\UltimateWarfare\Entity\Player;
use FrankProjects\UltimateWarfare\Entity\WorldRegion;
use FrankProjects\UltimateWarfare\Repository\ConstructionRepository;
use FrankProjects\UltimateWarfare\Repository\GameUnitRegistry;
use FrankProjects\UltimateWarfare\Repository\PlayerRepository;
use FrankProjects\UltimateWarfare\Repository\FederationRepository;
use FrankProjects\UltimateWarfare\Repository\WorldRegionLeveledUnitRepository;
use FrankProjects\UltimateWarfare\Repository\WorldRegionStackableUnitRepository;
use FrankProjects\UltimateWarfare\Service\Action\ConstructionActionService;
use FrankProjects\UltimateWarfare\Service\GameUnit\GameUnitBehaviorFactory;
use FrankProjects\UltimateWarfare\Service\NetWorthUpdaterService;
use FrankProjects\UltimateWarfare\Util\NetWorthCalculator;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ConstructionActionServiceTest extends TestCase
{
    private ConstructionActionService $service;

    protected function setUp(): void
    {
        // NetWorthUpdaterService and NetWorthCalculator are final; build real instances
        // from mocked repositories. They are not reached by the leveled-category guard.
        $netWorthCalculator = new NetWorthCalculator(
            $this->createMock(WorldRegionStackableUnitRepository::class),
            $this->createMock(WorldRegionLeveledUnitRepository::class)
        );
        $netWorthUpdaterService = new NetWorthUpdaterService(
            $this->createMock(FederationRepository::class),
            $this->createMock(PlayerRepository::class),
            $netWorthCalculator
        );

        $this->service = new ConstructionActionService(
            $this->createMock(ConstructionRepository::class),
            new GameUnitRegistry(),
            $this->createMock(PlayerRepository::class),
            $this->createMock(WorldRegionStackableUnitRepository::class),
            $netWorthUpdaterService,
            $this->createMock(GameUnitBehaviorFactory::class)
        );
    }

    public function testConstructRejectsLeveledCategory(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('dedicated building endpoints');

        $this->service->constructGameUnits(
            $this->createMock(WorldRegion::class),
            $this->createMock(Player::class),
            GameUnitCategory::DEFENSE_BUILDINGS,
            [GameUnitCategory::DEFENSE_BUILDINGS->value => '1']
        );
    }

    public function testRemoveRejectsLeveledCategory(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('cannot be destroyed');

        $this->service->removeGameUnits(
            $this->createMock(WorldRegion::class),
            $this->createMock(Player::class),
            GameUnitCategory::SPECIAL_BUILDINGS,
            [GameUnitCategory::SPECIAL_BUILDINGS->value => '1']
        );
    }
}
