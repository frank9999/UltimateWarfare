<?php

declare(strict_types=1);

namespace FrankProjects\UltimateWarfare\Service\OperationEngine\OperationProcessor;

use FrankProjects\UltimateWarfare\Entity\Enum\GameUnitCategory;
use FrankProjects\UltimateWarfare\Entity\Enum\GameUnitEnum;
use FrankProjects\UltimateWarfare\Service\OperationEngine\OperationProcessor;

final class StrategicBomberAttack extends OperationProcessor
{
    protected const int BUILDINGS_DESTROYED_PER_BOMBER = 5;

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
        // Do nothing
    }

    public function processSuccess(): void
    {
        $totalBuildings = 0;
        foreach ($this->region->getWorldRegionUnits() as $worldRegionUnit) {
            $gameUnit = $this->gameUnitRegistry->find($worldRegionUnit->getGameUnit());
            if ($gameUnit->getGameUnitCategory() === GameUnitCategory::SPECIAL_BUILDINGS) {
                $totalBuildings = $totalBuildings + $worldRegionUnit->getAmount();
            }
        }

        if (($this->amount * self::BUILDINGS_DESTROYED_PER_BOMBER) > $totalBuildings) {
            foreach ($this->region->getWorldRegionUnits() as $worldRegionUnit) {
                $gameUnit = $this->gameUnitRegistry->find($worldRegionUnit->getGameUnit());
                if ($gameUnit->getGameUnitCategory() === GameUnitCategory::SPECIAL_BUILDINGS) {
                    $this->worldRegionUnitRepository->remove($worldRegionUnit);
                    $unitName = $gameUnit->getName();
                    $this->addToOperationLog("You destroyed all {$unitName} buildings!");
                }
            }

            $this->addToOperationLog("You destroyed all special buildings!");
            $reportText = "Somebody launched a Strategic Bomber attack"
                . " against region {$this->region->getX()}, {$this->region->getY()}"
                . " and destroyed all special buildings.";
            $this->reportCreator->createReport($this->getTargetRegionPlayer(), time(), $reportText);
        } else {
            $buildingsDestroyed = $this->amount * self::BUILDINGS_DESTROYED_PER_BOMBER;
            foreach ($this->region->getWorldRegionUnits() as $worldRegionUnit) {
                $gameUnit = $this->gameUnitRegistry->find($worldRegionUnit->getGameUnit());
                if ($gameUnit->getGameUnitCategory() === GameUnitCategory::SPECIAL_BUILDINGS) {
                    $percentage = $worldRegionUnit->getAmount() / $totalBuildings;
                    $destroyed = round($buildingsDestroyed * $percentage);
                    $worldRegionUnit->setAmount((int) ($worldRegionUnit->getAmount() - $destroyed));
                    $this->worldRegionUnitRepository->save($worldRegionUnit);
                    $unitName = $gameUnit->getName();
                    $this->addToOperationLog("You destroyed {$destroyed} {$unitName} buildings!");
                }
            }

            $reportText = "Somebody launched a Strategic Bomber attack"
                . " against region {$this->region->getX()}, {$this->region->getY()}"
                . " and destroyed {$buildingsDestroyed} buildings.";
            $this->reportCreator->createReport($this->getTargetRegionPlayer(), time(), $reportText);
        }
    }

    public function processFailed(): void
    {
        $specialOpsLost = intval($this->getSpecialOps() * 0.05);
        $strategicBombersLost = intval($this->amount * 0.1);

        foreach ($this->playerRegion->getWorldRegionUnits() as $worldRegionUnit) {
            if ($worldRegionUnit->getGameUnit() === GameUnitEnum::SABOTEUR) {
                $worldRegionUnit->setAmount(intval($worldRegionUnit->getAmount() - $specialOpsLost));
                $this->worldRegionUnitRepository->save($worldRegionUnit);
            }

            if ($worldRegionUnit->getGameUnit() === GameUnitEnum::STRATEGIC_BOMBER) {
                $worldRegionUnit->setAmount(intval($worldRegionUnit->getAmount() - $strategicBombersLost));
                $this->worldRegionUnitRepository->save($worldRegionUnit);
            }
        }

        $reportText = "{$this->getPlayerRegionPlayer()->getName()} tried to launch a Strategic Bomber attack"
            . " against region {$this->region->getX()}, {$this->region->getY()} but failed.";
        $this->reportCreator->createReport($this->getTargetRegionPlayer(), time(), $reportText);

        $this->addToOperationLog(
            "We failed our Strategic Bomber attack and lost {$specialOpsLost} Special Ops"
            . " and {$strategicBombersLost} Strategic Bombers"
        );
    }

    public function processPostOperation(): void
    {
        $player = $this->getTargetRegionPlayer();
        $player->getNotifications()->setAttacked(true);
        $this->playerRepository->save($player);
    }
}
