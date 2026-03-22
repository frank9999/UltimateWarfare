<?php

declare(strict_types=1);

namespace FrankProjects\UltimateWarfare\Tests\Entity;

use FrankProjects\UltimateWarfare\Entity\Research;
use FrankProjects\UltimateWarfare\Entity\Research\AdvancedSpyTechnologyResearch;
use FrankProjects\UltimateWarfare\Entity\Research\NuclearTechnologyResearch;
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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ResearchTest extends TestCase
{
    /**
     * @return array<string, array{class-string<Research>}>
     */
    public static function researchProvider(): array
    {
        return [
            'research-level-1' => [ResearchLevel1Research::class],
            'research-level-2' => [ResearchLevel2Research::class],
            'research-level-3' => [ResearchLevel3Research::class],
            'research-level-4' => [ResearchLevel4Research::class],
            'research-level-5' => [ResearchLevel5Research::class],
            'research-level-6' => [ResearchLevel6Research::class],
            'research-level-7' => [ResearchLevel7Research::class],
            'research-level-8' => [ResearchLevel8Research::class],
            'research-level-9' => [ResearchLevel9Research::class],
            'research-level-10' => [ResearchLevel10Research::class],
            'special-operations' => [SpecialOperationsResearch::class],
            'special-operations-2' => [SpecialOperations2Research::class],
            'special-operations-3' => [SpecialOperations3Research::class],
            'special-operations-4' => [SpecialOperations4Research::class],
            'spy-technology' => [SpyTechnologyResearch::class],
            'advanced-spy-technology' => [AdvancedSpyTechnologyResearch::class],
            'nuclear-technology' => [NuclearTechnologyResearch::class],
        ];
    }

    /**
     * @param class-string<Research> $researchClass
     */
    #[DataProvider('researchProvider')]
    public function testGetSlugReturnsNonEmptyString(string $researchClass): void
    {
        $research = new $researchClass();
        self::assertNotEmpty($research->getSlug());
    }

    /**
     * @param class-string<Research> $researchClass
     */
    #[DataProvider('researchProvider')]
    public function testResearchHasValidProperties(string $researchClass): void
    {
        $research = new $researchClass();

        self::assertNotEmpty($research->getName());
        self::assertNotEmpty($research->getImage());
        self::assertNotEmpty($research->getDescription());
        self::assertGreaterThan(0, $research->getCost());
        self::assertGreaterThan(0, $research->getTimestamp());
    }

    /**
     * @param class-string<Research> $researchClass
     */
    #[DataProvider('researchProvider')]
    public function testPrerequisitesAreValidResearchClasses(string $researchClass): void
    {
        $research = new $researchClass();
        $prerequisites = $research->getPrerequisites();

        self::assertGreaterThanOrEqual(0, count($prerequisites));

        foreach ($prerequisites as $prerequisiteClass) {
            $prerequisite = new $prerequisiteClass();
            self::assertInstanceOf(Research::class, $prerequisite);
        }
    }

    /**
     * @param class-string<Research> $researchClass
     */
    #[DataProvider('researchProvider')]
    public function testGetPrerequisiteSlugsMatchesPrerequisiteClasses(string $researchClass): void
    {
        $research = new $researchClass();
        $prerequisites = $research->getPrerequisites();
        $slugs = $research->getPrerequisiteSlugs();

        self::assertCount(count($prerequisites), $slugs);

        foreach ($prerequisites as $i => $prerequisiteClass) {
            $expected = (new $prerequisiteClass())->getSlug();
            self::assertSame($expected, $slugs[$i]);
        }
    }

    /**
     * @param class-string<Research> $researchClass
     */
    #[DataProvider('researchProvider')]
    public function testGetPrerequisiteNamesMatchesPrerequisiteClasses(string $researchClass): void
    {
        $research = new $researchClass();
        $prerequisites = $research->getPrerequisites();
        $names = $research->getPrerequisiteNames();

        self::assertCount(count($prerequisites), $names);

        foreach ($prerequisites as $i => $prerequisiteClass) {
            $expected = (new $prerequisiteClass())->getName();
            self::assertSame($expected, $names[$i]);
        }
    }

    public function testAllResearchHaveUniqueSlugs(): void
    {
        $researches = array_map(
            static fn (array $data): Research => new $data[0](),
            self::researchProvider()
        );

        $slugs = array_map(
            static fn (Research $r): string => $r->getSlug(),
            $researches
        );

        self::assertCount(count($slugs), array_unique($slugs), 'Duplicate research slugs found');
    }

    public function testResearchLevel1HasNoPrerequisites(): void
    {
        $research = new ResearchLevel1Research();
        self::assertEmpty($research->getPrerequisites());
    }

    public function testResearchLevel2RequiresLevel1(): void
    {
        $research = new ResearchLevel2Research();
        self::assertSame([ResearchLevel1Research::class], $research->getPrerequisites());
    }

    public function testSpyTechnologyRequiresLevel2AndSpecialOps(): void
    {
        $research = new SpyTechnologyResearch();
        self::assertSame(
            [ResearchLevel2Research::class, SpecialOperationsResearch::class],
            $research->getPrerequisites()
        );
    }
}
