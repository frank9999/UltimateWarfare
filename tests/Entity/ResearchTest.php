<?php

declare(strict_types=1);

namespace FrankProjects\UltimateWarfare\Tests\Entity;

use FrankProjects\UltimateWarfare\Entity\Research;
use FrankProjects\UltimateWarfare\Entity\Research\AdvancedOpticsResearch;
use FrankProjects\UltimateWarfare\Entity\Research\BallisticMissileTechnologyResearch;
use FrankProjects\UltimateWarfare\Entity\Research\FactoryBlueprintResearch;
use FrankProjects\UltimateWarfare\Entity\Research\NavalBombardmentResearch;
use FrankProjects\UltimateWarfare\Entity\Research\NuclearTechnologyResearch;
use FrankProjects\UltimateWarfare\Entity\Research\RadarTechnologyResearch;
use FrankProjects\UltimateWarfare\Entity\Research\ResearchLevelResearch;
use FrankProjects\UltimateWarfare\Entity\Research\SpecialOperationsResearch;
use FrankProjects\UltimateWarfare\Entity\Research\SpyTechnologyResearch;
use FrankProjects\UltimateWarfare\Entity\Research\SubmarineTechnologyResearch;
use InvalidArgumentException;
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
            'research-level' => [ResearchLevelResearch::class],
            'special-operations' => [SpecialOperationsResearch::class],
            'spy-technology' => [SpyTechnologyResearch::class],
            'nuclear-technology' => [NuclearTechnologyResearch::class],
            'factory-blueprint' => [FactoryBlueprintResearch::class],
            'advanced-optics' => [AdvancedOpticsResearch::class],
            'submarine-technology' => [SubmarineTechnologyResearch::class],
            'ballistic-missile-technology' => [BallisticMissileTechnologyResearch::class],
            'radar-technology' => [RadarTechnologyResearch::class],
            'naval-bombardment' => [NavalBombardmentResearch::class],
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
        self::assertGreaterThan(0, $research->getMaxLevel());

        for ($level = 1; $level <= $research->getMaxLevel(); $level++) {
            self::assertGreaterThan(0, $research->getCost($level));
            self::assertGreaterThan(0, $research->getTimestamp($level));
        }
    }

    /**
     * @param class-string<Research> $researchClass
     */
    #[DataProvider('researchProvider')]
    public function testPrerequisitesReferenceValidResearchClasses(string $researchClass): void
    {
        $research = new $researchClass();
        self::assertGreaterThan(0, $research->getMaxLevel());

        for ($level = 1; $level <= $research->getMaxLevel(); $level++) {
            foreach ($research->getPrerequisites($level) as $prereqClass => $minLevel) {
                self::assertGreaterThan(0, $minLevel);
                $prerequisite = new $prereqClass();
                self::assertInstanceOf(Research::class, $prerequisite);
                self::assertGreaterThanOrEqual($minLevel, $prerequisite->getMaxLevel());
            }
        }
    }

    public function testInvalidLevelThrows(): void
    {
        $research = new ResearchLevelResearch();

        $this->expectException(InvalidArgumentException::class);
        $research->getCost($research->getMaxLevel() + 1);
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

    public function testResearchLevelLevel1HasNoPrerequisites(): void
    {
        $research = new ResearchLevelResearch();
        self::assertEmpty($research->getPrerequisites(1));
    }

    public function testResearchLevelHasTenLevels(): void
    {
        self::assertSame(10, (new ResearchLevelResearch())->getMaxLevel());
    }

    public function testSpecialOperationsLevel3RequiresResearchLevel3(): void
    {
        $research = new SpecialOperationsResearch();
        self::assertSame(
            [ResearchLevelResearch::class => 3],
            $research->getPrerequisites(3)
        );
    }

    public function testSpyTechnologyLevel1RequiresSpecialOps1(): void
    {
        $research = new SpyTechnologyResearch();
        self::assertSame(
            [SpecialOperationsResearch::class => 1],
            $research->getPrerequisites(1)
        );
    }

    public function testGetPrerequisiteDescriptionsReturnsSlugAndName(): void
    {
        $research = new SpecialOperationsResearch();
        $descriptions = $research->getPrerequisiteDescriptions(2);

        self::assertCount(1, $descriptions);
        self::assertSame('research-level', $descriptions[0]['slug']);
        self::assertSame('Research', $descriptions[0]['name']);
        self::assertSame(2, $descriptions[0]['minLevel']);
    }
}
