<?php

declare(strict_types=1);

namespace FrankProjects\UltimateWarfare\Service\OperationEngine\OperationProcessor;

use FrankProjects\UltimateWarfare\Entity\Enum\GameUnitEnum;
use FrankProjects\UltimateWarfare\Service\OperationEngine\OperationProcessor;

final class SubmarineAttack extends OperationProcessor
{
    protected const int SHIPS_KILLED_PER_SUBMARINE = 1;

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
        $ships = 0;
        foreach ($this->region->getWorldRegionUnits() as $worldRegionUnit) {
            if ($worldRegionUnit->getGameUnit() === GameUnitEnum::DESTROYER) {
                $ships = $ships + $worldRegionUnit->getAmount();
            }
        }

        if (($this->amount * self::SHIPS_KILLED_PER_SUBMARINE) > $ships) {
            foreach ($this->region->getWorldRegionUnits() as $worldRegionUnit) {
                if ($worldRegionUnit->getGameUnit() === GameUnitEnum::DESTROYER) {
                    $this->worldRegionUnitRepository->remove($worldRegionUnit);
                    $unitName = $this->gameUnitRegistry->find($worldRegionUnit->getGameUnit())->getNameMulti();
                    $this->addToOperationLog("You sunk {$ships} {$unitName}!");
                }
            }

            $this->addToOperationLog("You sunk all ships!");
            $reportText = "Somebody launched a Submarine attack"
                . " against region {$this->region->getX()}, {$this->region->getY()} and sunk all ships.";
            $this->reportCreator->createReport($this->getTargetRegionPlayer(), time(), $reportText);
        } else {
            $shipsDestroyed = $this->amount * self::SHIPS_KILLED_PER_SUBMARINE;
            foreach ($this->region->getWorldRegionUnits() as $worldRegionUnit) {
                if ($worldRegionUnit->getGameUnit() === GameUnitEnum::DESTROYER) {
                    $worldRegionUnit->setAmount($worldRegionUnit->getAmount() - $shipsDestroyed);
                    $this->worldRegionUnitRepository->save($worldRegionUnit);
                    $unitName = $this->gameUnitRegistry->find($worldRegionUnit->getGameUnit())->getNameMulti();
                    $this->addToOperationLog("You sunk {$shipsDestroyed} {$unitName}!");
                }
            }

            $reportText = "Somebody launched a Submarine attack"
                . " against region {$this->region->getX()}, {$this->region->getY()}"
                . " and sunk {$shipsDestroyed} ships.";
            $this->reportCreator->createReport($this->getTargetRegionPlayer(), time(), $reportText);
        }
    }

    public function processFailed(): void
    {
        $specialOpsLost = intval($this->getSpecialOps() * 0.05);
        $submarinesLost = intval($this->amount * 0.2);

        foreach ($this->playerRegion->getWorldRegionUnits() as $worldRegionUnit) {
            if ($worldRegionUnit->getGameUnit() === GameUnitEnum::SABOTEUR) {
                $worldRegionUnit->setAmount(intval($worldRegionUnit->getAmount() - $specialOpsLost));
                $this->worldRegionUnitRepository->save($worldRegionUnit);
            }

            if ($worldRegionUnit->getGameUnit() === GameUnitEnum::SUBMARINE) {
                $worldRegionUnit->setAmount(intval($worldRegionUnit->getAmount() - $submarinesLost));
                $this->worldRegionUnitRepository->save($worldRegionUnit);
            }
        }

        $reportText = "{$this->getPlayerRegionPlayer()->getName()} tried to launch a Submarine attack"
            . " against region {$this->region->getX()}, {$this->region->getY()} but failed.";
        $this->reportCreator->createReport($this->getTargetRegionPlayer(), time(), $reportText);

        $this->addToOperationLog(
            "We failed our Submarine attack and lost {$specialOpsLost} Special Ops and {$submarinesLost} Submarines"
        );
    }

    public function processPostOperation(): void
    {
        $player = $this->getTargetRegionPlayer();
        $player->getNotifications()->setAttacked(true);
        $this->playerRepository->save($player);
    }
}
