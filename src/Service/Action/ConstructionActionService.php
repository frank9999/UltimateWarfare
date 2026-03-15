<?php

declare(strict_types=1);

namespace FrankProjects\UltimateWarfare\Service\Action;

use FrankProjects\UltimateWarfare\Entity\Construction;
use FrankProjects\UltimateWarfare\Entity\Enum\GameUnitCategory;
use FrankProjects\UltimateWarfare\Entity\GameUnit;
use FrankProjects\UltimateWarfare\Entity\Player;
use FrankProjects\UltimateWarfare\Entity\WorldRegion;
use FrankProjects\UltimateWarfare\Repository\ConstructionRepository;
use FrankProjects\UltimateWarfare\Repository\GameUnitRepository;
use FrankProjects\UltimateWarfare\Repository\PlayerRepository;
use FrankProjects\UltimateWarfare\Repository\WorldRegionUnitRepository;
use FrankProjects\UltimateWarfare\Service\GameUnit\GameUnitBehaviorFactory;
use FrankProjects\UltimateWarfare\Service\NetWorthUpdaterService;
use RuntimeException;

final class ConstructionActionService
{
    private ConstructionRepository $constructionRepository;
    private GameUnitRepository $gameUnitRepository;
    private PlayerRepository $playerRepository;
    private WorldRegionUnitRepository $worldRegionUnitRepository;
    private NetWorthUpdaterService $netWorthUpdaterService;
    private GameUnitBehaviorFactory $behaviorFactory;

    public function __construct(
        ConstructionRepository $constructionRepository,
        GameUnitRepository $gameUnitRepository,
        PlayerRepository $playerRepository,
        WorldRegionUnitRepository $worldRegionUnitRepository,
        NetWorthUpdaterService $netWorthUpdaterService,
        GameUnitBehaviorFactory $behaviorFactory
    ) {
        $this->constructionRepository = $constructionRepository;
        $this->gameUnitRepository = $gameUnitRepository;
        $this->playerRepository = $playerRepository;
        $this->worldRegionUnitRepository = $worldRegionUnitRepository;
        $this->netWorthUpdaterService = $netWorthUpdaterService;
        $this->behaviorFactory = $behaviorFactory;
    }

    /**
     * @param array<int, string> $constructionData
     */
    public function constructGameUnits(
        WorldRegion $region,
        Player $player,
        GameUnitCategory $gameUnitCategory,
        array $constructionData
    ): void {
        $this->validateCategoryAllowed($region, $gameUnitCategory);

        $regionBuildingIndex = $this->getRegionBuildingIndex($region);

        $priceCash = 0;
        $priceWood = 0;
        $priceSteel = 0;
        $totalBuild = 0;
        $constructions = [];

        foreach ($constructionData as $gameUnitId => $amount) {
            $amount = intval($amount);
            if ($amount < 1) {
                continue;
            }

            $gameUnit = $this->gameUnitRepository->find($gameUnitId);
            if ($gameUnit === null) {
                continue;
            }

            if ($gameUnit->getGameUnitCategory() !== $gameUnitCategory) {
                continue;
            }

            $this->validateUnitAllowed($gameUnit, $region, $regionBuildingIndex);

            $behavior = $this->behaviorFactory->create($gameUnit);
            if (!$behavior->canBuild($region, $player)) {
                throw new RuntimeException(
                    "Cannot build {$gameUnit->getName()}: " .
                    $behavior->getBuildRequirementDescription()
                );
            }

            $priceCash = $priceCash + ($amount * $gameUnit->getCost()->getCash());
            $priceWood = $priceWood + ($amount * $gameUnit->getCost()->getWood());
            $priceSteel = $priceSteel + ($amount * $gameUnit->getCost()->getSteel());

            if ($gameUnitCategory === GameUnitCategory::BUILDINGS) {
                $totalBuild = $totalBuild + $amount;
            }

            $constructions[] = Construction::create($region, $player, $gameUnit, $amount);
        }

        if ($gameUnitCategory === GameUnitCategory::BUILDINGS) {
            $buildingsInConstruction = $this->getCountGameUnitsInConstruction($region, $gameUnitCategory);
            $regionBuildings = $this->getCountGameUnitsInWorldRegion($region, $gameUnitCategory);
            $totalSpace = $region->getSpace() - $regionBuildings - $buildingsInConstruction;

            if ($totalBuild > $totalSpace) {
                throw new RuntimeException('You do not have that much building space.');
            }
        }

        $resources = $player->getResources();

        if ($priceCash > $resources->getCash()) {
            throw new RuntimeException("You don't have enough cash to build that.");
        }
        if ($priceWood > $resources->getWood()) {
            throw new RuntimeException("You don't have enough wood to build that.");
        }
        if ($priceSteel > $resources->getSteel()) {
            throw new RuntimeException("You don't have enough steel to build that.");
        }

        if (count($constructions) === 0) {
            throw new RuntimeException("You didn't select anything to build.");
        }

        $resources->setCash($resources->getCash() - $priceCash);
        $resources->setWood($resources->getWood() - $priceWood);
        $resources->setSteel($resources->getSteel() - $priceSteel);

        $player->setResources($resources);
        $this->playerRepository->save($player);

        foreach ($constructions as $construction) {
            $this->constructionRepository->save($construction);

            $behavior = $this->behaviorFactory->create($construction->getGameUnit());
            $behavior->onBuild($region, $construction->getNumber());
        }
    }

    /**
     * Validate that the given game unit category is allowed to be built on this region.
     */
    private function validateCategoryAllowed(WorldRegion $region, GameUnitCategory $gameUnitCategory): void
    {
        $buildingIndex = $this->getRegionBuildingIndex($region);

        match ($gameUnitCategory) {
            GameUnitCategory::TROOPS,
            GameUnitCategory::SPECIAL_UNITS => $this->requireBuilding($buildingIndex, 'barrack', 'a Barrack'),
            GameUnitCategory::AIR_UNITS => $this->requireBuilding($buildingIndex, 'airport', 'an Airport'),
            GameUnitCategory::NAVAL_UNITS => $this->requireBuilding($buildingIndex, 'harbor', 'a Harbor'),
            GameUnitCategory::MISSILES => $this->requireBuilding($buildingIndex, 'missile_silo', 'a Missile Silo'),
            GameUnitCategory::BUILDINGS,
            GameUnitCategory::DEFENSE_BUILDINGS,
            GameUnitCategory::SPECIAL_BUILDINGS => null, // Always allowed at category level
        };
    }

    /**
     * Validate that a specific game unit is allowed to be built on this region.
     *
     * @param array<string, int> $buildingIndex
     */
    private function validateUnitAllowed(GameUnit $gameUnit, WorldRegion $region, array $buildingIndex): void
    {
        $rowName = $gameUnit->getRowName();
        $regionType = $region->getType();

        // Tanks require a Factory on this region
        if ($rowName === 'tank') {
            $this->requireBuilding($buildingIndex, 'factory', 'a Factory');
        }

        // Harbor can only be built on sand/beach regions
        $sandTypes = [WorldRegion::TYPE_SAND, WorldRegion::TYPE_BEACH];
        if ($rowName === 'harbor' && !in_array($regionType, $sandTypes, true)) {
            throw new RuntimeException("Cannot build {$gameUnit->getName()}: requires a beach region.");
        }

        // Sea mines can only be built on coastal or water regions
        $waterTypes = [
            WorldRegion::TYPE_DEEP_WATER, WorldRegion::TYPE_WATER,
            WorldRegion::TYPE_SHALLOW_WATER, WorldRegion::TYPE_SAND,
            WorldRegion::TYPE_BEACH,
        ];
        if ($rowName === 'sea_mine' && !in_array($regionType, $waterTypes, true)) {
            throw new RuntimeException("Cannot build {$gameUnit->getName()}: requires a beach or water region.");
        }
    }

    /**
     * Check that a building with the given row_name exists (built or in construction) on the region.
     *
     * @param array<string, int> $buildingIndex
     */
    private function requireBuilding(array $buildingIndex, string $rowName, string $readableName): void
    {
        if (($buildingIndex[$rowName] ?? 0) < 1) {
            throw new RuntimeException("This region requires $readableName before you can build this.");
        }
    }

    /**
     * Build an index of building row_names => total amounts for a region.
     *
     * @return array<string, int>
     */
    private function getRegionBuildingIndex(WorldRegion $region): array
    {
        $index = [];

        foreach ($region->getWorldRegionUnits() as $worldRegionUnit) {
            $rowName = $worldRegionUnit->getGameUnit()->getRowName();
            $index[$rowName] = ($index[$rowName] ?? 0) + $worldRegionUnit->getAmount();
        }

        return $index;
    }

    /**
     * @param array<int, string> $destroyData
     */
    public function removeGameUnits(
        WorldRegion $region,
        Player $player,
        GameUnitCategory $gameUnitCategory,
        array $destroyData
    ): void {
        $isRemoving = false;
        foreach ($destroyData as $gameUnitId => $amount) {
            $amount = intval($amount);
            if ($amount < 1) {
                continue;
            }

            $gameUnit = $this->gameUnitRepository->find($gameUnitId);
            if ($gameUnit === null) {
                continue;
            }

            if ($gameUnit->getGameUnitCategory() !== $gameUnitCategory) {
                continue;
            }

            $this->removeGameUnitsFromWorldRegion($region, $gameUnit, $amount);
            $isRemoving = true;
        }

        if ($isRemoving === true) {
            $this->netWorthUpdaterService->updateNetWorthForPlayer($player);
        } else {
            throw new RuntimeException("You didn't select anything to remove.");
        }
    }

    public function cancelConstruction(Player $player, int $constructionId): void
    {
        $construction = $this->constructionRepository->find($constructionId);

        if ($construction === null) {
            throw new RuntimeException('This construction queue does not exist!');
        }

        if ($construction->getPlayer()->getId() !== $player->getId()) {
            throw new RuntimeException('This is not your construction queue!');
        }

        $this->constructionRepository->remove($construction);
    }

    public function getBuildingSpaceLeft(GameUnitCategory $gameUnitCategory, WorldRegion $worldRegion): int
    {
        if ($gameUnitCategory !== GameUnitCategory::BUILDINGS) {
            return 0;
        }

        $buildingsInConstruction = $this->getCountGameUnitsInConstruction($worldRegion, $gameUnitCategory);
        $regionBuildings = $this->getCountGameUnitsInWorldRegion($worldRegion, $gameUnitCategory);

        return $worldRegion->getSpace() - $regionBuildings - $buildingsInConstruction;
    }

    public function getCountGameUnitsInConstruction(WorldRegion $worldRegion, GameUnitCategory $gameUnitCategory): int
    {
        return $this->constructionRepository->getGameUnitConstructionSumByWorldRegionAndCategory(
            $worldRegion,
            $gameUnitCategory
        );
    }

    public function getCountGameUnitsInWorldRegion(WorldRegion $worldRegion, GameUnitCategory $gameUnitCategory): int
    {
        $regionBuildings = 0;
        foreach ($worldRegion->getWorldRegionUnits() as $regionUnit) {
            if ($regionUnit->getGameUnit()->getGameUnitCategory() === $gameUnitCategory) {
                $regionBuildings += $regionUnit->getAmount();
            }
        }

        return $regionBuildings;
    }

    private function removeGameUnitsFromWorldRegion(WorldRegion $worldRegion, GameUnit $gameUnit, int $amount): void
    {
        foreach ($worldRegion->getWorldRegionUnits() as $worldRegionUnit) {
            if ($worldRegionUnit->getGameUnit()->getId() !== $gameUnit->getId()) {
                continue;
            }

            if ($amount > $worldRegionUnit->getAmount()) {
                throw new RuntimeException('You do not have that many ' . $gameUnit->getName() . "s!");
            }

            $worldRegionUnit->setAmount($worldRegionUnit->getAmount() - $amount);
            $this->worldRegionUnitRepository->save($worldRegionUnit);
        }
    }
}
