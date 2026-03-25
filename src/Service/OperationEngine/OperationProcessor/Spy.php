<?php

declare(strict_types=1);

namespace FrankProjects\UltimateWarfare\Service\OperationEngine\OperationProcessor;

use FrankProjects\UltimateWarfare\Entity\Enum\GameUnitCategory;
use FrankProjects\UltimateWarfare\Entity\Enum\GameUnitEnum;
use FrankProjects\UltimateWarfare\Entity\Report;
use FrankProjects\UltimateWarfare\Service\OperationEngine\OperationProcessor;

final class Spy extends OperationProcessor
{
    public function getFormula(): float
    {
        $guards = $this->getGuards();
        $total_units = $this->amount + $guards + 1;

        return (3 * $this->amount / (2 * $total_units))
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
        $this->addToOperationLog("Searching for buildings...");
        $buildingsFound = false;
        foreach ($this->region->getWorldRegionUnits() as $worldRegionUnit) {
            $resolvedUnit = $this->gameUnitRegistry->find($worldRegionUnit->getGameUnit());
            if ($resolvedUnit->getGameUnitCategory() === GameUnitCategory::BUILDINGS) {
                $this->addToOperationLog("- {$worldRegionUnit->getAmount()} {$resolvedUnit->getNameMulti()}");
                $buildingsFound = true;
            }
        }

        if ($buildingsFound === false) {
            $this->addToOperationLog(
                "No buildings found"
            );
        }

        $this->addToOperationLog("Searching for units...");
        $unitsFound = false;
        foreach ($this->region->getWorldRegionUnits() as $worldRegionUnit) {
            $resolvedUnit = $this->gameUnitRegistry->find($worldRegionUnit->getGameUnit());
            if ($resolvedUnit->getGameUnitCategory() === GameUnitCategory::TROOPS) {
                $this->addToOperationLog("- {$worldRegionUnit->getAmount()} {$resolvedUnit->getNameMulti()}");
                $unitsFound = true;
            }
        }

        if ($unitsFound === false) {
            $this->addToOperationLog(
                "No units found"
            );
        }
    }

    public function processFailed(): void
    {
        $spiesLost = intval($this->amount * 0.05);

        foreach ($this->playerRegion->getWorldRegionUnits() as $worldRegionUnit) {
            if ($worldRegionUnit->getGameUnit() === GameUnitEnum::SPY) {
                $worldRegionUnit->setAmount(intval($worldRegionUnit->getAmount() - $spiesLost));
                $this->worldRegionUnitRepository->save($worldRegionUnit);
            }
        }

        $reportText = "{$this->getPlayerRegionPlayer()->getName()} tried to spy"
            . " on region {$this->region->getX()}, {$this->region->getY()} but failed.";
        $this->reportCreator->createReport($this->getTargetRegionPlayer(), time(), $reportText, Report::TYPE_GENERAL);

        $this->addToOperationLog("We failed to spy and lost {$spiesLost} spies");
    }

    public function processPostOperation(): void
    {
        $player = $this->getTargetRegionPlayer();
        $player->getNotifications()->setGeneral(true);
        $this->playerRepository->save($player);
    }
}
