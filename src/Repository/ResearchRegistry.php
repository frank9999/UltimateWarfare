<?php

declare(strict_types=1);

namespace FrankProjects\UltimateWarfare\Repository;

use FrankProjects\UltimateWarfare\Entity\Research;
use FrankProjects\UltimateWarfare\Entity\Research\AdvancedOpticsResearch;
use FrankProjects\UltimateWarfare\Entity\Research\AdvancedSpy2TechnologyResearch;
use FrankProjects\UltimateWarfare\Entity\Research\AdvancedSpyTechnologyResearch;
use FrankProjects\UltimateWarfare\Entity\Research\BallisticMissileTechnologyResearch;
use FrankProjects\UltimateWarfare\Entity\Research\FactoryBlueprintResearch;
use FrankProjects\UltimateWarfare\Entity\Research\NavalBombardmentResearch;
use FrankProjects\UltimateWarfare\Entity\Research\NuclearTechnologyResearch;
use FrankProjects\UltimateWarfare\Entity\Research\RadarTechnologyResearch;
use FrankProjects\UltimateWarfare\Entity\Research\SubmarineTechnologyResearch;
use FrankProjects\UltimateWarfare\Entity\Research\ResearchLevel10Research;
use FrankProjects\UltimateWarfare\Entity\Research\ResearchLevel1Research;
use FrankProjects\UltimateWarfare\Entity\Research\ResearchLevel2Research;
use FrankProjects\UltimateWarfare\Entity\Research\ResearchLevel3Research;
use FrankProjects\UltimateWarfare\Entity\Research\ResearchLevel4Research;
use FrankProjects\UltimateWarfare\Entity\Research\ResearchLevel5Research;
use FrankProjects\UltimateWarfare\Entity\Research\ResearchLevel6Research;
use FrankProjects\UltimateWarfare\Entity\Research\ResearchLevel7Research;
use FrankProjects\UltimateWarfare\Entity\Research\ResearchLevel8Research;
use FrankProjects\UltimateWarfare\Entity\Research\ResearchLevel9Research;
use FrankProjects\UltimateWarfare\Entity\Research\SpecialOperations2Research;
use FrankProjects\UltimateWarfare\Entity\Research\SpecialOperations3Research;
use FrankProjects\UltimateWarfare\Entity\Research\SpecialOperations4Research;
use FrankProjects\UltimateWarfare\Entity\Research\SpecialOperationsResearch;
use FrankProjects\UltimateWarfare\Entity\Research\SpyTechnologyResearch;

final class ResearchRegistry
{
    /** @var array<string, Research> */
    private array $researches;

    public function __construct()
    {
        $researchList = [
            new ResearchLevel1Research(),
            new ResearchLevel2Research(),
            new ResearchLevel3Research(),
            new ResearchLevel4Research(),
            new ResearchLevel5Research(),
            new ResearchLevel6Research(),
            new ResearchLevel7Research(),
            new ResearchLevel8Research(),
            new ResearchLevel9Research(),
            new ResearchLevel10Research(),
            new SpecialOperationsResearch(),
            new SpecialOperations2Research(),
            new SpecialOperations3Research(),
            new SpecialOperations4Research(),
            new SpyTechnologyResearch(),
            new AdvancedSpyTechnologyResearch(),
            new AdvancedSpy2TechnologyResearch(),
            new NuclearTechnologyResearch(),
            new FactoryBlueprintResearch(),
            new AdvancedOpticsResearch(),
            new SubmarineTechnologyResearch(),
            new BallisticMissileTechnologyResearch(),
            new RadarTechnologyResearch(),
            new NavalBombardmentResearch(),
        ];

        $this->researches = [];
        foreach ($researchList as $research) {
            $this->researches[$research->getSlug()] = $research;
        }
    }

    public function find(string $slug): ?Research
    {
        return $this->researches[$slug] ?? null;
    }

    /**
     * @return Research[]
     */
    public function findAll(): array
    {
        return array_values($this->researches);
    }

    /**
     * @return Research[]
     */
    public function findEnabled(): array
    {
        return array_values(
            array_filter($this->researches, static fn (Research $r): bool => $r->isEnabled())
        );
    }

    /**
     * @param string[] $completedSlugs
     * @return Research[]
     */
    public function findAvailableForPlayer(array $completedSlugs): array
    {
        return array_values(
            array_filter($this->researches, static function (Research $research) use ($completedSlugs): bool {
                if (!$research->isEnabled()) {
                    return false;
                }

                if (in_array($research->getSlug(), $completedSlugs, true)) {
                    return false;
                }

                foreach ($research->getPrerequisiteSlugs() as $prerequisiteSlug) {
                    if (!in_array($prerequisiteSlug, $completedSlugs, true)) {
                        return false;
                    }
                }

                return true;
            })
        );
    }
}
