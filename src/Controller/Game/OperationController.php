<?php

declare(strict_types=1);

namespace FrankProjects\UltimateWarfare\Controller\Game;

use FrankProjects\UltimateWarfare\Exception\WorldRegionNotFoundException;
use FrankProjects\UltimateWarfare\Repository\BombardmentCooldownRepository;
use FrankProjects\UltimateWarfare\Repository\GameUnitRegistry;
use FrankProjects\UltimateWarfare\Repository\OperationRegistry;
use FrankProjects\UltimateWarfare\Repository\WorldRegionRepository;
use FrankProjects\UltimateWarfare\Service\Action\RegionActionService;
use FrankProjects\UltimateWarfare\Service\OperationService;
use FrankProjects\UltimateWarfare\Util\DistanceCalculator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class OperationController extends BaseGameController
{
    private OperationRegistry $operationRegistry;
    private GameUnitRegistry $gameUnitRegistry;
    private WorldRegionRepository $worldRegionRepository;
    private RegionActionService $regionActionService;
    private OperationService $operationService;
    private DistanceCalculator $distanceCalculator;
    private BombardmentCooldownRepository $bombardmentCooldownRepository;

    public function __construct(
        OperationRegistry $operationRegistry,
        GameUnitRegistry $gameUnitRegistry,
        WorldRegionRepository $worldRegionRepository,
        RegionActionService $regionActionService,
        OperationService $operationService,
        DistanceCalculator $distanceCalculator,
        BombardmentCooldownRepository $bombardmentCooldownRepository
    ) {
        $this->operationRegistry = $operationRegistry;
        $this->gameUnitRegistry = $gameUnitRegistry;
        $this->worldRegionRepository = $worldRegionRepository;
        $this->regionActionService = $regionActionService;
        $this->operationService = $operationService;
        $this->distanceCalculator = $distanceCalculator;
        $this->bombardmentCooldownRepository = $bombardmentCooldownRepository;
    }

    /**
     * XXX TODO: Fix sorting for region selection table
     *
     * @param int $regionId
     * @param string $operationSlug
     * @return Response
     */
    public function selectWorldRegion(int $regionId, string $operationSlug): Response
    {
        $player = $this->getPlayer();
        $playerRegions = [];
        $worldRegion = null;

        try {
            $worldRegion = $this->regionActionService->getWorldRegionByIdAndWorld($regionId, $player->getWorld());
            $playerRegions = $this->regionActionService->getOperationAttackFromWorldRegionList(
                $worldRegion,
                $this->getPlayer()
            );
        } catch (WorldRegionNotFoundException $e) {
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute('Game/RegionList', [], 302);
        } catch (Throwable $e) {
            $this->addFlash('error', $e->getMessage());
        }

        $operation = $this->operationRegistry->find($operationSlug);
        if ($operation === null) {
            $this->addFlash('error', 'Unknown operation selected!');
            return $this->redirectToRoute('Game/RegionList', [], 302);
        }

        $gameUnit = $this->gameUnitRegistry->find($operation->getGameUnit());

        return $this->render(
            'game/operation/selectRegion.html.twig',
            [
                'region' => $worldRegion,
                'player' => $player,
                'playerRegions' => $playerRegions,
                'operation' => $operation,
                'gameUnit' => $gameUnit
            ]
        );
    }

    public function selectGameUnits(
        Request $request,
        int $regionId,
        string $operationSlug,
        int $playerRegionId
    ): Response {
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

        $gameUnitsData = $this->worldRegionRepository->getWorldGameUnitSumByWorldRegion($playerRegion);
        $operation = $this->operationRegistry->find($operationSlug);
        $gameUnit = $operation !== null ? $this->gameUnitRegistry->find($operation->getGameUnit()) : null;

        return $this->render(
            'game/operation/selectGameUnit.html.twig',
            [
                'region' => $worldRegion,
                'playerRegion' => $playerRegion,
                'player' => $player,
                'gameUnitsData' => $gameUnitsData,
                'operation' => $operation,
                'gameUnit' => $gameUnit
            ]
        );
    }

    public function selectOperation(Request $request, int $regionId): Response
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

        $operations = $this->operationRegistry->findAvailableForPlayer($player);

        return $this->render(
            'game/operation/selectOperation.html.twig',
            [
                'region' => $worldRegion,
                'player' => $player,
                'operations' => $operations
            ]
        );
    }

    public function executeOperation(
        Request $request,
        int $regionId,
        string $operationSlug,
        int $playerRegionId
    ): Response {
        $operationResults = [];
        $player = $this->getPlayer();

        try {
            $worldRegion = $this->regionActionService->getWorldRegionByIdAndWorld($regionId, $player->getWorld());
        } catch (WorldRegionNotFoundException $e) {
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute('Game/RegionList', [], 302);
        }

        $operation = $this->operationRegistry->find($operationSlug);
        if ($operation === null) {
            $this->addFlash('error', 'Unknown operation selected!');
            return $this->redirectToRoute('Game/RegionList', [], 302);
        }

        try {
            $playerRegion = $this->regionActionService->getWorldRegionByIdAndPlayer($playerRegionId, $player);
        } catch (WorldRegionNotFoundException $e) {
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute('Game/RegionList', [], 302);
        }

        if ($request->isMethod(Request::METHOD_POST)) {
            try {
                $operationResults = $this->operationService->executeOperation(
                    $worldRegion,
                    $operation,
                    $playerRegion,
                    $request->request->getInt('amount')
                );
                $this->addFlash('success', 'Successfully executed the operation!');
            } catch (Throwable $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render(
            'game/operation/executeOperation.html.twig',
            [
                'region' => $worldRegion,
                'player' => $player,
                'operation' => $operation,
                'operationResults' => $operationResults
            ]
        );
    }

    public function operationsApi(int $regionId): JsonResponse
    {
        $player = $this->getPlayer();

        try {
            $worldRegion = $this->regionActionService->getWorldRegionByIdAndWorld($regionId, $player->getWorld());
        } catch (WorldRegionNotFoundException $e) {
            return new JsonResponse(['success' => false, 'message' => $e->getMessage()], 404);
        }

        if ($worldRegion->getPlayer() === null) {
            return new JsonResponse(['success' => false, 'message' => 'Cannot target region without owner!'], 400);
        }

        if ($worldRegion->getPlayer()->getId() === $player->getId()) {
            return new JsonResponse(['success' => false, 'message' => 'Cannot target your own region!'], 400);
        }

        $operations = $this->operationRegistry->findAvailableForPlayer($player);

        $operationsData = [];
        foreach ($operations as $operation) {
            $gameUnit = $this->gameUnitRegistry->find($operation->getGameUnit());
            $operationsData[] = [
                'slug' => $operation->getSlug(),
                'name' => $operation->getName(),
                'image' => $operation->getImage(),
                'cost' => $operation->getCost(),
                'description' => $operation->getDescription(),
                'difficulty' => $operation->getDifficulty(),
                'maxDistance' => $operation->getMaxDistance(),
                'unitName' => $gameUnit->getName(),
                'unitImage' => $gameUnit->getImage(),
            ];
        }

        return new JsonResponse([
            'success' => true,
            'operations' => $operationsData,
        ]);
    }

    public function operationEligibleRegionsApi(int $regionId, string $operationSlug): JsonResponse
    {
        $player = $this->getPlayer();

        try {
            $worldRegion = $this->regionActionService->getWorldRegionByIdAndWorld($regionId, $player->getWorld());
        } catch (WorldRegionNotFoundException $e) {
            return new JsonResponse(['success' => false, 'message' => $e->getMessage()], 404);
        }

        if ($worldRegion->getPlayer() === null) {
            return new JsonResponse(['success' => false, 'message' => 'Cannot target region without owner!'], 400);
        }

        if ($worldRegion->getPlayer()->getId() === $player->getId()) {
            return new JsonResponse(['success' => false, 'message' => 'Cannot target your own region!'], 400);
        }

        $operation = $this->operationRegistry->find($operationSlug);
        if ($operation === null) {
            return new JsonResponse(['success' => false, 'message' => 'Unknown operation!'], 404);
        }

        $requiredGameUnitId = $operation->getGameUnit();
        $maxDistance = $operation->getMaxDistance();

        $eligibleRegions = [];
        foreach ($player->getWorldRegions() as $playerRegion) {
            $distance = $this->distanceCalculator->calculateDistance(
                $playerRegion->getX(),
                $playerRegion->getY(),
                $worldRegion->getX(),
                $worldRegion->getY()
            );

            if ($distance > $maxDistance) {
                continue;
            }

            // Check if region has the required unit type
            $hasUnit = false;
            foreach ($playerRegion->getWorldRegionUnits() as $worldRegionUnit) {
                if (
                    $worldRegionUnit->getGameUnit() === $requiredGameUnitId
                    && $worldRegionUnit->getAmount() > 0
                ) {
                    $hasUnit = true;
                    break;
                }
            }

            if (!$hasUnit) {
                continue;
            }

            // Skip regions with active bombardment cooldowns
            if ($operation->hasCooldown()) {
                $cooldown = $this->bombardmentCooldownRepository->findActiveByWorldRegionAndOperation(
                    $playerRegion,
                    $operation->getSlug()
                );
                if ($cooldown !== null) {
                    continue;
                }
            }

            $eligibleRegions[] = [
                'regionId' => $playerRegion->getId(),
                'x' => $playerRegion->getX(),
                'y' => $playerRegion->getY(),
                'distance' => $distance,
            ];
        }

        return new JsonResponse([
            'success' => true,
            'eligibleRegions' => $eligibleRegions,
        ]);
    }

    public function operationUnitsApi(int $regionId, string $operationSlug, int $playerRegionId): JsonResponse
    {
        $player = $this->getPlayer();

        try {
            $worldRegion = $this->regionActionService->getWorldRegionByIdAndWorld($regionId, $player->getWorld());
            $playerRegion = $this->regionActionService->getWorldRegionByIdAndPlayer($playerRegionId, $player);
        } catch (WorldRegionNotFoundException $e) {
            return new JsonResponse(['success' => false, 'message' => $e->getMessage()], 404);
        }

        $operation = $this->operationRegistry->find($operationSlug);
        if ($operation === null) {
            return new JsonResponse(['success' => false, 'message' => 'Unknown operation!'], 404);
        }

        $requiredGameUnitId = $operation->getGameUnit();
        $availableAmount = 0;

        foreach ($playerRegion->getWorldRegionUnits() as $worldRegionUnit) {
            if ($worldRegionUnit->getGameUnit() === $requiredGameUnitId) {
                $availableAmount = $worldRegionUnit->getAmount();
                break;
            }
        }

        $gameUnit = $this->gameUnitRegistry->find($requiredGameUnitId);

        return new JsonResponse([
            'success' => true,
            'unitName' => $gameUnit->getName(),
            'unitImage' => $gameUnit->getImage(),
            'available' => $availableAmount,
            'costPerUnit' => $operation->getCost(),
            'playerCash' => $player->getResources()->getCash(),
        ]);
    }

    public function executeOperationApi(
        Request $request,
        int $regionId,
        string $operationSlug,
        int $playerRegionId
    ): JsonResponse {
        $player = $this->getPlayer();

        try {
            $worldRegion = $this->regionActionService->getWorldRegionByIdAndWorld($regionId, $player->getWorld());
        } catch (WorldRegionNotFoundException $e) {
            return new JsonResponse(['success' => false, 'message' => $e->getMessage()], 404);
        }

        $operation = $this->operationRegistry->find($operationSlug);
        if ($operation === null) {
            return new JsonResponse(['success' => false, 'message' => 'Unknown operation!'], 404);
        }

        try {
            $playerRegion = $this->regionActionService->getWorldRegionByIdAndPlayer($playerRegionId, $player);
        } catch (WorldRegionNotFoundException $e) {
            return new JsonResponse(['success' => false, 'message' => $e->getMessage()], 404);
        }

        /** @var array{amount?: int} $data */
        $data = json_decode($request->getContent(), true) ?? [];
        $amount = $data['amount'] ?? 0;

        try {
            $operationResults = $this->operationService->executeOperation(
                $worldRegion,
                $operation,
                $playerRegion,
                $amount
            );

            $response = [
                'success' => true,
                'message' => 'Operation executed!',
                'results' => $operationResults,
                'newCash' => $player->getResources()->getCash(),
            ];

            if ($operation->hasCooldown()) {
                $cooldown = $this->bombardmentCooldownRepository->findActiveByWorldRegionAndOperation(
                    $playerRegion,
                    $operation->getSlug()
                );
                if ($cooldown !== null) {
                    $response['bombardmentCooldown'] = [
                        'sourceX' => $playerRegion->getX(),
                        'sourceY' => $playerRegion->getY(),
                        'targetX' => $worldRegion->getX(),
                        'targetY' => $worldRegion->getY(),
                        'cooldownUntil' => $cooldown->getCooldownUntil(),
                        'remainingSeconds' => max(0, $cooldown->getCooldownUntil() - time()),
                    ];
                }
            }

            return new JsonResponse($response);
        } catch (Throwable $e) {
            return new JsonResponse([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
}
