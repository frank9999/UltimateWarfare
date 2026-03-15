<?php

declare(strict_types=1);

namespace FrankProjects\UltimateWarfare\Tests\Service\Action;

use Doctrine\Common\Collections\ArrayCollection;
use FrankProjects\UltimateWarfare\Entity\Player;
use FrankProjects\UltimateWarfare\Entity\Player\Resources as PlayerResources;
use FrankProjects\UltimateWarfare\Entity\Research;
use FrankProjects\UltimateWarfare\Entity\ResearchNeeds;
use FrankProjects\UltimateWarfare\Entity\ResearchPlayer;
use FrankProjects\UltimateWarfare\Repository\PlayerRepository;
use FrankProjects\UltimateWarfare\Repository\ResearchPlayerRepository;
use FrankProjects\UltimateWarfare\Repository\ResearchRepository;
use FrankProjects\UltimateWarfare\Service\Action\ResearchActionService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ResearchActionServiceTest extends TestCase
{
    private ResearchActionService $service;
    private ResearchRepository&MockObject $researchRepository;
    private ResearchPlayerRepository&MockObject $researchPlayerRepository;
    private PlayerRepository&MockObject $playerRepository;

    protected function setUp(): void
    {
        $this->researchRepository = $this->createMock(ResearchRepository::class);
        $this->researchPlayerRepository = $this->createMock(ResearchPlayerRepository::class);
        $this->playerRepository = $this->createMock(PlayerRepository::class);

        $this->service = new ResearchActionService(
            $this->researchRepository,
            $this->researchPlayerRepository,
            $this->playerRepository
        );
    }

    private function createResearch(int $id = 1, int $cost = 500, bool $active = true): Research
    {
        $research = new Research();
        $research->setId($id);
        $research->setName('Test Research');
        $research->setCost($cost);
        $research->setActive($active);
        $research->setResearchNeeds(new ArrayCollection());

        return $research;
    }

    private function createPlayer(int $cash = 1000): Player
    {
        $playerResources = new PlayerResources();
        $playerResources->setCash($cash);

        $player = $this->createMock(Player::class);
        $player->method('getResources')->willReturn($playerResources);
        $player->method('getPlayerResearch')->willReturn(new ArrayCollection());

        return $player;
    }

    private function createResearchPlayer(Research $research, bool $active = false): ResearchPlayer
    {
        $researchPlayer = new ResearchPlayer();
        $researchPlayer->setResearch($research);
        $researchPlayer->setActive($active);
        $researchPlayer->setTimestamp(time());

        return $researchPlayer;
    }

    // ========== PERFORM RESEARCH TESTS ==========

    public function testPerformResearchSuccess(): void
    {
        $research = $this->createResearch(1, 500);
        $player = $this->createPlayer(1000);

        $this->researchRepository->method('find')->with(1)->willReturn($research);
        $this->playerRepository->expects(self::once())->method('save');
        $this->researchPlayerRepository->expects(self::once())->method('save');

        $this->service->performResearch(1, $player);

        self::assertEquals(500, $player->getResources()->getCash());
    }

    public function testPerformResearchDeductsFullCost(): void
    {
        $research = $this->createResearch(1, 1000);
        $player = $this->createPlayer(1000);

        $this->researchRepository->method('find')->with(1)->willReturn($research);

        $this->service->performResearch(1, $player);

        self::assertEquals(0, $player->getResources()->getCash());
    }

    public function testPerformResearchFailsWhenResearchNotFound(): void
    {
        $player = $this->createPlayer();

        $this->researchRepository->method('find')->with(999)->willReturn(null);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('This technology does not exist!');

        $this->service->performResearch(999, $player);
    }

    public function testPerformResearchFailsWhenResearchDisabled(): void
    {
        $research = $this->createResearch(1, 500, false);
        $player = $this->createPlayer();

        $this->researchRepository->method('find')->with(1)->willReturn($research);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('This technology is disabled!');

        $this->service->performResearch(1, $player);
    }

    public function testPerformResearchFailsWhenResearchInProgress(): void
    {
        $research = $this->createResearch(1, 500);
        $otherResearch = $this->createResearch(2, 300);
        $inProgressResearchPlayer = $this->createResearchPlayer($otherResearch, false);

        $player = $this->createMock(Player::class);
        $playerResources = new PlayerResources();
        $playerResources->setCash(1000);
        $player->method('getResources')->willReturn($playerResources);
        $player->method('getPlayerResearch')->willReturn(new ArrayCollection([$inProgressResearchPlayer]));

        $this->researchRepository->method('find')->with(1)->willReturn($research);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('You can only research 1 technology at a time!');

        $this->service->performResearch(1, $player);
    }

    public function testPerformResearchFailsWhenAlreadyResearched(): void
    {
        $research = $this->createResearch(1, 500);
        $completedResearchPlayer = $this->createResearchPlayer($research, true);

        $player = $this->createMock(Player::class);
        $playerResources = new PlayerResources();
        $playerResources->setCash(1000);
        $player->method('getResources')->willReturn($playerResources);
        $player->method('getPlayerResearch')->willReturn(new ArrayCollection([$completedResearchPlayer]));

        $this->researchRepository->method('find')->with(1)->willReturn($research);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('This technology has already been researched!');

        $this->service->performResearch(1, $player);
    }

    public function testPerformResearchFailsWhenPrerequisitesNotMet(): void
    {
        $prerequisite = $this->createResearch(2, 200);
        $researchNeed = new ResearchNeeds();
        $researchNeed->setRequiredResearch($prerequisite);

        $research = $this->createResearch(1, 500);
        $research->setResearchNeeds(new ArrayCollection([$researchNeed]));

        $player = $this->createPlayer(1000);

        $this->researchRepository->method('find')->with(1)->willReturn($research);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('You do not have all required technologies!');

        $this->service->performResearch(1, $player);
    }

    public function testPerformResearchFailsWhenCannotAfford(): void
    {
        $research = $this->createResearch(1, 500);
        $player = $this->createPlayer(100);

        $this->researchRepository->method('find')->with(1)->willReturn($research);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('You can not afford that!');

        $this->service->performResearch(1, $player);
    }

    public function testPerformResearchSucceedsWithPrerequisitesMet(): void
    {
        $prerequisite = $this->createResearch(2, 200);
        $researchNeed = new ResearchNeeds();
        $researchNeed->setRequiredResearch($prerequisite);

        $research = $this->createResearch(1, 500);
        $research->setResearchNeeds(new ArrayCollection([$researchNeed]));

        $completedPrereq = $this->createResearchPlayer($prerequisite, true);

        $player = $this->createMock(Player::class);
        $playerResources = new PlayerResources();
        $playerResources->setCash(1000);
        $player->method('getResources')->willReturn($playerResources);
        $player->method('getPlayerResearch')->willReturn(new ArrayCollection([$completedPrereq]));

        $this->researchRepository->method('find')->with(1)->willReturn($research);
        $this->playerRepository->expects(self::once())->method('save');
        $this->researchPlayerRepository->expects(self::once())->method('save');

        $this->service->performResearch(1, $player);

        self::assertEquals(500, $playerResources->getCash());
    }

    // ========== PERFORM CANCEL TESTS ==========

    public function testPerformCancelRemovesInProgressResearch(): void
    {
        $research = $this->createResearch(1, 500);
        $inProgressResearchPlayer = $this->createResearchPlayer($research, false);

        $player = $this->createMock(Player::class);
        $player->method('getPlayerResearch')->willReturn(new ArrayCollection([$inProgressResearchPlayer]));

        $this->researchRepository->method('find')->with(1)->willReturn($research);
        $this->researchPlayerRepository->expects(self::once())->method('remove')->with($inProgressResearchPlayer);

        $this->service->performCancel(1, $player);
    }

    public function testPerformCancelFailsWhenResearchCompleted(): void
    {
        $research = $this->createResearch(1, 500);
        $completedResearchPlayer = $this->createResearchPlayer($research, true);

        $player = $this->createMock(Player::class);
        $player->method('getPlayerResearch')->willReturn(new ArrayCollection([$completedResearchPlayer]));

        $this->researchRepository->method('find')->with(1)->willReturn($research);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Research project is already completed!');

        $this->service->performCancel(1, $player);
    }

    public function testPerformCancelFailsWhenResearchNotFound(): void
    {
        $player = $this->createPlayer();

        $this->researchRepository->method('find')->with(999)->willReturn(null);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('This technology does not exist!');

        $this->service->performCancel(999, $player);
    }

    public function testPerformCancelDoesNothingWhenPlayerHasNoMatchingResearch(): void
    {
        $research = $this->createResearch(1, 500);

        $player = $this->createMock(Player::class);
        $player->method('getPlayerResearch')->willReturn(new ArrayCollection());

        $this->researchRepository->method('find')->with(1)->willReturn($research);
        $this->researchPlayerRepository->expects(self::never())->method('remove');

        $this->service->performCancel(1, $player);
    }
}
