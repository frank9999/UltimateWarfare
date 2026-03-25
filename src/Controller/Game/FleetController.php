<?php

declare(strict_types=1);

namespace FrankProjects\UltimateWarfare\Controller\Game;

use FrankProjects\UltimateWarfare\Entity\Enum\GameUnitCategory;
use FrankProjects\UltimateWarfare\Entity\Enum\GameUnitEnum;
use FrankProjects\UltimateWarfare\Entity\Player;
use FrankProjects\UltimateWarfare\Entity\WorldRegion;
use FrankProjects\UltimateWarfare\Exception\WorldRegionNotFoundException;
use FrankProjects\UltimateWarfare\Repository\GameUnitRegistry;
use FrankProjects\UltimateWarfare\Repository\WorldRegionRepository;
use FrankProjects\UltimateWarfare\Service\Action\FleetActionService;
use FrankProjects\UltimateWarfare\Service\Action\RegionActionService;
use FrankProjects\UltimateWarfare\Util\DistanceCalculator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class FleetController extends BaseGameController
{
    private WorldRegionRepository $worldRegionRepository;
    private FleetActionService $fleetActionService;
    private RegionActionService $regionActionService;
    private GameUnitRegistry $gameUnitRegistry;
    private DistanceCalculator $distanceCalculator;

    public function __construct(
        WorldRegionRepository $worldRegionRepository,
        FleetActionService $fleetActionService,
        RegionActionService $regionActionService,
        GameUnitRegistry $gameUnitRegistry,
        DistanceCalculator $distanceCalculator
    ) {
        $this->worldRegionRepository = $worldRegionRepository;
        $this->fleetActionService = $fleetActionService;
        $this->regionActionService = $regionActionService;
        $this->gameUnitRegistry = $gameUnitRegistry;
        $this->distanceCalculator = $distanceCalculator;
    }

    /**
     * API endpoint for recalling a fleet (JSON response)
     */
    public function recallApi(int $fleetId): JsonResponse
    {
        try {
            $region = $this->fleetActionService->recall($fleetId, $this->getPlayer());
            return new JsonResponse([
                'success' => true,
                'message' => 'You successfully recalled your troops!',
                'regionId' => $region->getId(),
                'units' => $this->gameUnitRegistry->getRegionUnitSummary($region),
            ]);
        } catch (Throwable $e) {
            return new JsonResponse([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * API endpoint for reinforcing a region (JSON response)
     */
    public function reinforceApi(int $fleetId): JsonResponse
    {
        try {
            $region = $this->fleetActionService->reinforce($fleetId, $this->getPlayer());
            return new JsonResponse([
                'success' => true,
                'message' => 'You successfully reinforced your region!',
                'regionId' => $region->getId(),
                'units' => $this->gameUnitRegistry->getRegionUnitSummary($region),
            ]);
        } catch (Throwable $e) {
            return new JsonResponse([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * API: Get sendable units from a player's region
     */
    public function sendUnitsDataApi(int $regionId): JsonResponse
    {
        $player = $this->getPlayer();

        try {
            $playerRegion = $this->regionActionService->getWorldRegionByIdAndPlayer($regionId, $player);
        } catch (WorldRegionNotFoundException $e) {
            return new JsonResponse(['success' => false, 'message' => $e->getMessage()], 404);
        }

        $units = [];
        foreach ($playerRegion->getWorldRegionUnits() as $worldRegionUnit) {
            if ($worldRegionUnit->getAmount() <= 0) {
                continue;
            }

            $gameUnitEnum = $worldRegionUnit->getGameUnit();
            $gameUnit = $this->gameUnitRegistry->find($gameUnitEnum);
            if ($gameUnit === null) {
                continue;
            }

            $category = $gameUnit->getGameUnitCategory();

            if (!$category->isSendable()) {
                continue;
            }

            // Only movable unit categories
            if (
                !in_array($category, [
                GameUnitCategory::TROOPS,
                GameUnitCategory::AIR_UNITS,
                GameUnitCategory::NAVAL_UNITS,
                GameUnitCategory::SPECIAL_UNITS,
                ], true)
            ) {
                continue;
            }

            $units[] = [
                'gameUnitId' => $gameUnitEnum->value,
                'name' => $gameUnit->getName(),
                'image' => $gameUnit->getImage(),
                'imageDir' => $category->getImageDir(),
                'amount' => $worldRegionUnit->getAmount(),
                'category' => $category->getLabel(),
            ];
        }

        return new JsonResponse([
            'success' => true,
            'units' => $units,
            'sourceRegion' => [
                'id' => $playerRegion->getId(),
                'x' => $playerRegion->getX(),
                'y' => $playerRegion->getY(),
            ],
        ]);
    }

    /**
     * API: Get target regions (your own) that are in range for sending units
     */
    public function sendUnitsTargetsApi(int $regionId): JsonResponse
    {
        $player = $this->getPlayer();

        try {
            $sourceRegion = $this->regionActionService->getWorldRegionByIdAndPlayer($regionId, $player);
        } catch (WorldRegionNotFoundException $e) {
            return new JsonResponse(['success' => false, 'message' => $e->getMessage()], 404);
        }

        $targets = [];
        foreach ($player->getWorldRegions() as $worldRegion) {
            if ($worldRegion->getId() === $sourceRegion->getId()) {
                continue; // Can't send to self
            }

            $travelTime = $this->distanceCalculator->calculateDistanceTravelTime(
                $worldRegion->getX(),
                $worldRegion->getY(),
                $sourceRegion->getX(),
                $sourceRegion->getY()
            );

            $targets[] = [
                'regionId' => $worldRegion->getId(),
                'x' => $worldRegion->getX(),
                'y' => $worldRegion->getY(),
                'travelTime' => $travelTime,
            ];
        }

        return new JsonResponse([
            'success' => true,
            'sourceRegionId' => $regionId,
            'targets' => $targets,
        ]);
    }

    /**
     * API: Send units from one of your regions to another (creates fleet)
     */
    public function sendUnitsApi(Request $request, int $regionId): JsonResponse
    {
        $player = $this->getPlayer();

        try {
            $sourceRegion = $this->regionActionService->getWorldRegionByIdAndPlayer($regionId, $player);
        } catch (WorldRegionNotFoundException $e) {
            return new JsonResponse(['success' => false, 'message' => $e->getMessage()], 404);
        }

        /** @var array<string, mixed>|null $data */
        $data = json_decode($request->getContent(), true);
        $rawTargetId = is_array($data) ? ($data['targetRegionId'] ?? null) : null;
        $targetRegionId = is_numeric($rawTargetId) ? (int) $rawTargetId : 0;
        /** @var array<string, int|string> $unitData */
        $unitData = is_array($data) && isset($data['units']) && is_array($data['units']) ? $data['units'] : [];

        if ($unitData === []) {
            return new JsonResponse(['success' => false, 'message' => 'No units selected!'], 400);
        }

        if ($targetRegionId === 0) {
            return new JsonResponse(['success' => false, 'message' => 'No target region selected!'], 400);
        }

        try {
            $targetRegion = $this->regionActionService->getWorldRegionByIdAndPlayer($targetRegionId, $player);
        } catch (WorldRegionNotFoundException $e) {
            return new JsonResponse(['success' => false, 'message' => 'Target region not found or not yours!'], 404);
        }

        // Convert unit data to string values expected by FleetActionService
        $filteredUnits = [];
        foreach ($unitData as $gameUnitId => $amount) {
            if (!is_numeric($amount)) {
                continue;
            }
            $amountInt = (int) $amount;
            if ($amountInt > 0) {
                $filteredUnits[(int) $gameUnitId] = (string) $amountInt;
            }
        }

        if ($filteredUnits === []) {
            return new JsonResponse(['success' => false, 'message' => 'No valid units selected!'], 400);
        }

        try {
            $fleet = $this->fleetActionService->sendGameUnits(
                $sourceRegion,
                $targetRegion,
                $player,
                $filteredUnits
            );
        } catch (Throwable $e) {
            return new JsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
        }

        $travelTime = $this->distanceCalculator->calculateDistanceTravelTime(
            $targetRegion->getX(),
            $targetRegion->getY(),
            $sourceRegion->getX(),
            $sourceRegion->getY()
        );

        $sentUnits = [];
        $totalUnitCount = 0;
        foreach ($filteredUnits as $gameUnitId => $amount) {
            $gameUnitEnum = GameUnitEnum::tryFrom($gameUnitId);
            $gameUnit = $gameUnitEnum !== null ? $this->gameUnitRegistry->find($gameUnitEnum) : null;
            if ($gameUnit !== null) {
                $sentUnits[] = [
                    'name' => $gameUnit->getName(),
                    'amount' => (int) $amount,
                ];
                $totalUnitCount += (int) $amount;
            }
        }

        return new JsonResponse([
            'success' => true,
            'message' => 'Units dispatched successfully!',
            'fleet' => [
                'id' => $fleet->getId(),
                'sourceX' => $sourceRegion->getX(),
                'sourceY' => $sourceRegion->getY(),
                'targetX' => $targetRegion->getX(),
                'targetY' => $targetRegion->getY(),
                'targetRegionId' => $targetRegion->getId(),
                'targetIsYours' => true,
                'timestampArrive' => time() + $travelTime,
                'hasArrived' => false,
                'eta' => $travelTime,
                'unitCount' => $totalUnitCount,
                'units' => $sentUnits,
            ],
            'sourceRegionId' => $sourceRegion->getId(),
            'sourceRegionUnits' => $this->gameUnitRegistry->getRegionUnitSummary($sourceRegion),
        ]);
    }

    public function sendGameUnits(Request $request, int $regionId): Response
    {
        $player = $this->getPlayer();

        try {
            $worldRegion = $this->regionActionService->getWorldRegionByIdAndPlayer($regionId, $player);
        } catch (WorldRegionNotFoundException $e) {
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
                $units = $request->request->all('units');
                $this->fleetActionService->sendGameUnits(
                    $worldRegion,
                    $targetRegion,
                    $player,
                    $units
                );
                $this->addFlash('success', 'You successfully send units!');
            } catch (Throwable $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        $gameUnitsData = $this->worldRegionRepository->getWorldGameUnitSumByWorldRegion($worldRegion);
        $targetRegions = $this->getTargetWorldRegionData($player, $worldRegion);
        $gameUnits = $this->gameUnitRegistry->findByCategories([
            GameUnitCategory::TROOPS,
            GameUnitCategory::AIR_UNITS,
            GameUnitCategory::NAVAL_UNITS,
            GameUnitCategory::SPECIAL_UNITS
        ]);

        return $this->render(
            'game/region/sendUnits.html.twig',
            [
                'region' => $worldRegion,
                'player' => $player,
                'gameUnits' => $gameUnits,
                'targetRegions' => $targetRegions,
                'gameUnitsData' => $gameUnitsData
            ]
        );
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

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getTargetWorldRegionData(Player $player, WorldRegion $region): array
    {
        $targetRegions = [];
        foreach ($player->getWorldRegions() as $worldRegion) {
            $travelTime = $this->distanceCalculator->calculateDistanceTravelTime(
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
