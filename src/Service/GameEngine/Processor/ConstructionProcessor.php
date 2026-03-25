<?php

declare(strict_types=1);

namespace FrankProjects\UltimateWarfare\Service\GameEngine\Processor;

use FrankProjects\UltimateWarfare\Entity\Construction;
use FrankProjects\UltimateWarfare\Entity\Player;
use FrankProjects\UltimateWarfare\Entity\Report;
use FrankProjects\UltimateWarfare\Entity\WorldRegionUnit;
use FrankProjects\UltimateWarfare\Repository\ConstructionRepository;
use FrankProjects\UltimateWarfare\Repository\GameUnitRegistry;
use FrankProjects\UltimateWarfare\Repository\PlayerRepository;
use FrankProjects\UltimateWarfare\Repository\ReportRepository;
use FrankProjects\UltimateWarfare\Repository\WorldRegionUnitRepository;
use FrankProjects\UltimateWarfare\Service\GameEngine\Processor;
use FrankProjects\UltimateWarfare\Service\NetWorthUpdaterService;

final class ConstructionProcessor implements Processor
{
    private ConstructionRepository $constructionRepository;
    private PlayerRepository $playerRepository;
    private ReportRepository $reportRepository;
    private WorldRegionUnitRepository $worldRegionUnitRepository;
    private NetWorthUpdaterService $netWorthUpdaterService;
    private GameUnitRegistry $gameUnitRegistry;
    private int $constructionTimeOverride;

    public function __construct(
        ConstructionRepository $constructionRepository,
        PlayerRepository $playerRepository,
        ReportRepository $reportRepository,
        WorldRegionUnitRepository $worldRegionUnitRepository,
        NetWorthUpdaterService $netWorthUpdaterService,
        GameUnitRegistry $gameUnitRegistry,
        int $constructionTimeOverride
    ) {
        $this->constructionRepository = $constructionRepository;
        $this->playerRepository = $playerRepository;
        $this->reportRepository = $reportRepository;
        $this->worldRegionUnitRepository = $worldRegionUnitRepository;
        $this->netWorthUpdaterService = $netWorthUpdaterService;
        $this->gameUnitRegistry = $gameUnitRegistry;
        $this->constructionTimeOverride = $constructionTimeOverride;
    }

    public function run(int $timestamp): void
    {
        // Override construction time for testing purposes
        if ($this->constructionTimeOverride > 0) {
            $constructions = $this->constructionRepository->getAllConstructions();
        } else {
            $constructions = $this->constructionRepository->getCompletedConstructions($timestamp);
        }

        foreach ($constructions as $construction) {
            $worldRegion = $construction->getWorldRegion();

            if (
                $worldRegion->getPlayer() === null
                || $worldRegion->getPlayer()->getId() !== $construction->getPlayer()->getId()
            ) {
                // Never process construction queue items for a region that no longer belongs to this player
                $this->constructionRepository->remove($construction);
                continue;
            }

            $this->processConstruction($construction);
        }
    }

    private function updatePlayerResources(Player $player, Construction $construction): Player
    {
        $gameUnit = $this->gameUnitRegistry->find($construction->getGameUnit());
        if ($gameUnit === null) {
            return $player;
        }

        $upkeepCash = $construction->getNumber() * $gameUnit->getUpkeep()->getCash();
        $upkeepFood = $construction->getNumber() * $gameUnit->getUpkeep()->getFood();
        $upkeepWood = $construction->getNumber() * $gameUnit->getUpkeep()->getWood();
        $upkeepSteel = $construction->getNumber() * $gameUnit->getUpkeep()->getSteel();

        $incomeCash = $construction->getNumber() * $gameUnit->getIncome()->getCash();
        $incomeFood = $construction->getNumber() * $gameUnit->getIncome()->getFood();
        $incomeWood = $construction->getNumber() * $gameUnit->getIncome()->getWood();
        $incomeSteel = $construction->getNumber() * $gameUnit->getIncome()->getSteel();

        $income = $player->getIncome();
        $upkeep = $player->getUpkeep();

        $upkeep->addCash($upkeepCash);
        $upkeep->addFood($upkeepFood);
        $upkeep->addWood($upkeepWood);
        $upkeep->addSteel($upkeepSteel);

        $income->addCash($incomeCash);
        $income->addFood($incomeFood);
        $income->addWood($incomeWood);
        $income->addSteel($incomeSteel);

        $player->setIncome($income);
        $player->setUpkeep($upkeep);

        return $player;
    }

    private function processConstruction(Construction $construction): void
    {
        // XXX TODO: Process income before processing construction...
        //$this->processPlayerIncome($construction->getPlayer(), $timestamp);

        $worldRegionUnit = $this->getWorldRegionUnit($construction);

        if ($worldRegionUnit !== null) {
            $worldRegionUnit->setAmount($worldRegionUnit->getAmount() + $construction->getNumber());
        } else {
            $worldRegionUnit = WorldRegionUnit::create(
                $construction->getWorldRegion(),
                $construction->getGameUnit(),
                $construction->getNumber()
            );
        }

        $player = $this->updatePlayerResources($construction->getPlayer(), $construction);
        $this->createConstructionReport($construction);

        $this->worldRegionUnitRepository->save($worldRegionUnit);
        $this->playerRepository->save($player);
        $this->constructionRepository->remove($construction);

        $this->netWorthUpdaterService->updateNetWorthForPlayer($player);
    }

    private function createConstructionReport(Construction $construction): void
    {
        $gameUnit = $this->gameUnitRegistry->find($construction->getGameUnit());
        $reportType = Report::TYPE_GENERAL;
        if ($construction->getNumber() > 1) {
            $unitName = $gameUnit?->getNameMulti() ?? '';
            $message = "You completed {$construction->getNumber()} {$unitName}!";
        } else {
            $unitName = $gameUnit?->getName() ?? '';
            $message = "You completed {$construction->getNumber()} {$unitName}!";
        }

        $finishedConstructionTime = $construction->getTimestamp() + ($gameUnit?->getTimestamp() ?? 0);
        $report = Report::createForPlayer($construction->getPlayer(), $finishedConstructionTime, $reportType, $message);
        $this->reportRepository->save($report);
    }

    private function getWorldRegionUnit(Construction $construction): ?WorldRegionUnit
    {
        $worldRegion = $construction->getWorldRegion();
        foreach ($worldRegion->getWorldRegionUnits() as $worldRegionUnitObject) {
            if ($worldRegionUnitObject->getGameUnit() === $construction->getGameUnit()) {
                return $worldRegionUnitObject;
            }
        }

        return null;
    }
}
