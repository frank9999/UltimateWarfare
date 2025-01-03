<?php

declare(strict_types=1);

namespace FrankProjects\UltimateWarfare\Controller\Game;

use FrankProjects\UltimateWarfare\Entity\GameUnitType;
use FrankProjects\UltimateWarfare\Entity\Player;
use FrankProjects\UltimateWarfare\Entity\WorldRegion;
use FrankProjects\UltimateWarfare\Exception\GameUnitTypeNotFoundException;
use FrankProjects\UltimateWarfare\Exception\WorldRegionNotFoundException;
use FrankProjects\UltimateWarfare\Repository\GameUnitTypeRepository;
use FrankProjects\UltimateWarfare\Repository\WorldRegionRepository;
use FrankProjects\UltimateWarfare\Service\Action\FleetActionService;
use FrankProjects\UltimateWarfare\Service\Action\RegionActionService;
use FrankProjects\UltimateWarfare\Util\DistanceCalculator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class FleetController extends BaseGameController
{
    private WorldRegionRepository $worldRegionRepository;
    private GameUnitTypeRepository $gameUnitTypeRepository;
    private FleetActionService $fleetActionService;
    private RegionActionService $regionActionService;

    public function __construct(
        WorldRegionRepository $worldRegionRepository,
        GameUnitTypeRepository $gameUnitTypeRepository,
        FleetActionService $fleetActionService,
        RegionActionService $regionActionService
    ) {
        $this->worldRegionRepository = $worldRegionRepository;
        $this->gameUnitTypeRepository = $gameUnitTypeRepository;
        $this->fleetActionService = $fleetActionService;
        $this->regionActionService = $regionActionService;
    }

    public function fleetList(): Response
    {
        return $this->render(
            'game/fleetList.html.twig',
            [
                'player' => $this->getPlayer()
            ]
        );
    }

    public function recall(int $fleetId): Response
    {
        try {
            $this->fleetActionService->recall($fleetId, $this->getPlayer());
            $this->addFlash('success', 'You successfully recalled your troops!');
        } catch (Throwable $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->render(
            'game/fleetList.html.twig',
            [
                'player' => $this->getPlayer()
            ]
        );
    }

    public function reinforce(int $fleetId): Response
    {
        try {
            $this->fleetActionService->reinforce($fleetId, $this->getPlayer());
            $this->addFlash('success', 'You successfully reinforced your region!');
        } catch (Throwable $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->render(
            'game/fleetList.html.twig',
            [
                'player' => $this->getPlayer()
            ]
        );
    }

    public function sendGameUnits(Request $request, int $regionId): Response
    {
        $player = $this->getPlayer();

        try {
            $worldRegion = $this->regionActionService->getWorldRegionByIdAndPlayer($regionId, $player);
            $gameUnitType = $this->gameUnitTypeRepository->find(GameUnitType::GAME_UNIT_TYPE_UNITS);
        } catch (WorldRegionNotFoundException | GameUnitTypeNotFoundException $e) {
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute('Game/RegionList', [], 302);
        }

        if ($request->isMethod(Request::METHOD_POST)) {
            $targetRegionId = intval($request->request->get('target', 0));
            try {
                $targetRegion = $this->regionActionService->getWorldRegionByIdAndWorld(
                    $targetRegionId,
                    $player->getWorld()
                );
            } catch (WorldRegionNotFoundException $e) {
                $this->addFlash('error', $e->getMessage());
                return $this->redirectToRoute('Game/RegionList', [], 302);
            }

            try {
                /** @var array<int, string> $units */
                $units = $request->get('units');
                $this->fleetActionService->sendGameUnits(
                    $worldRegion,
                    $targetRegion,
                    $player,
                    $gameUnitType,
                    $units
                );
                $this->addFlash('success', 'You successfully send units!');
            } catch (Throwable $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        $gameUnitsData = $this->worldRegionRepository->getWorldGameUnitSumByWorldRegion($worldRegion);
        $targetRegions = $this->getTargetWorldRegionData($player, $worldRegion);

        return $this->render(
            'game/region/sendUnits.html.twig',
            [
                'region' => $worldRegion,
                'player' => $player,
                'gameUnitType' => $gameUnitType,
                'targetRegions' => $targetRegions,
                'gameUnitsData' => $gameUnitsData
            ]
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getTargetWorldRegionData(Player $player, WorldRegion $region): array
    {
        $distanceCalculator = new DistanceCalculator();

        $targetRegions = [];
        foreach ($player->getWorldRegions() as $worldRegion) {
            $travelTime = $distanceCalculator->calculateDistanceTravelTime(
                $worldRegion->getX(),
                $worldRegion->getY(),
                $region->getX(),
                $region->getY()
            );

            $targetRegions[] = [
                'region' => $worldRegion,
                'travelTime' => $travelTime
            ];
        }

        return $targetRegions;
    }
}
