<?php

declare(strict_types=1);

namespace FrankProjects\UltimateWarfare\Entity;

abstract readonly class Research
{
    /**
     * @param array<class-string<Research>> $prerequisites
     */
    public function __construct(
        private string $name,
        private string $image,
        private int $cost,
        private int $timestamp,
        private string $description,
        private bool $enabled,
        private array $prerequisites = [],
    ) {
    }

    abstract public function getSlug(): string;

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

    public function getTimestamp(): int
    {
        return $this->timestamp;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * @return array<class-string<Research>>
     */
    public function getPrerequisites(): array
    {
        return $this->prerequisites;
    }

    /**
     * @return string[]
     */
    public function getPrerequisiteNames(): array
    {
        $names = [];
        foreach ($this->prerequisites as $prerequisiteClass) {
            $prerequisite = new $prerequisiteClass();
            $names[] = $prerequisite->getName();
        }

        return $names;
    }

    /**
     * @return string[]
     */
    public function getPrerequisiteSlugs(): array
    {
        $slugs = [];
        foreach ($this->prerequisites as $prerequisiteClass) {
            $prerequisite = new $prerequisiteClass();
            $slugs[] = $prerequisite->getSlug();
        }

        return $slugs;
    }
}
