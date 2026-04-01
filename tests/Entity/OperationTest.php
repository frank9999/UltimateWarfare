<?php

declare(strict_types=1);

namespace FrankProjects\UltimateWarfare\Tests\Entity;

use FrankProjects\UltimateWarfare\Entity\Operation;
use FrankProjects\UltimateWarfare\Entity\Operation\AdvancedSpy2Operation;
use FrankProjects\UltimateWarfare\Entity\Operation\AdvancedSpyOperation;
use FrankProjects\UltimateWarfare\Entity\Operation\BomberAttackOperation;
use FrankProjects\UltimateWarfare\Entity\Operation\MissileAttackOperation;
use FrankProjects\UltimateWarfare\Entity\Operation\NuclearMissileAttackOperation;
use FrankProjects\UltimateWarfare\Entity\Operation\SniperAttackOperation;
use FrankProjects\UltimateWarfare\Entity\Operation\SpyOperation;
use FrankProjects\UltimateWarfare\Entity\Operation\StrategicBomberAttackOperation;
use FrankProjects\UltimateWarfare\Entity\Operation\SubmarineAttackOperation;
use FrankProjects\UltimateWarfare\Entity\Research;
use FrankProjects\UltimateWarfare\Service\OperationEngine\OperationInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class OperationTest extends TestCase
{
    /**
     * @return array<string, array{class-string<Operation>, string}>
     */
    public static function operationProvider(): array
    {
        return [
            'spy' => [SpyOperation::class, 'spy'],
            'advanced-spy' => [AdvancedSpyOperation::class, 'advanced-spy'],
            'advanced-spy-2' => [AdvancedSpy2Operation::class, 'advanced-spy-2'],
            'sniper-attack' => [SniperAttackOperation::class, 'sniper-attack'],
            'missile-attack' => [MissileAttackOperation::class, 'missile-attack'],
            'submarine-attack' => [SubmarineAttackOperation::class, 'submarine-attack'],
            'bomber-attack' => [BomberAttackOperation::class, 'bomber-attack'],
            'strategic-bomber-attack' => [StrategicBomberAttackOperation::class, 'strategic-bomber-attack'],
            'nuclear-missile-attack' => [NuclearMissileAttackOperation::class, 'nuclear-missile-attack'],
        ];
    }

    /**
     * @param class-string<Operation> $operationClass
     */
    #[DataProvider('operationProvider')]
    public function testGetSlugReturnsExpectedSlug(string $operationClass, string $expectedSlug): void
    {
        $operation = new $operationClass();
        self::assertSame($expectedSlug, $operation->getSlug());
    }

    /**
     * @param class-string<Operation> $operationClass
     */
    #[DataProvider('operationProvider')]
    public function testGetResearchClassReturnsValidResearchSubclass(string $operationClass): void
    {
        $operation = new $operationClass();
        $research = new ($operation->getResearchClass())();

        self::assertInstanceOf(Research::class, $research);
    }

    /**
     * @param class-string<Operation> $operationClass
     */
    #[DataProvider('operationProvider')]
    public function testGetResearchSlugDerivedFromClass(string $operationClass): void
    {
        $operation = new $operationClass();
        $researchClass = $operation->getResearchClass();
        $expectedSlug = (new $researchClass())->getSlug();

        self::assertSame($expectedSlug, $operation->getResearchSlug());
    }

    /**
     * @param class-string<Operation> $operationClass
     */
    #[DataProvider('operationProvider')]
    public function testGetResearchNameReturnsNonEmptyString(string $operationClass): void
    {
        $operation = new $operationClass();
        self::assertNotEmpty($operation->getResearchName());
    }

    /**
     * @param class-string<Operation> $operationClass
     */
    #[DataProvider('operationProvider')]
    public function testGetProcessorClassReturnsValidOperationInterface(string $operationClass): void
    {
        $operation = new $operationClass();
        $processorClass = $operation->getProcessorClass();

        self::assertTrue(class_exists($processorClass), "Processor class {$processorClass} does not exist");
        self::assertTrue(
            is_subclass_of($processorClass, OperationInterface::class),
            "{$processorClass} does not implement OperationInterface"
        );
    }

    /**
     * @param class-string<Operation> $operationClass
     */
    #[DataProvider('operationProvider')]
    public function testOperationHasValidProperties(string $operationClass): void
    {
        $operation = new $operationClass();

        self::assertNotEmpty($operation->getName());
        self::assertNotEmpty($operation->getImage());
        self::assertNotEmpty($operation->getDescription());
        self::assertGreaterThan(0, $operation->getCost());
        self::assertGreaterThanOrEqual(0.0, $operation->getDifficulty());
        self::assertLessThanOrEqual(1.0, $operation->getDifficulty());
        self::assertGreaterThan(0, $operation->getMaxDistance());
        self::assertGreaterThan(0, $operation->getGameUnit()->value);
    }

    public function testAllOperationsHaveUniqueSlugs(): void
    {
        $operations = array_map(
            static fn (array $data): Operation => new $data[0](),
            self::operationProvider()
        );

        $slugs = array_map(
            static fn (Operation $o): string => $o->getSlug(),
            $operations
        );

        self::assertCount(count($slugs), array_unique($slugs), 'Duplicate operation slugs found');
    }

    public function testSpyOperationSpecificProperties(): void
    {
        $operation = new SpyOperation();

        self::assertSame('Spy Technology', $operation->getName());
        self::assertSame(150, $operation->getCost());
        self::assertSame(0.1, $operation->getDifficulty());
        self::assertSame(3, $operation->getMaxDistance());
        self::assertSame(407, $operation->getGameUnit()->value);
        self::assertTrue($operation->isEnabled());
    }
}
