<?php

declare(strict_types=1);

namespace FrankProjects\UltimateWarfare\Repository;

use FrankProjects\UltimateWarfare\Entity\Operation;
use FrankProjects\UltimateWarfare\Entity\Operation\AdvancedSpy2Operation;
use FrankProjects\UltimateWarfare\Entity\Operation\AdvancedSpyOperation;
use FrankProjects\UltimateWarfare\Entity\Operation\MissileAttackOperation;
use FrankProjects\UltimateWarfare\Entity\Operation\NuclearMissileAttackOperation;
use FrankProjects\UltimateWarfare\Entity\Operation\SniperAttackOperation;
use FrankProjects\UltimateWarfare\Entity\Operation\SpyOperation;
use FrankProjects\UltimateWarfare\Entity\Operation\StealthBomberAttackOperation;
use FrankProjects\UltimateWarfare\Entity\Operation\SubmarineAttackOperation;
use FrankProjects\UltimateWarfare\Entity\Player;

final class OperationRegistry
{
    /** @var array<string, Operation> */
    private array $operations;

    public function __construct()
    {
        $operationList = [
            new MissileAttackOperation(),
            new StealthBomberAttackOperation(),
            new SpyOperation(),
            new SniperAttackOperation(),
            new NuclearMissileAttackOperation(),
            new SubmarineAttackOperation(),
            new AdvancedSpyOperation(),
            new AdvancedSpy2Operation(),
        ];

        $this->operations = [];
        foreach ($operationList as $operation) {
            $this->operations[$operation->getSlug()] = $operation;
        }
    }

    public function find(string $slug): ?Operation
    {
        return $this->operations[$slug] ?? null;
    }

    /**
     * @return Operation[]
     */
    public function findAll(): array
    {
        return array_values($this->operations);
    }

    /**
     * @return Operation[]
     */
    public function findEnabled(): array
    {
        return array_values(
            array_filter($this->operations, static fn (Operation $o): bool => $o->isEnabled())
        );
    }

    /**
     * @return Operation[]
     */
    public function findAvailableForPlayer(Player $player): array
    {
        $activeResearchSlugs = [];
        foreach ($player->getPlayerResearch() as $playerResearch) {
            if ($playerResearch->getActive() === true) {
                $activeResearchSlugs[] = $playerResearch->getResearchSlug();
            }
        }

        return array_values(
            array_filter(
                $this->operations,
                static fn (Operation $o): bool => $o->isEnabled()
                    && in_array($o->getResearchSlug(), $activeResearchSlugs, true)
            )
        );
    }
}
