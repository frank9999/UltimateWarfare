<?php

declare(strict_types=1);

namespace FrankProjects\UltimateWarfare\Service\OperationEngine;

use FrankProjects\UltimateWarfare\Entity\Enum\GameUnitEnum;
use FrankProjects\UltimateWarfare\Entity\Operation;
use FrankProjects\UltimateWarfare\Entity\Player;
use FrankProjects\UltimateWarfare\Entity\WorldRegion;
use FrankProjects\UltimateWarfare\Repository\ConstructionRepository;
use FrankProjects\UltimateWarfare\Repository\GameUnitRegistry;
use FrankProjects\UltimateWarfare\Repository\PlayerRepository;
use FrankProjects\UltimateWarfare\Repository\WorldRegionRepository;
use FrankProjects\UltimateWarfare\Repository\WorldRegionUnitRepository;
use FrankProjects\UltimateWarfare\Util\ReportCreator;
use RuntimeException;

abstract class OperationProcessor implements OperationInterface
{
    protected WorldRegion $region;
    protected Operation $operation;
    protected WorldRegion $playerRegion;
    protected int $amount;
    protected ReportCreator $reportCreator;
    protected PlayerRepository $playerRepository;
    protected WorldRegionUnitRepository $worldRegionUnitRepository;
    protected WorldRegionRepository $worldRegionRepository;
    protected ConstructionRepository $constructionRepository;
    protected GameUnitRegistry $gameUnitRegistry;
    /**
     * @var array <int, string>
     */
    protected array $operationLog = [];

    private function __construct(
        WorldRegion $region,
        Operation $operation,
        WorldRegion $playerRegion,
        int $amount,
        ReportCreator $reportCreator,
        PlayerRepository $playerRepository,
        WorldRegionUnitRepository $worldRegionUnitRepository,
        WorldRegionRepository $worldRegionRepository,
        ConstructionRepository $constructionRepository,
        GameUnitRegistry $gameUnitRegistry
    ) {
        $this->region = $region;
        $this->operation = $operation;
        $this->playerRegion = $playerRegion;
        $this->amount = $amount;
        $this->reportCreator = $reportCreator;
        $this->playerRepository = $playerRepository;
        $this->worldRegionUnitRepository = $worldRegionUnitRepository;
        $this->worldRegionRepository = $worldRegionRepository;
        $this->constructionRepository = $constructionRepository;
        $this->gameUnitRegistry = $gameUnitRegistry;
    }

    public static function factory(
        WorldRegion $region,
        Operation $operation,
        WorldRegion $playerRegion,
        int $amount,
        ReportCreator $reportCreator,
        PlayerRepository $playerRepository,
        WorldRegionUnitRepository $worldRegionUnitRepository,
        WorldRegionRepository $worldRegionRepository,
        ConstructionRepository $constructionRepository,
        GameUnitRegistry $gameUnitRegistry
    ): OperationInterface {
        $className = $operation->getProcessorClass();
        if (!class_exists($className) || is_subclass_of($className, OperationInterface::class) === false) {
            throw new RuntimeException("Unknown Operation processor {$className}");
        }

        return new $className(
            $region,
            $operation,
            $playerRegion,
            $amount,
            $reportCreator,
            $playerRepository,
            $worldRegionUnitRepository,
            $worldRegionRepository,
            $constructionRepository,
            $gameUnitRegistry
        );
    }

    /**
     * @return array<int, string>
     */
    public function execute(): array
    {
        $this->addToOperationLog("Launching operation {$this->operation->getName()}!");
        $this->processPreOperation();
        $formula = $this->getFormula();

        if ($formula <= 0) {
            $this->processFailed();
        } else {
            $this->processSuccess();
        }

        $this->processPostOperation();

        return $this->getOperationLog();
    }

    protected function getRandomChance(): float
    {
        $random = mt_rand(0, 2);
        return ($random - 1) / 10;
    }

    protected function getSpecialOps(): int
    {
        foreach ($this->playerRegion->getWorldRegionUnits() as $worldRegionUnit) {
            if ($worldRegionUnit->getGameUnit() === GameUnitEnum::SABOTEUR) {
                return $worldRegionUnit->getAmount();
            }
        }

        return 0;
    }

    protected function getGuards(): int
    {
        foreach ($this->region->getWorldRegionUnits() as $worldRegionUnit) {
            if ($worldRegionUnit->getGameUnit() === GameUnitEnum::GUARD) {
                return $worldRegionUnit->getAmount();
            }
        }

        return 0;
    }

    protected function hasResearched(string $researchSlug): bool
    {
        foreach ($this->getPlayerRegionPlayer()->getPlayerResearch() as $playerResearch) {
            if ($playerResearch->getActive() === false) {
                continue;
            }

            if ($playerResearch->getResearchSlug() === $researchSlug) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    public function getOperationLog(): array
    {
        return $this->operationLog;
    }

    protected function addToOperationLog(string $log): void
    {
        $this->operationLog[] = $log;
    }

    protected function getTargetRegionPlayer(): Player
    {
        return $this->getWorldRegionPlayer($this->region);
    }

    protected function getPlayerRegionPlayer(): Player
    {
        return $this->getWorldRegionPlayer($this->playerRegion);
    }

    private function getWorldRegionPlayer(WorldRegion $worldRegion): Player
    {
        $player = $worldRegion->getPlayer();
        if ($player === null) {
            throw new RuntimeException("Region has no owner");
        }

        return $player;
    }
}
