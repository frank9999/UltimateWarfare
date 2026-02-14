<?php

declare(strict_types=1);

namespace FrankProjects\UltimateWarfare\Repository;

use FrankProjects\UltimateWarfare\Entity\Enum\GameUnitCategory;
use FrankProjects\UltimateWarfare\Entity\GameUnit;

interface GameUnitRepository
{
    public function find(int $id): ?GameUnit;

    /**
     * @return GameUnit[]
     */
    public function findAll(): array;

    /**
     * @return GameUnit[]
     */
    public function findByGameUnitCategory(GameUnitCategory $gameUnitCategory): array;

    /**
     * @param GameUnitCategory[] $gameUnitCategories
     * @return GameUnit[]
     */
    public function findByGameUnitCategories(array $gameUnitCategories): array;

    public function save(GameUnit $gameUnit): void;
}
