<?php

declare(strict_types=1);

namespace FrankProjects\UltimateWarfare\Controller\Game;

use FrankProjects\UltimateWarfare\Entity\Enum\GameUnitCategory;
use FrankProjects\UltimateWarfare\Exception\WorldRegionNotFoundException;
use FrankProjects\UltimateWarfare\Repository\GameUnitRepository;
use FrankProjects\UltimateWarfare\Repository\WorldRegionRepository;
use FrankProjects\UltimateWarfare\Service\Action\FleetActionService;
use FrankProjects\UltimateWarfare\Service\Action\RegionActionService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class AttackController extends BaseGameController
{
    private WorldRegionRepository $worldRegionRepository;
    private FleetActionService $fleetActionService;
    private RegionActionService $regionActionService;
    private GameUnitRepository $gameUnitRepository;

    public function __construct(
        WorldRegionRepository $worldRegionRepository,
        FleetActionService $fleetActionService,
        RegionActionService $regionActionService,
        GameUnitRepository $gameUnitRepository
    ) {
        $this->worldRegionRepository = $worldRegionRepository;
        $this->fleetActionService = $fleetActionService;
        $this->regionActionService = $regionActionService;
        $this->gameUnitRepository = $gameUnitRepository;
    }

    public function attack(int $regionId): Response
    {
        $player = $this->getPlayer();
        $playerRegions = [];
        $worldRegion = null;

        try {
            $worldRegion = $this->regionActionService->getWorldRegionByIdAndWorld($regionId, $player->getWorld());
            $playerRegions = $this->regionActionService->getAttackFromWorldRegionList($worldRegion, $this->getPlayer());
        } catch (WorldRegionNotFoundException $e) {
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute('Game/RegionList', [], 302);
        } catch (Throwable $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->render(
            'game/region/attackFrom.html.twig',
            [
                'region' => $worldRegion,
                'player' => $player,
                'playerRegions' => $playerRegions
            ]
        );
    }

    public function attackSelectGameUnits(Request $request, int $regionId, int $playerRegionId): Response
    {
        $player = $this->getPlayer();

        try {
            $worldRegion = $this->regionActionService->getWorldRegionByIdAndWorld($regionId, $player->getWorld());
        } catch (WorldRegionNotFoundException $e) {
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute('Game/RegionList', [], 302);
        }

        if ($worldRegion->getPlayer() === null) {
            $this->addFlash('error', "Can not attack nobody!");
            return $this->redirectToRoute('Game/World/Region', ['regionId' => $worldRegion->getId()], 302);
        }

        if ($worldRegion->getPlayer()->getId() === $player->getId()) {
            $this->addFlash('error', "Can not attack your own region!");
            return $this->redirectToRoute('Game/World/Region', ['regionId' => $worldRegion->getId()], 302);
        }

        try {
            $playerRegion = $this->regionActionService->getWorldRegionByIdAndPlayer($playerRegionId, $player);
        } catch (WorldRegionNotFoundException $e) {
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute('Game/RegionList', [], 302);
        }

        if ($request->isMethod(Request::METHOD_POST)) {
            /** @var array<int, string> $units */
            $units = $request->request->all('units');
            $this->fleetActionService->sendGameUnits(
                $playerRegion,
                $worldRegion,
                $player,
                $units
            );
            return $this->redirectToRoute('Game/Fleets', [], 302);
        }

        $gameUnitsData = $this->worldRegionRepository->getWorldGameUnitSumByWorldRegion($playerRegion);
        $gameUnits = $this->gameUnitRepository->findByGameUnitCategories([
            GameUnitCategory::TROOPS,
            GameUnitCategory::AIR_UNITS,
            GameUnitCategory::NAVAL_UNITS,
        ]);

        return $this->render(
            'game/region/attackSelectGameUnits.html.twig',
            [
                'region' => $worldRegion,
                'playerRegion' => $playerRegion,
                'player' => $player,
                'gameUnits' => $gameUnits,
                'gameUnitsData' => $gameUnitsData
            ]
        );
    }
}
