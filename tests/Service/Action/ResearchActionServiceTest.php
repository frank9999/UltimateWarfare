<?php

declare(strict_types=1);

namespace FrankProjects\UltimateWarfare\Tests\Service\Action;

use Doctrine\Common\Collections\ArrayCollection;
use FrankProjects\UltimateWarfare\Entity\Player;
use FrankProjects\UltimateWarfare\Entity\Player\Resources as PlayerResources;
use FrankProjects\UltimateWarfare\Entity\Research\ResearchLevel1Research;
use FrankProjects\UltimateWarfare\Entity\Research\ResearchLevel2Research;
use FrankProjects\UltimateWarfare\Entity\ResearchPlayer;
use FrankProjects\UltimateWarfare\Repository\PlayerRepository;
use FrankProjects\UltimateWarfare\Repository\ResearchPlayerRepository;
use FrankProjects\UltimateWarfare\Repository\ResearchRegistry;
use FrankProjects\UltimateWarfare\Service\Action\ResearchActionService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ResearchActionServiceTest extends TestCase
{
    private ResearchActionService $service;
    private ResearchRegistry $researchRegistry;
    private ResearchPlayerRepository&MockObject $researchPlayerRepository;
    private PlayerRepository&MockObject $playerRepository;

    protected function setUp(): void
    {
        $this->researchRegistry = new ResearchRegistry();
        $this->researchPlayerRepository = $this->createMock(ResearchPlayerRepository::class);
        $this->playerRepository = $this->createMock(PlayerRepository::class);

        $this->service = new ResearchActionService(
            $this->researchRegistry,
            $this->researchPlayerRepository,
            $this->playerRepository
        );
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

    private function createResearchPlayer(string $researchSlug, bool $active = false): ResearchPlayer
    {
        $researchPlayer = new ResearchPlayer();
        $researchPlayer->setResearchSlug($researchSlug);
        $researchPlayer->setActive($active);
        $researchPlayer->setTimestamp(time());
        $researchPlayer->setCompletionTimestamp(time() + 180);

        return $researchPlayer;
    }

    // ========== PERFORM RESEARCH TESTS ==========

    public function testPerformResearchSuccess(): void
    {
        $player = $this->createPlayer(5000);

        $this->playerRepository->expects(self::once())->method('save');
        $this->researchPlayerRepository->expects(self::once())->method('save');

        $this->service->performResearch('research-level-1', $player);

        self::assertEquals(2500, $player->getResources()->getCash());
    }

    public function testPerformResearchDeductsFullCost(): void
    {
        $player = $this->createPlayer(2500);

        $this->service->performResearch('research-level-1', $player);

        self::assertEquals(0, $player->getResources()->getCash());
    }

    public function testPerformResearchFailsWhenResearchNotFound(): void
    {
        $player = $this->createPlayer();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('This technology does not exist!');

        $this->service->performResearch('nonexistent-research', $player);
    }

    public function testPerformResearchFailsWhenResearchInProgress(): void
    {
        $inProgressResearchPlayer = $this->createResearchPlayer('research-level-2', false);

        $player = $this->createMock(Player::class);
        $playerResources = new PlayerResources();
        $playerResources->setCash(100000);
        $player->method('getResources')->willReturn($playerResources);
        $player->method('getPlayerResearch')->willReturn(new ArrayCollection([$inProgressResearchPlayer]));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('You can only research 1 technology at a time!');

        $this->service->performResearch('research-level-1', $player);
    }

    public function testPerformResearchFailsWhenAlreadyResearched(): void
    {
        $completedResearchPlayer = $this->createResearchPlayer('research-level-1', true);

        $player = $this->createMock(Player::class);
        $playerResources = new PlayerResources();
        $playerResources->setCash(100000);
        $player->method('getResources')->willReturn($playerResources);
        $player->method('getPlayerResearch')->willReturn(new ArrayCollection([$completedResearchPlayer]));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('This technology has already been researched!');

        $this->service->performResearch('research-level-1', $player);
    }

    public function testPerformResearchFailsWhenPrerequisitesNotMet(): void
    {
        $player = $this->createPlayer(100000);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('You do not have all required technologies!');

        $this->service->performResearch('research-level-2', $player);
    }

    public function testPerformResearchFailsWhenCannotAfford(): void
    {
        $player = $this->createPlayer(100);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('You can not afford that!');

        $this->service->performResearch('research-level-1', $player);
    }

    public function testPerformResearchSucceedsWithPrerequisitesMet(): void
    {
        $completedPrereq = $this->createResearchPlayer('research-level-1', true);

        $player = $this->createMock(Player::class);
        $playerResources = new PlayerResources();
        $playerResources->setCash(100000);
        $player->method('getResources')->willReturn($playerResources);
        $player->method('getPlayerResearch')->willReturn(new ArrayCollection([$completedPrereq]));

        $this->playerRepository->expects(self::once())->method('save');
        $this->researchPlayerRepository->expects(self::once())->method('save');

        $this->service->performResearch('research-level-2', $player);

        self::assertEquals(85000, $playerResources->getCash());
    }

    // ========== PERFORM CANCEL TESTS ==========

    public function testPerformCancelRemovesInProgressResearch(): void
    {
        $inProgressResearchPlayer = $this->createResearchPlayer('research-level-1', false);

        $player = $this->createMock(Player::class);
        $player->method('getPlayerResearch')->willReturn(new ArrayCollection([$inProgressResearchPlayer]));

        $this->researchPlayerRepository->expects(self::once())->method('remove')->with($inProgressResearchPlayer);

        $this->service->performCancel('research-level-1', $player);
    }

    public function testPerformCancelFailsWhenResearchCompleted(): void
    {
        $completedResearchPlayer = $this->createResearchPlayer('research-level-1', true);

        $player = $this->createMock(Player::class);
        $player->method('getPlayerResearch')->willReturn(new ArrayCollection([$completedResearchPlayer]));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Research project is already completed!');

        $this->service->performCancel('research-level-1', $player);
    }

    public function testPerformCancelFailsWhenResearchNotFound(): void
    {
        $player = $this->createPlayer();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('This technology does not exist!');

        $this->service->performCancel('nonexistent-research', $player);
    }

    public function testPerformCancelDoesNothingWhenPlayerHasNoMatchingResearch(): void
    {
        $player = $this->createMock(Player::class);
        $player->method('getPlayerResearch')->willReturn(new ArrayCollection());

        $this->researchPlayerRepository->expects(self::never())->method('remove');

        $this->service->performCancel('research-level-1', $player);
    }
}
