<?php

declare(strict_types=1);

namespace FrankProjects\UltimateWarfare\Repository\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use FrankProjects\UltimateWarfare\Entity\Enum\GameUnitCategory;
use FrankProjects\UltimateWarfare\Entity\Enum\GameUnitEnum;
use FrankProjects\UltimateWarfare\Entity\Player;
use FrankProjects\UltimateWarfare\Entity\WorldRegion;
use FrankProjects\UltimateWarfare\Entity\WorldRegionUnit;
use FrankProjects\UltimateWarfare\Repository\GameUnitRegistry;
use FrankProjects\UltimateWarfare\Repository\WorldRegionUnitRepository;

final class DoctrineWorldRegionUnitRepository implements WorldRegionUnitRepository
{
    private EntityManagerInterface $entityManager;

    /**
     * @var EntityRepository<WorldRegionUnit>
     */
    private EntityRepository $repository;

    private GameUnitRegistry $gameUnitRegistry;

    public function __construct(EntityManagerInterface $entityManager, GameUnitRegistry $gameUnitRegistry)
    {
        $this->entityManager = $entityManager;
        $this->repository = $this->entityManager->getRepository(WorldRegionUnit::class);
        $this->gameUnitRegistry = $gameUnitRegistry;
    }

    public function find(int $id): ?WorldRegionUnit
    {
        return $this->repository->find($id);
    }

    /**
     * @param Player $player
     * @return array<int, array<string, int>>
     */
    public function findAmountAndNetWorthByPlayer(Player $player): array
    {
        $results = $this->entityManager->createQuery(
            'SELECT wru.gameUnit, wru.amount
              FROM ' . WorldRegionUnit::class . ' wru
              JOIN ' . WorldRegion::class . ' wr ON wru.worldRegion = wr
              WHERE wr.player = :player'
        )->setParameter(
            'player',
            $player
        )->getArrayResult();

        $data = [];
        /** @var array{gameUnit: GameUnitEnum, amount: int} $result */
        foreach ($results as $result) {
            $gameUnit = $this->gameUnitRegistry->find($result['gameUnit']);
            $data[] = [
                'amount' => $result['amount'],
                'netWorth' => $gameUnit->getNetWorth(),
            ];
        }

        return $data;
    }

    /**
     * @param Player $player
     * @param GameUnitCategory[] $gameUnitCategories
     * @return array<int|string, int>
     */
    public function getGameUnitSumByPlayerAndGameUnitCategories(Player $player, array $gameUnitCategories): array
    {
        $unitIds = [];
        foreach ($gameUnitCategories as $category) {
            foreach ($this->gameUnitRegistry->getIdsByCategory($category) as $id) {
                $unitIds[] = $id;
            }
        }

        if ($unitIds === []) {
            return [];
        }

        $results = $this->entityManager
            ->createQuery(
                'SELECT wru.gameUnit, sum(wru.amount) as total
              FROM ' . WorldRegionUnit::class . ' wru
              JOIN ' . WorldRegion::class . ' wr ON wru.worldRegion = wr
              WHERE wr.player = :player AND wru.gameUnit IN (:unitIds)
              GROUP BY wru.gameUnit'
            )->setParameter('player', $player)
            ->setParameter('unitIds', $unitIds)
            ->getArrayResult();

        $gameUnits = [];
        /** @var array{gameUnit: GameUnitEnum, total: int} $result */
        foreach ($results as $result) {
            $gameUnits[$result['gameUnit']->value] = $result['total'];
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
