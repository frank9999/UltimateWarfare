<?php

declare(strict_types=1);

namespace FrankProjects\UltimateWarfare\Service\OperationEngine\OperationProcessor;

use FrankProjects\UltimateWarfare\Entity\Enum\GameUnitCategory;
use FrankProjects\UltimateWarfare\Entity\Enum\GameUnitEnum;
use FrankProjects\UltimateWarfare\Service\OperationEngine\OperationProcessor;

final class MissileAttack extends OperationProcessor
{
    public function getFormula(): float
    {
        $specialOps = $this->getSpecialOps();
        $guards = $this->getGuards();
        $total_units = $specialOps + $guards + 1;

        return (3 * $specialOps / (2 * $total_units))
            - (3 * $guards / (2 * $total_units))
            - $this->operation->getDifficulty()
            + $this->getRandomChance();
    }

    public function processPreOperation(): void
    {
        foreach ($this->playerRegion->getWorldRegionUnits() as $worldRegionUnit) {
            if ($worldRegionUnit->getGameUnit() === $this->operation->getGameUnit()) {
                $worldRegionUnit->setAmount($worldRegionUnit->getAmount() - $this->amount);
                $this->worldRegionUnitRepository->save($worldRegionUnit);
            }
        }
    }

    public function processSuccess(): void
    {
        $totalBuildings = 0;
        foreach ($this->region->getWorldRegionUnits() as $worldRegionUnit) {
            $gameUnit = $this->gameUnitRegistry->find($worldRegionUnit->getGameUnit());
            if ($gameUnit->getGameUnitCategory() === GameUnitCategory::BUILDINGS) {
                $totalBuildings = $totalBuildings + $worldRegionUnit->getAmount();
            }
        }

        if (($this->amount / 2) > $totalBuildings) {
            $buildingsDestroyed = $totalBuildings;
            foreach ($this->region->getWorldRegionUnits() as $worldRegionUnit) {
                $gameUnit = $this->gameUnitRegistry->find($worldRegionUnit->getGameUnit());
                if ($gameUnit->getGameUnitCategory() === GameUnitCategory::BUILDINGS) {
                    $this->worldRegionUnitRepository->remove($worldRegionUnit);
                    $unitName = $gameUnit->getName();
                    $this->addToOperationLog("You destroyed all {$unitName} buildings!");
                }
            }

            $reportText = "{$this->getPlayerRegionPlayer()->getName()} launched a missile attack"
                . " against region {$this->region->getX()}, {$this->region->getY()} and destroyed all buildings.";
            $this->reportCreator->createReport($this->getTargetRegionPlayer(), time(), $reportText);
        } else {
            $buildingsDestroyed = intval($this->amount / 2);
            foreach ($this->region->getWorldRegionUnits() as $worldRegionUnit) {
                $gameUnit = $this->gameUnitRegistry->find($worldRegionUnit->getGameUnit());
                if ($gameUnit->getGameUnitCategory() === GameUnitCategory::BUILDINGS) {
                    $percentage = $worldRegionUnit->getAmount() / $totalBuildings;
                    $destroyed = intval($buildingsDestroyed * $percentage);
                    $worldRegionUnit->setAmount($worldRegionUnit->getAmount() - $destroyed);
                    $this->worldRegionUnitRepository->save($worldRegionUnit);
                    $unitName = $gameUnit->getName();
                    $this->addToOperationLog("You destroyed {$destroyed} {$unitName} buildings!");
                }
            }

            $reportText = "{$this->getPlayerRegionPlayer()->getName()} launched a missile attack"
                . " against region {$this->region->getX()}, {$this->region->getY()}"
                . " and destroyed {$buildingsDestroyed} buildings.";
            $this->reportCreator->createReport($this->getTargetRegionPlayer(), time(), $reportText);
        }

        $this->addToOperationLog("You destroyed {$buildingsDestroyed} buildings!");
    }

    public function processFailed(): void
    {
        $troopsLost = intval($this->getSpecialOps() * 0.05);

        foreach ($this->playerRegion->getWorldRegionUnits() as $worldRegionUnit) {
            if ($worldRegionUnit->getGameUnit() === GameUnitEnum::SABOTEUR) {
                $worldRegionUnit->setAmount(intval($worldRegionUnit->getAmount() - $troopsLost));
                $this->worldRegionUnitRepository->save($worldRegionUnit);
            }
        }

        $reportText = "{$this->getPlayerRegionPlayer()->getName()} tried to launch a missile attack"
            . " against region {$this->region->getX()}, {$this->region->getY()} but failed.";
        $this->reportCreator->createReport($this->getTargetRegionPlayer(), time(), $reportText);

        $this->addToOperationLog("We failed our Missile Attack and lost {$troopsLost} Special Ops");
    }

    public function processPostOperation(): void
    {
        $player = $this->getTargetRegionPlayer();
        $player->getNotifications()->setAttacked(true);
        $this->playerRepository->save($player);
    }
}
