<?php

declare(strict_types=1);

namespace FrankProjects\UltimateWarfare\Controller\Game;

use FrankProjects\UltimateWarfare\Entity\Enum\GameUnitCategory;
use FrankProjects\UltimateWarfare\Repository\GameUnitRepository;
use FrankProjects\UltimateWarfare\Repository\ReportRepository;
use FrankProjects\UltimateWarfare\Repository\WorldRegionUnitRepository;
use Symfony\Component\HttpFoundation\Response;

final class HeadquarterController extends BaseGameController
{
    private ReportRepository $reportRepository;
    private WorldRegionUnitRepository $worldRegionUnitRepository;
    private GameUnitRepository $gameUnitRepository;

    public function __construct(
        ReportRepository $reportRepository,
        WorldRegionUnitRepository $worldRegionUnitRepository,
        GameUnitRepository $gameUnitRepository
    ) {
        $this->reportRepository = $reportRepository;
        $this->worldRegionUnitRepository = $worldRegionUnitRepository;
        $this->gameUnitRepository = $gameUnitRepository;
    }

    public function army(): Response
    {
        $gameUnitCategories = [
            GameUnitCategory::TROOPS,
            GameUnitCategory::AIR_UNITS,
            GameUnitCategory::NAVAL_UNITS,
            GameUnitCategory::MISSILES
        ];

        $gameUnits = $this->gameUnitRepository->findAll();

        return $this->render(
            'game/headquarter/army.html.twig',
            [
                'player' => $this->getPlayer(),
                'gameUnitCategories' => $gameUnitCategories,
                'gameUnits' => $gameUnits,
                'gameUnitData' => $this->worldRegionUnitRepository->getGameUnitSumByPlayerAndGameUnitCategories(
                    $this->getPlayer(),
                    $gameUnitCategories
                )
            ]
        );
    }

    public function headquarter(): Response
    {
        $reports = $this->reportRepository->findReports($this->getPlayer(), 10);

        return $this->render(
            'game/headquarter.html.twig',
            [
                'player' => $this->getPlayer(),
                'reports' => $reports
            ]
        );
    }

    public function income(): Response
    {
        return $this->render(
            'game/headquarter/income.html.twig',
            [
                'player' => $this->getPlayer(),
                'incomePop' => 0,
            ]
        );
    }

    public function infrastructure(): Response
    {
        $gameUnitCategories = [
            GameUnitCategory::BUILDINGS,
            GameUnitCategory::DEFENSE_BUILDINGS,
            GameUnitCategory::SPECIAL_BUILDINGS
        ];

        $gameUnits = $this->gameUnitRepository->findAll();

        return $this->render(
            'game/headquarter/infrastructure.html.twig',
            [
                'player' => $this->getPlayer(),
                'gameUnits' => $gameUnits,
                'gameUnitCategories' => $gameUnitCategories,
                'gameUnitData' => $this->worldRegionUnitRepository->getGameUnitSumByPlayerAndGameUnitCategories(
                    $this->getPlayer(),
                    $gameUnitCategories
                )
            ]
        );
    }
}
