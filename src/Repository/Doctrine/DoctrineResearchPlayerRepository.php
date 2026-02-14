<?php

declare(strict_types=1);

namespace FrankProjects\UltimateWarfare\Repository\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use FrankProjects\UltimateWarfare\Entity\Player;
use FrankProjects\UltimateWarfare\Entity\Research;
use FrankProjects\UltimateWarfare\Entity\ResearchPlayer;
use FrankProjects\UltimateWarfare\Repository\ResearchPlayerRepository;

final class DoctrineResearchPlayerRepository implements ResearchPlayerRepository
{
    private EntityManagerInterface $entityManager;

    /**
     * @var EntityRepository <ResearchPlayer>
     */
    private EntityRepository $repository;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
        $this->repository = $this->entityManager->getRepository(ResearchPlayer::class);

    }

    /**
     * @param int $timestamp
     * @return ResearchPlayer[]
     */
    public function getNonActiveCompletedResearch(int $timestamp): array
    {
        return $this->entityManager->createQuery(
            'SELECT rp
              FROM ' . ResearchPlayer::class . ' rp
              JOIN ' . Research::class . ' r ON rp.research = r
              WHERE rp.active = 0 AND (rp.timestamp + r.timestamp) < :timestamp'
        )->setParameter(
            'timestamp',
            $timestamp
        )->getResult();
    }

    /**
     * @return ResearchPlayer[]
     */
    public function getAllNonActiveResearch(): array
    {
        return $this->repository->findBy(['active' => 0]);
    }

    /**
     * @param Player $player
     * @return ResearchPlayer[]
     */
    public function findFinishedByPlayer(Player $player): array
    {
        return $this->entityManager->createQuery(
            'SELECT rp
              FROM ' . ResearchPlayer::class . ' rp
              WHERE rp.player = :player AND rp.active = 1
              ORDER BY rp.timestamp DESC'
        )->setParameter(
            'player',
            $player
        )->getResult();
    }

    public function remove(ResearchPlayer $researchPlayer): void
    {
        $this->entityManager->remove($researchPlayer);
        $this->entityManager->flush();
    }

    public function save(ResearchPlayer $researchPlayer): void
    {
        $this->entityManager->persist($researchPlayer);
        $this->entityManager->flush();
    }
}
