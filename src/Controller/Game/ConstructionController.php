<?php

declare(strict_types=1);

namespace FrankProjects\UltimateWarfare\Controller\Game;

use FrankProjects\UltimateWarfare\Entity\Enum\GameUnitCategory;
use FrankProjects\UltimateWarfare\Exception\WorldRegionNotFoundException;
use FrankProjects\UltimateWarfare\Repository\ConstructionRepository;
use FrankProjects\UltimateWarfare\Repository\GameUnitRepository;
use FrankProjects\UltimateWarfare\Repository\WorldRegionRepository;
use FrankProjects\UltimateWarfare\Service\Action\ConstructionActionService;
use FrankProjects\UltimateWarfare\Service\Action\RegionActionService;
use FrankProjects\UltimateWarfare\Service\GameUnit\GameUnitBehaviorFactory;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;
use Symfony\Component\HttpFoundation\JsonResponse;

final class ConstructionController extends BaseGameController
{
    private ConstructionRepository $constructionRepository;
    private WorldRegionRepository $worldRegionRepository;
    private ConstructionActionService $constructionActionService;
    private RegionActionService $regionActionService;
    private GameUnitRepository $gameUnitRepository;
    private GameUnitBehaviorFactory $behaviorFactory;

    public function __construct(
        ConstructionRepository $constructionRepository,
        WorldRegionRepository $worldRegionRepository,
        ConstructionActionService $constructionActionService,
        RegionActionService $regionActionService,
        GameUnitRepository $gameUnitRepository,
        GameUnitBehaviorFactory $behaviorFactory
    ) {
        $this->constructionRepository = $constructionRepository;
        $this->worldRegionRepository = $worldRegionRepository;
        $this->constructionActionService = $constructionActionService;
        $this->regionActionService = $regionActionService;
        $this->gameUnitRepository = $gameUnitRepository;
        $this->behaviorFactory = $behaviorFactory;
    }

    public function construction(int $gameUnitCategoryId): Response
    {
        $gameUnitCategories = GameUnitCategory::getAll();
        $gameUnitCategory = GameUnitCategory::fromInteger($gameUnitCategoryId);
        if ($gameUnitCategory === null) {
            $gameUnits = $this->gameUnitRepository->findAll();
        } else {
            $gameUnits = $this->gameUnitRepository->findByGameUnitCategory($gameUnitCategory);
        }

        if ($gameUnitCategory === null) {
            $constructionData = $this->constructionRepository->getGameUnitConstructionSumByPlayer($this->getPlayer());
            return $this->render(
                'game/constructionSummary.html.twig',
                [
                    'player' => $this->getPlayer(),
                    'gameUnits' => $gameUnits,
                    'gameUnitCategories' => $gameUnitCategories,
                    'constructionData' => $constructionData
                ]
            );
        }

        $constructions = $this->constructionRepository->findByPlayerAndGameUnitCategory($this->getPlayer(), $gameUnitCategory);

        return $this->render(
            'game/construction.html.twig',
            [
                'player' => $this->getPlayer(),
                'constructions' => $constructions,
                'gameUnitCategory' => $gameUnitCategory,
                'gameUnitCategories' => $gameUnitCategories,
            ]
        );
    }

    public function constructGameUnits(Request $request, int $regionId, int $gameUnitCategoryId): Response
    {
        /**
         * XXX TODO: Fix unit info page
         * XXX TODO: Fix buildtime to human readable format
         */
        try {
            $worldRegion = $this->regionActionService->getWorldRegionByIdAndPlayer($regionId, $this->getPlayer());
        } catch (WorldRegionNotFoundException $e) {
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute('Game/RegionList', [], 302);
        }

        $gameUnitCategory = GameUnitCategory::fromInteger($gameUnitCategoryId);
        if ($gameUnitCategory === null) {
            $this->addFlash('error', 'Unknown GameUnitCategory!');
            return $this->redirectToRoute('Game/World/Region', ['regionId' => $worldRegion->getId()], 302);
        }

        if ($request->isMethod(Request::METHOD_POST)) {
            try {
                /** @var array<int, string> $construct */
                $construct = $request->request->all('construct');
                $this->constructionActionService->constructGameUnits(
                    $worldRegion,
                    $this->getPlayer(),
                    $gameUnitCategory,
                    $construct
                );
                $this->addConstructGameUnitsFlash($gameUnitCategory);
            } catch (Throwable $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        $gameUnits = $this->gameUnitRepository->findByGameUnitCategory($gameUnitCategory);

        return $this->render(
            'game/region/constructGameUnits.html.twig',
            [
                'region' => $worldRegion,
                'player' => $this->getPlayer(),
                'spaceLeft' => $this->constructionActionService->getBuildingSpaceLeft($gameUnitCategory, $worldRegion),
                'gameUnitCategory' => $gameUnitCategory,
                'gameUnitCategories' => GameUnitCategory::getAll(),
                'gameUnitData' => $this->worldRegionRepository->getWorldGameUnitSumByWorldRegion($worldRegion),
                'gameUnits' => $gameUnits,
                'constructionData' => $this->constructionRepository->getGameUnitConstructionSumByWorldRegion(
                    $worldRegion
                )
            ]
        );
    }

    private function addConstructGameUnitsFlash(GameUnitCategory $gameUnitCategory): void
    {
        /**
         * XXX TODO: Refactor to show what game units are being built/trained
         */
        if ($gameUnitCategory === GameUnitCategory::UNITS) {
            $this->addFlash('success', 'New units are now being trained!');
        } else {
            $this->addFlash('success', 'New buildings are now being built!');
        }
    }

    public function removeGameUnits(Request $request, int $regionId, int $gameUnitCategoryId): Response
    {
        try {
            $worldRegion = $this->regionActionService->getWorldRegionByIdAndPlayer($regionId, $this->getPlayer());
        } catch (WorldRegionNotFoundException $e) {
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute('Game/RegionList', [], 302);
        }

        $gameUnitCategory = GameUnitCategory::fromInteger($gameUnitCategoryId);
        if ($gameUnitCategory === null) {
            return $this->redirectToRoute('Game/World/Region', ['regionId' => $worldRegion->getId()], 302);
        }

        if ($request->isMethod(Request::METHOD_POST)) {
            try {
                /** @var array<int, string> $destroy */
                $destroy = $request->request->all('destroy');
                $this->constructionActionService->removeGameUnits(
                    $worldRegion,
                    $this->getPlayer(),
                    $gameUnitCategory,
                    $destroy
                );
                $this->addRemoveGameUnitsFlash($gameUnitCategory);
            } catch (Throwable $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        $gameUnits = $this->gameUnitRepository->findByGameUnitCategory($gameUnitCategory);

        return $this->render(
            'game/region/removeGameUnits.html.twig',
            [
                'region' => $worldRegion,
                'player' => $this->getPlayer(),
                'gameUnits' => $gameUnits,
                'gameUnitCategory' => $gameUnitCategory,
                'gameUnitCategories' => GameUnitCategory::getAll(),
                'gameUnitData' => $this->worldRegionRepository->getWorldGameUnitSumByWorldRegion($worldRegion),
            ]
        );
    }

    private function addRemoveGameUnitsFlash(GameUnitCategory $gameUnitCategory): void
    {
        /**
         * XXX TODO: Refactor to show what game units are being destroyed/disbanded
         */
        if ($gameUnitCategory === GameUnitCategory::UNITS) {
            $this->addFlash('success', "You have disbanded units!");
        } else {
            $this->addFlash('success', "You have destroyed buildings!");
        }
    }

    public function cancel(int $constructionId): RedirectResponse
    {
        try {
            $this->constructionActionService->cancelConstruction($this->getPlayer(), $constructionId);
            $this->addFlash('success', 'Successfully cancelled construction queue!');
        } catch (Throwable $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('Game/Construction', [], 302);
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
            $data = json_decode($request->getContent(), true);
            $construct = $data['construct'] ?? [];

            $this->constructionActionService->constructGameUnits(
                $worldRegion,
                $this->getPlayer(),
                $gameUnitCategory,
                $construct
            );

            $player = $this->getPlayer();
            $message = $gameUnitCategory === GameUnitCategory::UNITS
                ? 'New units are now being trained!'
                : 'New buildings are now being built!';

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

    public function getBuildDataApi(int $regionId, int $gameUnitCategoryId): JsonResponse
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

        $gameUnitData = $this->worldRegionRepository->getWorldGameUnitSumByWorldRegion($worldRegion);
        $constructionData = $this->constructionRepository->getGameUnitConstructionSumByWorldRegion($worldRegion);
        $spaceLeft = $this->constructionActionService->getBuildingSpaceLeft($gameUnitCategory, $worldRegion);

        $gameUnits = $this->gameUnitRepository->findByGameUnitCategory($gameUnitCategory);
        $units = [];
        foreach ($gameUnits as $gameUnit) {
            // Check if unit can be built here
            $behavior = $this->behaviorFactory->create($gameUnit);
            $canBuild = $behavior->canBuild($worldRegion, $this->getPlayer());
        
            $units[] = [
                'id' => $gameUnit->getId(),
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
                'buildRequirement' => $behavior->getBuildRequirementDescription(),
                'owned' => $gameUnitData[$gameUnit->getId()] ?? 0,
                'inConstruction' => $constructionData[$gameUnit->getId()] ?? 0
            ];
        }
    
        return new JsonResponse([
            'success' => true,
            'gameUnitCategory' => [
                'id' => $gameUnitCategory->value,
                'name' => $gameUnitCategory->getLabel()
            ],
            'spaceLeft' => $spaceLeft,
            'units' => $units
        ]);
    }
}
