<?php

declare(strict_types=1);

namespace FrankProjects\UltimateWarfare\Controller\Game;

use FrankProjects\UltimateWarfare\Entity\Player;
use FrankProjects\UltimateWarfare\Repository\ResearchPlayerRepository;
use FrankProjects\UltimateWarfare\Repository\ResearchRegistry;
use FrankProjects\UltimateWarfare\Service\Action\ResearchActionService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Throwable;

final class ResearchController extends BaseGameController
{
    private ResearchRegistry $researchRegistry;
    private ResearchPlayerRepository $researchPlayerRepository;
    private ResearchActionService $researchActionService;

    public function __construct(
        ResearchRegistry $researchRegistry,
        ResearchPlayerRepository $researchPlayerRepository,
        ResearchActionService $researchActionService
    ) {
        $this->researchRegistry = $researchRegistry;
        $this->researchPlayerRepository = $researchPlayerRepository;
        $this->researchActionService = $researchActionService;
    }

    /**
     * @return string[]
     */
    private function getCompletedResearchSlugs(Player $player): array
    {
        $completedSlugs = [];

        foreach ($player->getPlayerResearch() as $researchPlayer) {
            if ($researchPlayer->getActive()) {
                $completedSlugs[] = $researchPlayer->getResearchSlug();
            }
        }

        return $completedSlugs;
    }

    public function researchTreeApi(): JsonResponse
    {
        $player = $this->getPlayer();
        $completedSlugs = $this->getCompletedResearchSlugs($player);
        $ongoingResearchPlayers = $this->researchPlayerRepository->findOngoingByPlayer($player);

        $ongoingMap = [];
        foreach ($ongoingResearchPlayers as $rp) {
            $ongoingMap[$rp->getResearchSlug()] = $rp;
        }

        $allResearch = $this->researchRegistry->findEnabled();
        $now = time();
        $researchData = [];

        foreach ($allResearch as $research) {
            $slug = $research->getSlug();

            if (in_array($slug, $completedSlugs, true)) {
                $status = 'completed';
            } elseif (isset($ongoingMap[$slug])) {
                $status = 'researching';
            } else {
                $prerequisiteSlugs = $research->getPrerequisiteSlugs();
                $allPrerequisitesMet = true;
                foreach ($prerequisiteSlugs as $prereqSlug) {
                    if (!in_array($prereqSlug, $completedSlugs, true)) {
                        $allPrerequisitesMet = false;
                        break;
                    }
                }
                $status = $allPrerequisitesMet ? 'available' : 'locked';
            }

            $item = [
                'slug' => $slug,
                'name' => $research->getName(),
                'description' => $research->getDescription(),
                'image' => $research->getImage(),
                'cost' => $research->getCost(),
                'duration' => $research->getTimestamp(),
                'status' => $status,
                'prerequisites' => $research->getPrerequisiteSlugs(),
                'completionTimestamp' => null,
                'remainingSeconds' => null,
            ];

            if ($status === 'researching') {
                $rp = $ongoingMap[$slug];
                $item['completionTimestamp'] = $rp->getCompletionTimestamp();
                $item['remainingSeconds'] = max(0, $rp->getCompletionTimestamp() - $now);
            }

            $researchData[] = $item;
        }

        return new JsonResponse([
            'success' => true,
            'research' => $researchData,
            'playerCash' => $player->getResources()->getCash(),
        ]);
    }

    public function performResearchApi(string $researchSlug): JsonResponse
    {
        try {
            $player = $this->getPlayer();
            $this->researchActionService->performResearch($researchSlug, $player);

            return new JsonResponse([
                'success' => true,
                'message' => 'Successfully started a new research project!',
                'newCash' => $player->getResources()->getCash(),
            ]);
        } catch (Throwable $e) {
            return new JsonResponse([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function performCancelApi(string $researchSlug): JsonResponse
    {
        try {
            $this->researchActionService->performCancel($researchSlug, $this->getPlayer());

            return new JsonResponse([
                'success' => true,
                'message' => 'Successfully cancelled your research project!',
            ]);
        } catch (Throwable $e) {
            return new JsonResponse([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
