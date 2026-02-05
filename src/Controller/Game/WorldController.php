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

    private function getWorldRegionsData(World $world, Player $player): array
    {
        $regions = [];
        foreach ($world->getWorldRegions() as $region) {
            $isYours = $region->getPlayer() !== null && $region->getPlayer()->getId() === $player->getId();
            
            $regionData = [
                'x' => $region->getX(),
                'y' => $region->getY(),
                'id' => $region->getId(),
                'type' => $region->getType(),
                'image' => $region->getType() . '.png',
                'hasOwner' => $region->getPlayer() !== null,
                'isYours' => $isYours,
                'ownerName' => $region->getPlayer()?->getName(),
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
     * Get a summary of units in a region grouped by type
     * @return array<string, int|array>
     */
    private function getUnitSummary(WorldRegion $region): array
    {
        $summary = [
            'buildings' => 0,
            'defences' => 0,
            'special' => 0,
            'units' => 0,
            'specialUnits' => 0,
            'details' => [
                'buildings' => [],
                'defences' => [],
                'special' => [],
                'units' => [],
                'specialUnits' => [],
            ],
        ];

        foreach ($region->getWorldRegionUnits() as $worldRegionUnit) {
            $gameUnitCategory = $worldRegionUnit->getGameUnit()->getGameUnitCategory();
            $amount = $worldRegionUnit->getAmount();
            $unitName = $worldRegionUnit->getGameUnit()->getName();

            match ($gameUnitCategory) {
                GameUnitCategory::BUILDINGS => $this->addUnitToSummary($summary, 'buildings', $unitName, $amount),
                GameUnitCategory::DEFENSE_BUILDINGS => $this->addUnitToSummary($summary, 'defences', $unitName, $amount),
                GameUnitCategory::SPECIAL_BUILDINGS => $this->addUnitToSummary($summary, 'special', $unitName, $amount),
                GameUnitCategory::UNITS => $this->addUnitToSummary($summary, 'units', $unitName, $amount),
                GameUnitCategory::SPECIAL_UNITS => $this->addUnitToSummary($summary, 'specialUnits', $unitName, $amount),
            };
        }

        return $summary;
    }

    private function addUnitToSummary(array &$summary, string $type, string $unitName, int $amount): void
    {
        $summary[$type] += $amount;
        $summary['details'][$type][] = [
            'name' => $unitName,
            'amount' => $amount,
        ];
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
