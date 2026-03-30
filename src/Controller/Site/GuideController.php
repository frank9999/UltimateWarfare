<?php

declare(strict_types=1);

namespace FrankProjects\UltimateWarfare\Controller\Site;

use FrankProjects\UltimateWarfare\Controller\BaseController;
use FrankProjects\UltimateWarfare\Entity\Enum\GameUnitCategory;
use FrankProjects\UltimateWarfare\Entity\Enum\GameUnitEnum;
use FrankProjects\UltimateWarfare\Repository\GameUnitRegistry;
use FrankProjects\UltimateWarfare\Repository\OperationRegistry;
use FrankProjects\UltimateWarfare\Repository\ResearchRegistry;
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

    public function gameUnit(int $gameUnitId, GameUnitRegistry $gameUnitRegistry): Response
    {
        $gameUnitEnum = GameUnitEnum::tryFrom($gameUnitId);
        if ($gameUnitEnum === null) {
            $this->addFlash('error', 'No such game unit!');
            return $this->redirectToRoute('Guide/ListUnits');
        }

        $gameUnit = $gameUnitRegistry->find($gameUnitEnum);

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

    public function gettingStarted(): Response
    {
        return $this->render('site/guide/gettingStarted.html.twig');
    }


    public function listOperations(OperationRegistry $operationRegistry): Response
    {
        return $this->render(
            'site/guide/listOperations.html.twig',
            [
                'operations' => $operationRegistry->findEnabled(),
            ]
        );
    }

    public function listResearch(ResearchRegistry $researchRegistry): Response
    {
        $researches = $researchRegistry->findEnabled();

        return $this->render(
            'site/guide/listResearch.html.twig',
            [
                'researches' => $researches
            ]
        );
    }

    public function listUnits(int $gameUnitCategoryId, GameUnitRegistry $gameUnitRegistry): Response
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

        $gameUnits = $gameUnitRegistry->findByCategory($gameUnitCategory);

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

    public function world(): Response
    {
        return $this->render('site/guide/world.html.twig');
    }
}
