<?php

declare(strict_types=1);

namespace FrankProjects\UltimateWarfare\Service\Action;

use FrankProjects\UltimateWarfare\Entity\Player;
use FrankProjects\UltimateWarfare\Entity\Research;
use FrankProjects\UltimateWarfare\Entity\ResearchPlayer;
use FrankProjects\UltimateWarfare\Repository\PlayerRepository;
use FrankProjects\UltimateWarfare\Repository\ResearchPlayerRepository;
use FrankProjects\UltimateWarfare\Repository\ResearchRegistry;
use RuntimeException;

final class ResearchActionService
{
    private ResearchRegistry $researchRegistry;
    private ResearchPlayerRepository $researchPlayerRepository;
    private PlayerRepository $playerRepository;

    public function __construct(
        ResearchRegistry $researchRegistry,
        ResearchPlayerRepository $researchPlayerRepository,
        PlayerRepository $playerRepository
    ) {
        $this->researchRegistry = $researchRegistry;
        $this->researchPlayerRepository = $researchPlayerRepository;
        $this->playerRepository = $playerRepository;
    }

    public function performResearch(string $researchSlug, Player $player): void
    {
        $research = $this->getResearchBySlug($researchSlug);

        $this->ensureCanResearch($research, $player);

        $researchPlayer = new ResearchPlayer();
        $researchPlayer->setPlayer($player);
        $researchPlayer->setResearchSlug($research->getSlug());
        $researchPlayer->setTimestamp(time());
        $researchPlayer->setCompletionTimestamp(time() + $research->getTimestamp());

        $resources = $player->getResources();
        $resources->setCash($resources->getCash() - $research->getCost());

        $player->setResources($resources);
        $this->playerRepository->save($player);
        $this->researchPlayerRepository->save($researchPlayer);
    }

    public function performCancel(string $researchSlug, Player $player): void
    {
        $this->getResearchBySlug($researchSlug);

        /** @var ResearchPlayer $playerResearch */
        foreach ($player->getPlayerResearch() as $playerResearch) {
            if ($playerResearch->getResearchSlug() !== $researchSlug) {
                continue;
            }

            if ($playerResearch->getActive()) {
                throw new RuntimeException('Research project is already completed!');
            }

            $this->researchPlayerRepository->remove($playerResearch);
        }
    }

    private function getResearchBySlug(string $researchSlug): Research
    {
        $research = $this->researchRegistry->find($researchSlug);

        if ($research === null) {
            throw new RuntimeException('This technology does not exist!');
        }

        if (!$research->isEnabled()) {
            throw new RuntimeException('This technology is disabled!');
        }

        return $research;
    }

    private function ensureCanResearch(Research $research, Player $player): void
    {
        $completedSlugs = [];

        /** @var ResearchPlayer $playerResearch */
        foreach ($player->getPlayerResearch() as $playerResearch) {
            if (!$playerResearch->getActive()) {
                throw new RuntimeException('You can only research 1 technology at a time!');
            }

            if ($playerResearch->getResearchSlug() === $research->getSlug()) {
                throw new RuntimeException('This technology has already been researched!');
            }

            $completedSlugs[] = $playerResearch->getResearchSlug();
        }

        foreach ($research->getPrerequisiteSlugs() as $prerequisiteSlug) {
            if (!in_array($prerequisiteSlug, $completedSlugs, true)) {
                throw new RuntimeException('You do not have all required technologies!');
            }
        }

        if ($research->getCost() > $player->getResources()->getCash()) {
            throw new RuntimeException('You can not afford that!');
        }
    }
}
