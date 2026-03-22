<?php

declare(strict_types=1);

namespace FrankProjects\UltimateWarfare\Entity;

abstract readonly class Operation
{
    public function __construct(
        private string $name,
        private string $image,
        private int $cost,
        private string $description,
        private bool $enabled,
        private float $difficulty,
        private int $maxDistance,
        private string $researchSlug,
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

    public function getResearchSlug(): string
    {
        return $this->researchSlug;
    }

    public function getGameUnitId(): int
    {
        return $this->gameUnitId;
    }
}
