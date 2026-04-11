<?php

declare(strict_types=1);

namespace FrankProjects\UltimateWarfare\Tests\Repository;

use Doctrine\Common\Collections\ArrayCollection;
use FrankProjects\UltimateWarfare\Entity\Operation\SpyOperation;
use FrankProjects\UltimateWarfare\Entity\Player;
use FrankProjects\UltimateWarfare\Entity\ResearchPlayer;
use FrankProjects\UltimateWarfare\Repository\OperationRegistry;
use PHPUnit\Framework\TestCase;

class OperationRegistryTest extends TestCase
{
    private OperationRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new OperationRegistry();
    }

    public function testFindReturnsOperationBySlug(): void
    {
        $operation = $this->registry->find('spy');
        self::assertNotNull($operation);
        self::assertInstanceOf(SpyOperation::class, $operation);
    }

    public function testFindReturnsNullForUnknownSlug(): void
    {
        self::assertNull($this->registry->find('nonexistent'));
    }

    public function testFindAllReturnsAllOperations(): void
    {
        $operations = $this->registry->findAll();
        self::assertCount(11, $operations);
    }

    public function testFindEnabledReturnsOnlyEnabledOperations(): void
    {
        $operations = $this->registry->findEnabled();

        foreach ($operations as $operation) {
            self::assertTrue($operation->isEnabled());
        }
    }

    public function testFindAvailableForPlayerFiltersOnActiveResearch(): void
    {
        $researchPlayer = $this->createMock(ResearchPlayer::class);
        $researchPlayer->method('getActive')->willReturn(true);
        $researchPlayer->method('getResearchSlug')->willReturn('spy-technology');

        $player = $this->createMock(Player::class);
        $player->method('getPlayerResearch')->willReturn(new ArrayCollection([$researchPlayer]));

        $operations = $this->registry->findAvailableForPlayer($player);

        self::assertNotEmpty($operations);
        foreach ($operations as $operation) {
            self::assertSame('spy-technology', $operation->getResearchSlug());
        }
    }

    public function testFindAvailableForPlayerReturnsEmptyWhenNoResearch(): void
    {
        $player = $this->createMock(Player::class);
        $player->method('getPlayerResearch')->willReturn(new ArrayCollection());

        $operations = $this->registry->findAvailableForPlayer($player);

        self::assertEmpty($operations);
    }

    public function testFindAvailableForPlayerExcludesInProgressResearch(): void
    {
        $researchPlayer = $this->createMock(ResearchPlayer::class);
        $researchPlayer->method('getActive')->willReturn(false);
        $researchPlayer->method('getResearchSlug')->willReturn('spy-technology');

        $player = $this->createMock(Player::class);
        $player->method('getPlayerResearch')->willReturn(new ArrayCollection([$researchPlayer]));

        $operations = $this->registry->findAvailableForPlayer($player);

        self::assertEmpty($operations);
    }
}
