<?php

declare(strict_types=1);

namespace FrankProjects\UltimateWarfare\Entity;

abstract readonly class Operation
{
    /**
     * @param class-string<Research> $researchClass
     */
    public function __construct(
        private string $name,
        private string $image,
        private int $cost,
        private string $description,
        private bool $enabled,
        private float $difficulty,
        private int $maxDistance,
        private string $researchClass,
        private int $gameUnitId,
    ) {
    }

    abstract public function getSlug(): string;

    abstract public function getProcessorClass(): string;

    public function getName(): string
    {
        return $this->name;
    }

    public function getImage(): string
    {
        return $this->image;
    }

    public function getCost(): int
    {
        return $this->cost;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function getDifficulty(): float
    {
        return $this->difficulty;
    }

    public function getMaxDistance(): int
    {
        return $this->maxDistance;
    }

    /**
     * @return class-string<Research>
     */
    public function getResearchClass(): string
    {
        return $this->researchClass;
    }

    public function getResearchSlug(): string
    {
        return (new $this->researchClass())->getSlug();
    }

    public function getResearchName(): string
    {
        return (new $this->researchClass())->getName();
    }

    public function getGameUnitId(): int
    {
        return $this->gameUnitId;
    }

    public function hasCooldown(): bool
    {
        return false;
    }
}
