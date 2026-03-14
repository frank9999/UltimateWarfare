<?php

declare(strict_types=1);

namespace FrankProjects\UltimateWarfare\Controller\Game;

use FrankProjects\UltimateWarfare\Entity\Enum\GameUnitCategory;
use FrankProjects\UltimateWarfare\Entity\Player;
use FrankProjects\UltimateWarfare\Entity\World;
use FrankProjects\UltimateWarfare\Entity\WorldRegion;
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

    public function __construct(
        PlayerRepository $playerRepository,
        WorldRepository $worldRepository,
        FleetRepository $fleetRepository
    ) {
        $this->playerRepository = $playerRepository;
        $this->worldRepository = $worldRepository;
        $this->fleetRepository = $fleetRepository;
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

    public function world(): Response
    {
        $player = $this->getPlayer();
        $world = $player->getWorld();

        $sectors = [];
        foreach ($world->getWorldSectors() as $sector) {
            $sectors[$sector->getX()][$sector->getY()] = $sector;
        }

        return $this->render(
            'game/world.html.twig',
            [
                'sectors' => $sectors,
                'player' => $player
            ]
        );
    }

    public function worldMap(): Response
    {
        $player = $this->getPlayer();
        $world = $player->getWorld();

        $regions = $this->getWorldRegionsData($world, $player);
        $fleets = $this->getPlayerFleetsData($player);

        return $this->render(
            'v2/game/world.html.twig',
            [
                'regions' => $regions,
                'player' => $player,
                'fleets' => $fleets,
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
                'image' => $region->getType() . '.png',
                'hasOwner' => $region->getPlayer() !== null,
                'isYours' => $isYours,
                'ownerName' => $region->getPlayer()?->getName(),
                'isVisible' => $isVisible,
                'units' => [],
            ];

            // Only include unit data for regions owned by the current player
            if ($isYours) {
                $regionData['units'] = $this->getUnitSummary($region);
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
     * Calculate which regions are visible (owned + directly adjacent)
     * @param array<string, true> $playerRegions
     * @return array<string, true>
     */
    private function calculateVisibleRegions(array $playerRegions): array
    {
        $visibleRegions = $playerRegions;

        // For each owned region, add all 4 adjacent regions
        foreach (array_keys($playerRegions) as $coordString) {
            [$x, $y] = explode(',', $coordString);
            $x = (int)$x;
            $y = (int)$y;

            // Add 4 adjacent tiles (up, down, left, right in isometric grid)
            $visibleRegions[($x - 1) . ',' . $y] = true;  // Left
            $visibleRegions[($x + 1) . ',' . $y] = true;  // Right
            $visibleRegions[$x . ',' . ($y - 1)] = true;  // Up
            $visibleRegions[$x . ',' . ($y + 1)] = true;  // Down
        }

        return $visibleRegions;
    }

    /**
     * Get a summary of units in a region grouped by type
     *
     * @return array{
     *   buildings: int, defences: int, special: int, specialUnits: int,
     *   troops: int, navalUnits: int, airUnits: int, missiles: int,
     *   details: array<string, list<array{name: string, amount: int}>>
     * }
     */
    private function getUnitSummary(WorldRegion $region): array
    {
        /** @var array{
         *   buildings: int, defences: int, special: int, specialUnits: int,
         *   troops: int, navalUnits: int, airUnits: int, missiles: int,
         *   details: array<string, list<array{name: string, amount: int}>>
         * } $summary
         */
        $summary = [
            'buildings' => 0,
            'defences' => 0,
            'special' => 0,
            'specialUnits' => 0,
            'troops' => 0,
            'navalUnits' => 0,
            'airUnits' => 0,
            'missiles' => 0,
            'details' => [
                'buildings' => [],
                'defences' => [],
                'special' => [],
                'specialUnits' => [],
                'troops' => [],
                'navalUnits' => [],
                'airUnits' => [],
                'missiles' => [],
            ],
        ];

        foreach ($region->getWorldRegionUnits() as $worldRegionUnit) {
            $gameUnitCategory = $worldRegionUnit->getGameUnit()->getGameUnitCategory();
            $amount = $worldRegionUnit->getAmount();
            $unitName = $worldRegionUnit->getGameUnit()->getName();

            match ($gameUnitCategory) {
                GameUnitCategory::BUILDINGS => $this->addUnitToSummary($summary, 'buildings', $unitName, $amount),
                GameUnitCategory::DEFENSE_BUILDINGS =>
                    $this->addUnitToSummary($summary, 'defences', $unitName, $amount),
                GameUnitCategory::SPECIAL_BUILDINGS => $this->addUnitToSummary($summary, 'special', $unitName, $amount),
                GameUnitCategory::SPECIAL_UNITS =>
                    $this->addUnitToSummary($summary, 'specialUnits', $unitName, $amount),
                GameUnitCategory::TROOPS => $this->addUnitToSummary($summary, 'troops', $unitName, $amount),
                GameUnitCategory::NAVAL_UNITS => $this->addUnitToSummary($summary, 'navalUnits', $unitName, $amount),
                GameUnitCategory::AIR_UNITS => $this->addUnitToSummary($summary, 'airUnits', $unitName, $amount),
                GameUnitCategory::MISSILES => $this->addUnitToSummary($summary, 'missiles', $unitName, $amount),
            };
        }

        /** @var array{
         *   buildings: int, defences: int, special: int, specialUnits: int,
         *   troops: int, navalUnits: int, airUnits: int, missiles: int,
         *   details: array<string, list<array{name: string, amount: int}>>
         * } $summary
         */
        return $summary;
    }

    /**
     * @param array<string, mixed> $summary
     */
    private function addUnitToSummary(array &$summary, string $type, string $unitName, int $amount): void
    {
        $summary[$type] = (is_int($summary[$type]) ? $summary[$type] : 0) + $amount;
        /** @var array<string, list<array{name: string, amount: int}>> $details */
        $details = is_array($summary['details']) ? $summary['details'] : [];
        $details[$type][] = ['name' => $unitName, 'amount' => $amount];
        $summary['details'] = $details;
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
                $units[] = [
                    'name' => $fleetUnit->getGameUnit()->getName(),
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
}
