<?php

declare(strict_types=1);

namespace FrankProjects\UltimateWarfare\Controller\Site;

use FrankProjects\UltimateWarfare\Controller\BaseController;
use FrankProjects\UltimateWarfare\Entity\Enum\GameUnitCategory;
use FrankProjects\UltimateWarfare\Repository\GameUnitRepository;
use FrankProjects\UltimateWarfare\Repository\OperationRegistry;
use FrankProjects\UltimateWarfare\Repository\ResearchRepository;
use Symfony\Component\HttpFoundation\Response;

final class GuideController extends BaseController
{
    public function attack(): Response
    {
        return $this->render('site/guide/attack.html.twig');
    }

    public function construction(): Response
    {
        return $this->render('site/guide/construction.html.twig');
    }

    public function gameUnit(int $gameUnitId, GameUnitRepository $gameUnitRepository): Response
    {
        $gameUnit = $gameUnitRepository->find($gameUnitId);

        if ($gameUnit === null) {
            $this->addFlash('error', 'No such game unit!');
            return $this->redirectToRoute('Guide/ListUnits');
        }

        return $this->render(
            'site/guide/gameUnit.html.twig',
            [
                'gameUnit' => $gameUnit
            ]
        );
    }

    public function federation(): Response
    {
        return $this->render('site/guide/federation.html.twig');
    }

    public function fleet(): Response
    {
        return $this->render('site/guide/fleet.html.twig');
    }

    public function headquarter(): Response
    {
        return $this->render('site/guide/headquarter.html.twig');
    }

    public function index(): Response
    {
        return $this->render('site/guide/index.html.twig');
    }

    public function listOperations(
        OperationRegistry $operationRegistry,
        ResearchRepository $researchRepository
    ): Response {
        $operations = $operationRegistry->findEnabled();

        $researchNames = [];
        foreach ($operations as $operation) {
            $researchId = $operation->getResearchId();
            if (!isset($researchNames[$researchId])) {
                $research = $researchRepository->find($researchId);
                $researchNames[$researchId] = $research !== null ? $research->getName() : '';
            }
        }

        return $this->render(
            'site/guide/listOperations.html.twig',
            [
                'operations' => $operations,
                'researchNames' => $researchNames
            ]
        );
    }

    public function listResearch(ResearchRepository $researchRepository): Response
    {
        $researches = $researchRepository->findAll();

        return $this->render(
            'site/guide/listResearch.html.twig',
            [
                'researches' => $researches
            ]
        );
    }

    public function listUnits(int $gameUnitCategoryId, GameUnitRepository $gameUnitRepository): Response
    {
        $gameUnitCategory = GameUnitCategory::fromInteger($gameUnitCategoryId);
        if ($gameUnitCategory === null) {
            $gameUnitCategories = GameUnitCategory::getAll();

            return $this->render(
                'site/guide/selectGameUnitCategory.html.twig',
                [
                    'gameUnitCategories' => $gameUnitCategories
                ]
            );
        }

        $gameUnits = [];
        foreach ($gameUnitRepository->findByGameUnitCategory($gameUnitCategory) as $gameUnit) {
            $gameUnits[] = $gameUnit;
        }

        return $this->render(
            'site/guide/listGameUnits.html.twig',
            [
                'gameUnitCategory' => $gameUnitCategory,
                'gameUnits' => $gameUnits
            ]
        );
    }

    public function logOff(): Response
    {
        return $this->render('site/guide/logOff.html.twig');
    }

    public function market(): Response
    {
        return $this->render('site/guide/market.html.twig');
    }

    public function ranking(): Response
    {
        return $this->render('site/guide/ranking.html.twig');
    }

    public function region(): Response
    {
        return $this->render('site/guide/region.html.twig');
    }

    public function report(): Response
    {
        return $this->render('site/guide/report.html.twig');
    }

    public function research(): Response
    {
        return $this->render('site/guide/research.html.twig');
    }

    public function rules(): Response
    {
        return $this->render('site/guide/rules.html.twig');
    }

    public function surrender(): Response
    {
        return $this->render('site/guide/surrender.html.twig');
    }

    public function world(): Response
    {
        return $this->render('site/guide/world.html.twig');
    }
}
