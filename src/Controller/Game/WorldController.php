<?php

declare(strict_types=1);

namespace FrankProjects\UltimateWarfare\Controller\Game;

use FrankProjects\UltimateWarfare\Entity\Player;
use FrankProjects\UltimateWarfare\Entity\World;
use FrankProjects\UltimateWarfare\Entity\WorldRegion;
use FrankProjects\UltimateWarfare\Repository\BombardmentCooldownRepository;
use FrankProjects\UltimateWarfare\Repository\GameUnitRegistry;
use FrankProjects\UltimateWarfare\Repository\PlayerRepository;
use FrankProjects\UltimateWarfare\Repository\FleetRepository;
use FrankProjects\UltimateWarfare\Repository\WorldRepository;
use FrankProjects\UltimateWarfare\Service\WorldGeneratorService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class WorldController extends BaseGameController
{
    private PlayerRepository $playerRepository;
    private WorldRepository $worldRepository;
    private FleetRepository $fleetRepository;
    private BombardmentCooldownRepository $bombardmentCooldownRepository;
    private GameUnitRegistry $gameUnitRegistry;

    public function __construct(
        PlayerRepository $playerRepository,
        WorldRepository $worldRepository,
        FleetRepository $fleetRepository,
        BombardmentCooldownRepository $bombardmentCooldownRepository,
        GameUnitRegistry $gameUnitRegistry
    ) {
        $this->playerRepository = $playerRepository;
        $this->worldRepository = $worldRepository;
        $this->fleetRepository = $fleetRepository;
        $this->bombardmentCooldownRepository = $bombardmentCooldownRepository;
        $this->gameUnitRegistry = $gameUnitRegistry;
    }

    public function create(WorldGeneratorService $worldGeneratorService): Response
    {
        $validWorlds = [];
        $worlds = $this->worldRepository->findByPublic(true);
        foreach ($worlds as $world) {
            if ($world->isJoinableForUser($this->getGameUser())) {
                $validWorlds[] = $world;
            }
        }

        if (count($validWorlds) > 0) {
            $this->addFlash('error', 'There are active worlds, no need to create a new one at this moment');
            return $this->redirectToRoute('Game/SelectWorld', [], 302);
        }

        $this->addFlash('success', 'Successfully created a new world!');
        $worldGeneratorService->generateBasicWorld();

        return $this->redirectToRoute('Game/SelectWorld', [], 302);
    }

    public function selectWorld(): Response
    {
        $validWorlds = [];
        $worlds = $this->worldRepository->findByPublic(true);
        foreach ($worlds as $world) {
            if ($world->isJoinableForUser($this->getGameUser())) {
                $validWorlds[] = $world;
            }
        }

        return $this->render(
            'game/selectWorld.html.twig',
            [
                'worlds' => $validWorlds,
                'user' => $this->getGameUser()
            ]
        );
    }

    public function selectName(int $worldId): Response
    {
        $world = $this->worldRepository->find($worldId);

        return $this->render(
            'game/selectName.html.twig',
            [
                'world' => $world,
                'user' => $this->getGameUser()
            ]
        );
    }

    public function start(Request $request, int $worldId): Response
    {
        $name = (string) $request->request->get('name');

        $user = $this->getGameUser();
        $world = $this->worldRepository->find($worldId);
        if ($world === null) {
            return $this->redirectToRoute('Game/SelectWorld', [], 302);
        }

        foreach ($user->getPlayers() as $player) {
            if ($player->getWorld()->getId() === $worldId) {
                $this->addFlash('error', 'You are already playing in this world!');
                return $this->redirectToRoute('Game/SelectName', ['worldId' => $worldId], 302);
            }
        }

        if ($this->playerRepository->findByNameAndWorld($name, $world) !== null) {
            $this->addFlash('error', 'Another player with this name already exist!');
            return $this->redirectToRoute('Game/SelectName', ['worldId' => $worldId], 302);
        }

        $player = Player::create($user, $name, $world);
        $this->playerRepository->save($player);
        return $this->redirectToRoute('Game/Login', [], 302);
    }

    public function worldMap(): Response
    {
        $player = $this->getPlayer();
        $world = $player->getWorld();

        $regions = $this->getWorldRegionsData($world, $player);
        $fleets = $this->getPlayerFleetsData($player);
        $bombardments = $this->getActiveBombardmentCooldowns($player);

        return $this->render(
            'v2/game/world.html.twig',
            [
                'regions' => $regions,
                'player' => $player,
                'fleets' => $fleets,
                'bombardments' => $bombardments,
            ]
        );
    }

    /**
     * @return list<array{
     *   x: int, y: int, id: int, type: string, image: string, hasOwner: bool,
     *   isYours: bool, ownerName: string|null, isVisible: bool,
     *   units: array{
     *     buildings: int, defences: int, special: int, specialUnits: int,
     *     troops: int, navalUnits: int, airUnits: int, missiles: int,
     *     details: array<string, list<array{name: string, amount: int}>>
     *   }|array{}
     * }>
     */
    private function getWorldRegionsData(World $world, Player $player): array
    {
        $playerRegions = $this->getPlayerRegionCoordinates($player);
        $visibleRegions = $this->calculateVisibleRegions($playerRegions);

        $regions = [];
        foreach ($world->getWorldRegions() as $region) {
            $isYours = $region->getPlayer() !== null && $region->getPlayer()->getId() === $player->getId();
            $coordinates = $region->getX() . ',' . $region->getY();
            // If player has no regions, make everything visible (no fog of war yet)
            $isVisible = $playerRegions === [] || isset($visibleRegions[$coordinates]);

            $regionData = [
                'x' => $region->getX(),
                'y' => $region->getY(),
                'id' => $region->getId(),
                'type' => $region->getType(),
                'image' => $this->getRegionImage($region->getType()),
                'hasOwner' => $region->getPlayer() !== null,
                'isYours' => $isYours,
                'ownerName' => $region->getPlayer()?->getName(),
                'isVisible' => $isVisible,
                'units' => [],
            ];

            // Only include unit data for regions owned by the current player
            if ($isYours) {
                $regionData['units'] = $this->gameUnitRegistry->getRegionUnitSummary($region);
            }

            $regions[] = $regionData;
        }

        return $regions;
    }

    /**
     * Get coordinates of all regions owned by the player
     * @return array<string, true>
     */
    private function getPlayerRegionCoordinates(Player $player): array
    {
        $coordinates = [];
        foreach ($player->getWorldRegions() as $region) {
            $coordinates[$region->getX() . ',' . $region->getY()] = true;
        }
        return $coordinates;
    }

    /**
     * Calculate which regions are visible (owned + 6 hex neighbors)
     * Uses odd-r offset hex grid: odd rows are shifted right
     * @param array<string, true> $playerRegions
     * @return array<string, true>
     */
    private function calculateVisibleRegions(array $playerRegions): array
    {
        $visibleRegions = $playerRegions;

        foreach (array_keys($playerRegions) as $coordString) {
            [$x, $y] = explode(',', $coordString);
            $x = (int)$x;
            $y = (int)$y;

            // 6 hex neighbors (pointy-top, odd-r offset)
            $visibleRegions[($x - 1) . ',' . $y] = true;
            $visibleRegions[($x + 1) . ',' . $y] = true;

            if ($y % 2 === 0) {
                // Even row
                $visibleRegions[($x - 1) . ',' . ($y - 1)] = true;
                $visibleRegions[$x . ',' . ($y - 1)] = true;
                $visibleRegions[($x - 1) . ',' . ($y + 1)] = true;
                $visibleRegions[$x . ',' . ($y + 1)] = true;
            } else {
                // Odd row (shifted right)
                $visibleRegions[$x . ',' . ($y - 1)] = true;
                $visibleRegions[($x + 1) . ',' . ($y - 1)] = true;
                $visibleRegions[$x . ',' . ($y + 1)] = true;
                $visibleRegions[($x + 1) . ',' . ($y + 1)] = true;
            }
        }

        return $visibleRegions;
    }

    /**
     * Get fleet data for the player
     * @return array<int, array<string, mixed>>
     */
    private function getPlayerFleetsData(Player $player): array
    {
        $fleets = [];
        $currentTime = time();

        foreach ($this->fleetRepository->findByPlayer($player) as $fleet) {
            $sourceRegion = $fleet->getWorldRegion();
            $targetRegion = $fleet->getTargetWorldRegion();
            $arriveTime = $fleet->getTimestampArrive();
            $hasArrived = $currentTime >= $arriveTime;

            // Check if target region belongs to the player
            $targetIsYours = $targetRegion->getPlayer() !== null
                && $targetRegion->getPlayer()->getId() === $player->getId();

            // Get unit details
            $units = [];
            $totalUnitCount = 0;
            foreach ($fleet->getFleetUnits() as $fleetUnit) {
                $amount = $fleetUnit->getAmount();
                $totalUnitCount += $amount;
                $gameUnit = $this->gameUnitRegistry->find($fleetUnit->getGameUnit());
                $units[] = [
                    'name' => $gameUnit !== null ? $gameUnit->getName() : '',
                    'amount' => $amount,
                ];
            }

            $fleets[] = [
                'id' => $fleet->getId(),
                'sourceX' => $sourceRegion->getX(),
                'sourceY' => $sourceRegion->getY(),
                'targetX' => $targetRegion->getX(),
                'targetY' => $targetRegion->getY(),
                'targetRegionId' => $targetRegion->getId(),
                'timestampArrive' => $arriveTime,
                'hasArrived' => $hasArrived,
                'eta' => $hasArrived ? 0 : $arriveTime - $currentTime,
                'unitCount' => $totalUnitCount,
                'units' => $units,
                'targetIsYours' => $targetIsYours,
            ];
        }

        return $fleets;
    }

    /**
     * @return list<array{
     *   sourceX: int, sourceY: int, targetX: int, targetY: int,
     *   cooldownUntil: int, remainingSeconds: int
     * }>
     */
    private function getActiveBombardmentCooldowns(Player $player): array
    {
        $bombardments = [];
        $currentTime = time();

        foreach ($this->bombardmentCooldownRepository->findActiveByPlayer($player) as $cooldown) {
            $bombardments[] = [
                'sourceX' => $cooldown->getWorldRegion()->getX(),
                'sourceY' => $cooldown->getWorldRegion()->getY(),
                'targetX' => $cooldown->getTargetWorldRegion()->getX(),
                'targetY' => $cooldown->getTargetWorldRegion()->getY(),
                'cooldownUntil' => $cooldown->getCooldownUntil(),
                'remainingSeconds' => max(0, $cooldown->getCooldownUntil() - $currentTime),
            ];
        }

        return $bombardments;
    }

    private function getRegionImage(string $type): string
    {
        return $type . '.png';
    }
}
