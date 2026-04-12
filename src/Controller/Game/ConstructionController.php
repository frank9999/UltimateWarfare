<?php

declare(strict_types=1);

namespace FrankProjects\UltimateWarfare\Controller\Game;

use FrankProjects\UltimateWarfare\Entity\Enum\GameUnitCategory;
use FrankProjects\UltimateWarfare\Entity\Enum\GameUnitEnum;
use FrankProjects\UltimateWarfare\Exception\WorldRegionNotFoundException;
use FrankProjects\UltimateWarfare\Repository\ConstructionRepository;
use FrankProjects\UltimateWarfare\Repository\GameUnitRegistry;
use FrankProjects\UltimateWarfare\Repository\WorldRegionRepository;
use FrankProjects\UltimateWarfare\Service\Action\ConstructionActionService;
use FrankProjects\UltimateWarfare\Service\Action\RegionActionService;
use FrankProjects\UltimateWarfare\Service\GameUnit\GameUnitBehaviorFactory;
use Symfony\Component\HttpFoundation\Request;
use Throwable;
use Symfony\Component\HttpFoundation\JsonResponse;

final class ConstructionController extends BaseGameController
{
    private ConstructionRepository $constructionRepository;
    private WorldRegionRepository $worldRegionRepository;
    private ConstructionActionService $constructionActionService;
    private RegionActionService $regionActionService;
    private GameUnitRegistry $gameUnitRegistry;
    private GameUnitBehaviorFactory $behaviorFactory;

    public function __construct(
        ConstructionRepository $constructionRepository,
        WorldRegionRepository $worldRegionRepository,
        ConstructionActionService $constructionActionService,
        RegionActionService $regionActionService,
        GameUnitRegistry $gameUnitRegistry,
        GameUnitBehaviorFactory $behaviorFactory
    ) {
        $this->constructionRepository = $constructionRepository;
        $this->worldRegionRepository = $worldRegionRepository;
        $this->constructionActionService = $constructionActionService;
        $this->regionActionService = $regionActionService;
        $this->gameUnitRegistry = $gameUnitRegistry;
        $this->behaviorFactory = $behaviorFactory;
    }

    public function constructionOverviewApi(): JsonResponse
    {
        $constructions = $this->constructionRepository->findByPlayer($this->getPlayer());
        $items = [];

        foreach ($constructions as $construction) {
            $gameUnit = $this->gameUnitRegistry->find($construction->getGameUnit());
            $unitName = $construction->getNumber() === 1
                ? $gameUnit->getName()
                : $gameUnit->getNameMulti();
            $timeLeft = ($construction->getTimestamp() + $gameUnit->getTimestamp()) - time();

            $items[] = [
                'id' => $construction->getId(),
                'unitName' => $unitName,
                'number' => $construction->getNumber(),
                'regionId' => $construction->getWorldRegion()->getId(),
                'regionX' => $construction->getWorldRegion()->getX(),
                'regionY' => $construction->getWorldRegion()->getY(),
                'timeLeft' => max(0, $timeLeft),
                'categoryId' => $gameUnit->getGameUnitCategory()->value,
                'categoryName' => $gameUnit->getGameUnitCategory()->getLabel(),
            ];
        }

        return new JsonResponse([
            'success' => true,
            'constructions' => $items,
        ]);
    }

    public function cancelApi(int $constructionId): JsonResponse
    {
        try {
            $this->constructionActionService->cancelConstruction($this->getPlayer(), $constructionId);
            return new JsonResponse([
                'success' => true,
                'message' => 'Successfully cancelled construction queue!',
            ]);
        } catch (Throwable $e) {
            return new JsonResponse([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function constructGameUnitsApi(Request $request, int $regionId, int $gameUnitCategoryId): JsonResponse
    {
        try {
            $worldRegion = $this->regionActionService->getWorldRegionByIdAndPlayer($regionId, $this->getPlayer());
        } catch (WorldRegionNotFoundException $e) {
            return new JsonResponse([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }

        $gameUnitCategory = GameUnitCategory::fromInteger($gameUnitCategoryId);
        if ($gameUnitCategory === null) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Unknown GameUnitCategory!'
            ], 400);
        }

        try {
            /** @var array<string, mixed>|null $data */
            $data = json_decode($request->getContent(), true);
            /** @var array<int, string> $construct */
            $construct = is_array($data) && isset($data['construct']) && is_array($data['construct'])
                ? $data['construct']
                : [];

            $this->constructionActionService->constructGameUnits(
                $worldRegion,
                $this->getPlayer(),
                $gameUnitCategory,
                $construct
            );

            $player = $this->getPlayer();
            $message = "New {$gameUnitCategory->getLabel()}"
                . " are now being {$gameUnitCategory->getConstructionAction()}!";

            return new JsonResponse([
                'success' => true,
                'message' => $message,
                'newCash' => $player->getResources()->getCash(),
                'newWood' => $player->getResources()->getWood(),
                'newSteel' => $player->getResources()->getSteel()
            ]);
        } catch (Throwable $e) {
            return new JsonResponse([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function removeGameUnitsApi(Request $request, int $regionId, int $gameUnitCategoryId): JsonResponse
    {
        try {
            $worldRegion = $this->regionActionService->getWorldRegionByIdAndPlayer($regionId, $this->getPlayer());
        } catch (WorldRegionNotFoundException $e) {
            return new JsonResponse([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }

        $gameUnitCategory = GameUnitCategory::fromInteger($gameUnitCategoryId);
        if ($gameUnitCategory === null) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Unknown GameUnitCategory!'
            ], 400);
        }

        try {
            /** @var array<string, mixed>|null $data */
            $data = json_decode($request->getContent(), true);
            /** @var array<int, string> $destroy */
            $destroy = is_array($data) && isset($data['destroy']) && is_array($data['destroy'])
                ? $data['destroy']
                : [];

            $this->constructionActionService->removeGameUnits(
                $worldRegion,
                $this->getPlayer(),
                $gameUnitCategory,
                $destroy
            );

            $action = $gameUnitCategory->getRemoveGameUnitActionDescription();
            $message = "You have {$action} {$gameUnitCategory->getLabel()}!";

            return new JsonResponse([
                'success' => true,
                'message' => $message,
                'regionId' => $worldRegion->getId(),
                'units' => $this->gameUnitRegistry->getRegionUnitSummary($worldRegion),
            ]);
        } catch (Throwable $e) {
            return new JsonResponse([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function getAllBuildDataApi(int $regionId): JsonResponse
    {
        try {
            $worldRegion = $this->regionActionService->getWorldRegionByIdAndPlayer($regionId, $this->getPlayer());
        } catch (WorldRegionNotFoundException $e) {
            return new JsonResponse([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }

        $regionType = $worldRegion->getType();
        $waterTypes = ['deep_water', 'water', 'shallow_water', 'sand'];
        $isSandOrWater = in_array($regionType, $waterTypes, true);
        $isSand = $regionType === 'sand';

        // Detect relevant buildings in this region (units + constructions in progress)
        $hasBarrack = false;
        $hasFactory = false;
        $hasAirfield = false;
        $hasHarbor = false;
        $hasMissileFactory = false;

        foreach ($worldRegion->getWorldRegionUnits() as $worldRegionUnit) {
            $gameUnitEnum = $worldRegionUnit->getGameUnit();
            if ($worldRegionUnit->getAmount() < 1) {
                continue;
            }

            match ($gameUnitEnum) {
                GameUnitEnum::BARRACK => $hasBarrack = true,
                GameUnitEnum::FACTORY => $hasFactory = true,
                GameUnitEnum::AIRFIELD => $hasAirfield = true,
                GameUnitEnum::HARBOR => $hasHarbor = true,
                GameUnitEnum::MISSILE_FACTORY => $hasMissileFactory = true,
                default => null,
            };
        }

        foreach ($worldRegion->getConstructions() as $construction) {
            match ($construction->getGameUnit()) {
                GameUnitEnum::BARRACK => $hasBarrack = true,
                GameUnitEnum::FACTORY => $hasFactory = true,
                GameUnitEnum::AIRFIELD => $hasAirfield = true,
                GameUnitEnum::HARBOR => $hasHarbor = true,
                GameUnitEnum::MISSILE_FACTORY => $hasMissileFactory = true,
                default => null,
            };
        }

        // Determine available categories based on buildings
        $availableCategories = [
            GameUnitCategory::BUILDINGS,
            GameUnitCategory::DEFENSE_BUILDINGS,
            GameUnitCategory::SPECIAL_BUILDINGS,
        ];

        if ($hasBarrack) {
            $availableCategories[] = GameUnitCategory::TROOPS;
            $availableCategories[] = GameUnitCategory::SPECIAL_UNITS;
        }
        if ($hasAirfield) {
            $availableCategories[] = GameUnitCategory::AIR_UNITS;
        }
        if ($hasHarbor) {
            $availableCategories[] = GameUnitCategory::NAVAL_UNITS;
        }
        if ($hasMissileFactory) {
            $availableCategories[] = GameUnitCategory::MISSILES;
        }

        // Query unit counts and construction counts once for the entire region
        $gameUnitData = $this->worldRegionRepository->getWorldGameUnitSumByWorldRegion($worldRegion);
        $constructionData = $this->constructionRepository->getGameUnitConstructionSumByWorldRegion($worldRegion);
        $spaceLeft = $this->constructionActionService->getBuildingSpaceLeft(GameUnitCategory::BUILDINGS, $worldRegion);
        $player = $this->getPlayer();

        // Build player's completed research slugs for research gating
        $completedResearchSlugs = [];
        foreach ($player->getPlayerResearch() as $playerResearch) {
            if ($playerResearch->getActive() === true) {
                $completedResearchSlugs[] = $playerResearch->getResearchSlug();
            }
        }

        $categories = [];
        foreach ($availableCategories as $gameUnitCategory) {
            $gameUnits = $this->gameUnitRegistry->findByCategory($gameUnitCategory);
            $units = [];

            foreach ($gameUnits as $gameUnit) {
                $rowName = $gameUnit->getRowName();

                // Filter harbor from special buildings when region is not sand
                if ($gameUnitCategory === GameUnitCategory::SPECIAL_BUILDINGS && $rowName === 'harbor' && !$isSand) {
                    continue;
                }

                // Filter sea mines from defense buildings when region is not sand or water
                if (
                    $gameUnitCategory === GameUnitCategory::DEFENSE_BUILDINGS
                    && $rowName === 'sea_mine'
                    && !$isSandOrWater
                ) {
                    continue;
                }

                $behavior = $this->behaviorFactory->create($gameUnit);
                $canBuild = $behavior->canBuild($worldRegion, $player);
                $buildRequirement = '';

                // Filter tanks from troops when no factory
                if ($gameUnitCategory === GameUnitCategory::TROOPS && $rowName === 'tank' && !$hasFactory) {
                    $canBuild = false;
                    $buildRequirement = 'Requires a Factory';
                }

                // Check research gating
                $researchSlug = $gameUnit->getResearchSlug();
                $hasRequiredResearch = $researchSlug === null
                    || in_array($researchSlug, $completedResearchSlugs, true);

                if (!$hasRequiredResearch) {
                    $canBuild = false;
                    $buildRequirement = 'Requires ' . $gameUnit->getResearchName() . ' research';
                } elseif (!$canBuild && $buildRequirement === '') {
                    $buildRequirement = $behavior->getBuildRequirementDescription();
                }

                $units[] = [
                    'gameUnitEnum' => $gameUnit->getGameUnitEnum(),
                    'name' => $gameUnit->getName(),
                    'description' => $gameUnit->getDescription(),
                    'image' => $gameUnit->getImage(),
                    'imageDir' => $gameUnitCategory->getImageDir(),
                    'costCash' => $gameUnit->getCost()->getCash(),
                    'costWood' => $gameUnit->getCost()->getWood(),
                    'costSteel' => $gameUnit->getCost()->getSteel(),
                    'costFood' => $gameUnit->getCost()->getFood(),
                    'incomeCash' => $gameUnit->getIncome()->getCash(),
                    'incomeWood' => $gameUnit->getIncome()->getWood(),
                    'incomeSteel' => $gameUnit->getIncome()->getSteel(),
                    'incomeFood' => $gameUnit->getIncome()->getFood(),
                    'upkeepCash' => $gameUnit->getUpkeep()->getCash(),
                    'upkeepWood' => $gameUnit->getUpkeep()->getWood(),
                    'upkeepSteel' => $gameUnit->getUpkeep()->getSteel(),
                    'upkeepFood' => $gameUnit->getUpkeep()->getFood(),
                    'netWorth' => $gameUnit->getNetWorth(),
                    'timestamp' => $gameUnit->getTimestamp(),
                    'canBuild' => $canBuild,
                    'buildRequirement' => $buildRequirement,
                    'owned' => $gameUnitData[$gameUnit->getGameUnitEnum()->value] ?? 0,
                    'inConstruction' => $constructionData[$gameUnit->getGameUnitEnum()->value] ?? 0,
                ];
            }

            $categories[] = [
                'id' => $gameUnitCategory->value,
                'name' => $gameUnitCategory->getLabel(),
                'units' => $units,
            ];
        }

        return new JsonResponse([
            'success' => true,
            'regionType' => $regionType,
            'spaceLeft' => $spaceLeft,
            'categories' => $categories,
        ]);
    }
}
