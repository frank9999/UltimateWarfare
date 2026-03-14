<?php

declare(strict_types=1);

namespace FrankProjects\UltimateWarfare\Controller\Game;

use FrankProjects\UltimateWarfare\Entity\Enum\GameUnitCategory;
use FrankProjects\UltimateWarfare\Exception\WorldRegionNotFoundException;
use FrankProjects\UltimateWarfare\Repository\GameUnitRepository;
use FrankProjects\UltimateWarfare\Repository\WorldRegionRepository;
use FrankProjects\UltimateWarfare\Service\Action\ConstructionActionService;
use FrankProjects\UltimateWarfare\Service\Action\RegionActionService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Throwable;

final class RegionController extends BaseGameController
{
    private WorldRegionRepository $worldRegionRepository;
    private ConstructionActionService $constructionActionService;
    private RegionActionService $regionActionService;
    private GameUnitRepository $gameUnitRepository;

    public function __construct(
        WorldRegionRepository $worldRegionRepository,
        ConstructionActionService $constructionActionService,
        RegionActionService $regionActionService,
        GameUnitRepository $gameUnitRepository
    ) {
        $this->worldRegionRepository = $worldRegionRepository;
        $this->constructionActionService = $constructionActionService;
        $this->regionActionService = $regionActionService;
        $this->gameUnitRepository = $gameUnitRepository;
    }

    public function buy(Request $request, int $regionId): Response
    {
        $player = $this->getPlayer();
        $worldRegion = null;

        try {
            $worldRegion = $this->regionActionService->getWorldRegionByIdAndWorld($regionId, $player->getWorld());

            if ($request->isMethod(Request::METHOD_POST)) {
                $this->regionActionService->buyWorldRegion($regionId, $this->getPlayer());
                $this->addFlash('success', 'You have bought a Region!');
                return $this->redirectToRoute('Game/World/Region', ['regionId' => $worldRegion->getId()]);
            }
        } catch (WorldRegionNotFoundException $e) {
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute('Game/RegionList');
        } catch (Throwable $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->render(
            'game/region/buy.html.twig',
            [
                'region' => $worldRegion,
                'player' => $player,
                'price' => $player->getRegionPrice()
            ]
        );
    }

    public function buyApi(int $regionId): JsonResponse
    {
        try {
            $player = $this->getPlayer();
            $this->regionActionService->buyWorldRegion($regionId, $player);

            return new JsonResponse([
                'success' => true,
                'message' => 'You have bought a Region!',
                'newCash' => $player->getResources()->getCash(),
                'newRegionPrice' => $player->getRegionPrice()
            ]);
        } catch (Throwable $e) {
            return new JsonResponse([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function region(int $regionId): Response
    {
        $player = $this->getPlayer();

        try {
            $worldRegion = $this->regionActionService->getWorldRegionByIdAndWorld($regionId, $player->getWorld());
        } catch (WorldRegionNotFoundException $e) {
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute('Game/RegionList');
        }

        $gameUnitsData = $this->worldRegionRepository->getWorldGameUnitSumByWorldRegion($worldRegion);
        $gameUnits = $this->gameUnitRepository->findAll();

        return $this->render(
            'game/region.html.twig',
            [
                'region' => $worldRegion,
                'player' => $player,
                'gameUnits' => $gameUnits,
                'previousRegion' => $this->worldRegionRepository->getPreviousWorldRegionForPlayer($regionId, $player),
                'nextRegion' => $this->worldRegionRepository->getNextWorldRegionForPlayer($regionId, $player),
                'gameUnitCategories' => GameUnitCategory::getAll(),
                'gameUnitsData' => $gameUnitsData
            ]
        );
    }

    public function regionList(): Response
    {
        /**
         * XXX TODO: Add sorting support (by building space, population, buildings, units)
         */
        $player = $this->getPlayer();
        $regions = $player->getWorldRegions();
        $regionList = [];

        foreach ($regions as $region) {
            $buildingsInConstruction = $this->constructionActionService->getCountGameUnitsInConstruction(
                $region,
                GameUnitCategory::BUILDINGS
            );
            $buildings = $this->constructionActionService->getCountGameUnitsInWorldRegion(
                $region,
                GameUnitCategory::BUILDINGS
            );
            $regionList[] = [
                'region' => $region,
                'buildingsInConstruction' => $buildingsInConstruction,
                'buildings' => $buildings
            ];
        }

        return $this->render(
            'game/regionList.html.twig',
            [
                'regionList' => $regionList,
                'player' => $player
            ]
        );
    }
}
