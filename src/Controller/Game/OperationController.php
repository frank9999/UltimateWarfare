<?php

declare(strict_types=1);

namespace FrankProjects\UltimateWarfare\Controller\Game;

use FrankProjects\UltimateWarfare\Entity\Enum\GameUnitCategory;
use FrankProjects\UltimateWarfare\Exception\WorldRegionNotFoundException;
use FrankProjects\UltimateWarfare\Repository\OperationRepository;
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
    private OperationRepository $operationRepository;
    private WorldRegionRepository $worldRegionRepository;
    private RegionActionService $regionActionService;
    private OperationService $operationService;
    private DistanceCalculator $distanceCalculator;

    public function __construct(
        OperationRepository $operationRepository,
        WorldRegionRepository $worldRegionRepository,
        RegionActionService $regionActionService,
        OperationService $operationService,
        DistanceCalculator $distanceCalculator
    ) {
        $this->operationRepository = $operationRepository;
        $this->worldRegionRepository = $worldRegionRepository;
        $this->regionActionService = $regionActionService;
        $this->operationService = $operationService;
        $this->distanceCalculator = $distanceCalculator;
    }

    /**
     * XXX TODO: Fix sorting for region selection table
     *
     * @param int $regionId
     * @param int $operationId
     * @return Response
     */
    public function selectWorldRegion(int $regionId, int $operationId): Response
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

        $operation = $this->operationRepository->find($operationId);
        if ($operation === null) {
            $this->addFlash('error', 'Unknown operation selected!');
            return $this->redirectToRoute('Game/RegionList', [], 302);
        }

        return $this->render(
            'game/operation/selectRegion.html.twig',
            [
                'region' => $worldRegion,
                'player' => $player,
                'playerRegions' => $playerRegions,
                'operation' => $operation
            ]
        );
    }

    public function selectGameUnits(Request $request, int $regionId, int $operationId, int $playerRegionId): Response
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

        $gameUnitsData = $this->worldRegionRepository->getWorldGameUnitSumByWorldRegion($playerRegion);
        $operation = $this->operationRepository->find($operationId);

        return $this->render(
            'game/operation/selectGameUnit.html.twig',
            [
                'region' => $worldRegion,
                'playerRegion' => $playerRegion,
                'player' => $player,
                'gameUnitsData' => $gameUnitsData,
                'operation' => $operation
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

        $operations = $this->operationRepository->findAvailableForPlayer($player);

        return $this->render(
            'game/operation/selectOperation.html.twig',
            [
                'region' => $worldRegion,
                'player' => $player,
                'operations' => $operations
            ]
        );
    }

    public function executeOperation(Request $request, int $regionId, int $operationId, int $playerRegionId): Response
    {
        $operationResults = [];
        $player = $this->getPlayer();

        try {
            $worldRegion = $this->regionActionService->getWorldRegionByIdAndWorld($regionId, $player->getWorld());
        } catch (WorldRegionNotFoundException $e) {
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute('Game/RegionList', [], 302);
        }

        $operation = $this->operationRepository->find($operationId);
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

        $operations = $this->operationRepository->findAvailableForPlayer($player);

        $operationsData = [];
        foreach ($operations as $operation) {
            $operationsData[] = [
                'id' => $operation->getId(),
                'name' => $operation->getName(),
                'image' => $operation->getImage(),
                'cost' => $operation->getCost(),
                'description' => $operation->getDescription(),
                'difficulty' => $operation->getDifficulty(),
                'maxDistance' => $operation->getMaxDistance(),
                'unitName' => $operation->getGameUnit()->getName(),
                'unitImage' => $operation->getGameUnit()->getImage(),
            ];
        }

        return new JsonResponse([
            'success' => true,
            'operations' => $operationsData,
        ]);
    }

    public function operationEligibleRegionsApi(int $regionId, int $operationId): JsonResponse
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

        $operation = $this->operationRepository->find($operationId);
        if ($operation === null) {
            return new JsonResponse(['success' => false, 'message' => 'Unknown operation!'], 404);
        }

        $requiredGameUnitId = $operation->getGameUnit()->getId();
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
                    $worldRegionUnit->getGameUnit()->getId() === $requiredGameUnitId
                    && $worldRegionUnit->getAmount() > 0
                ) {
                    $hasUnit = true;
                    break;
                }
            }

            if (!$hasUnit) {
                continue;
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

    public function operationUnitsApi(int $regionId, int $operationId, int $playerRegionId): JsonResponse
    {
        $player = $this->getPlayer();

        try {
            $worldRegion = $this->regionActionService->getWorldRegionByIdAndWorld($regionId, $player->getWorld());
            $playerRegion = $this->regionActionService->getWorldRegionByIdAndPlayer($playerRegionId, $player);
        } catch (WorldRegionNotFoundException $e) {
            return new JsonResponse(['success' => false, 'message' => $e->getMessage()], 404);
        }

        $operation = $this->operationRepository->find($operationId);
        if ($operation === null) {
            return new JsonResponse(['success' => false, 'message' => 'Unknown operation!'], 404);
        }

        $requiredGameUnitId = $operation->getGameUnit()->getId();
        $availableAmount = 0;

        foreach ($playerRegion->getWorldRegionUnits() as $worldRegionUnit) {
            if ($worldRegionUnit->getGameUnit()->getId() === $requiredGameUnitId) {
                $availableAmount = $worldRegionUnit->getAmount();
                break;
            }
        }

        return new JsonResponse([
            'success' => true,
            'unitName' => $operation->getGameUnit()->getName(),
            'unitImage' => $operation->getGameUnit()->getImage(),
            'available' => $availableAmount,
            'costPerUnit' => $operation->getCost(),
            'playerCash' => $player->getResources()->getCash(),
        ]);
    }

    public function executeOperationApi(
        Request $request,
        int $regionId,
        int $operationId,
        int $playerRegionId
    ): JsonResponse {
        $player = $this->getPlayer();

        try {
            $worldRegion = $this->regionActionService->getWorldRegionByIdAndWorld($regionId, $player->getWorld());
        } catch (WorldRegionNotFoundException $e) {
            return new JsonResponse(['success' => false, 'message' => $e->getMessage()], 404);
        }

        $operation = $this->operationRepository->find($operationId);
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

            return new JsonResponse([
                'success' => true,
                'message' => 'Operation executed!',
                'results' => $operationResults,
                'newCash' => $player->getResources()->getCash(),
            ]);
        } catch (Throwable $e) {
            return new JsonResponse([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
}
