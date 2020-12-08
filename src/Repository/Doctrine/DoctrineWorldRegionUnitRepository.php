<?php

declare(strict_types=1);

namespace FrankProjects\UltimateWarfare\Repository\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use FrankProjects\UltimateWarfare\Entity\GameUnitType;
use FrankProjects\UltimateWarfare\Entity\Player;
use FrankProjects\UltimateWarfare\Entity\WorldRegionUnit;
use FrankProjects\UltimateWarfare\Repository\WorldRegionUnitRepository;

final class DoctrineWorldRegionUnitRepository implements WorldRegionUnitRepository
{
    private EntityManagerInterface $entityManager;
    private EntityRepository $repository;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
        $this->repository = $this->entityManager->getRepository(WorldRegionUnit::class);
    }

    public function find(int $id): ?WorldRegionUnit
    {
        return $this->repository->find($id);
    }

    /**
     * @param Player $player
     * @return array
     */
    public function findAmountAndNetworthByPlayer(Player $player): array
    {
        return $this->entityManager->createQuery(
            'SELECT wru.amount, gu.networth
              FROM Game:WorldRegionUnit wru
              JOIN Game:WorldRegion wr WITH wru.worldRegion = wr
              JOIN Game:GameUnit gu WITH wru.gameUnit = gu
              WHERE wr.player = :player'
        )->setParameter(
            'player',
            $player
        )->getResult();
    }

    /**
     * @param Player $player
     * @param GameUnitType[] $gameUnitTypes
     * @return array
     */
    public function getGameUnitSumByPlayerAndGameUnitTypes(Player $player, array $gameUnitTypes): array
    {
        $results = $this->entityManager
            ->createQuery(
                'SELECT gu.id, sum(wru.amount) as total
              FROM Game:WorldRegionUnit wru
              JOIN Game:WorldRegion wr WITH wru.worldRegion = wr
              JOIN Game:GameUnit gu WITH wru.gameUnit = gu
              WHERE wr.player = :player AND gu.gameUnitType IN (:gameUnitTypes)
              GROUP BY gu.id'
            )->setParameter('player', $player)
            ->setParameter('gameUnitTypes', $gameUnitTypes)
            ->getArrayResult();

        $gameUnits = [];
        foreach ($results as $result) {
            $gameUnits[$result['id']] = $result['total'];
        }

        return $gameUnits;
    }

    public function remove(WorldRegionUnit $worldRegionUnit): void
    {
        $this->entityManager->remove($worldRegionUnit);
        $this->entityManager->flush();
    }

    public function save(WorldRegionUnit $worldRegionUnit): void
    {
        $this->entityManager->persist($worldRegionUnit);
        $this->entityManager->flush();
    }
}
