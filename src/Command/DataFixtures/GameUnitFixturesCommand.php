<?php

declare(strict_types=1);

namespace FrankProjects\UltimateWarfare\Command\DataFixtures;

use Doctrine\ORM\EntityManagerInterface;
use FrankProjects\UltimateWarfare\Entity\GameUnit;
use FrankProjects\UltimateWarfare\Entity\Enum\GameUnitCategory;
use FrankProjects\UltimateWarfare\Entity\Operation;
use FrankProjects\UltimateWarfare\Entity\WorldRegionUnit;
use FrankProjects\UltimateWarfare\Entity\FleetUnit;
use FrankProjects\UltimateWarfare\Entity\Construction;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'game:seed:game-units',
    description: 'Seeds or updates GameUnit data in the database'
)]
class GameUnitFixturesCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'commit',
            null,
            InputOption::VALUE_NONE,
            'Commit changes to the database (without this flag, it runs in dry-run mode)'
        );
        $this->addOption(
            'remove-obsolete',
            null,
            InputOption::VALUE_NONE,
            'Remove GameUnits that are no longer in the fixtures'
        );
    }


    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $commit = $input->getOption('commit');
        $removeObsolete = $input->getOption('remove-obsolete');

        if (!$commit) {
            $io->warning('Running in DRY-RUN mode. Use --commit to apply changes.');
        }

        $io->title('Processing GameUnit Fixtures');

        $gameUnitsData = $this->getGameUnitsData();
        $fixtureIds = array_column($gameUnitsData, 'id');

        $created = 0;
        $updated = 0;
        $unchanged = 0;
        $removed = 0;

        // Process fixture data
        foreach ($gameUnitsData as $data) {
            $gameUnit = $this->entityManager->getRepository(GameUnit::class)->find($data['id']);

            if ($gameUnit === null) {
                $io->section("Creating new GameUnit: {$data['name']} (ID: {$data['id']})");
                $this->displayNewUnit($io, $data);

                if ($commit) {
                    // Check if unit somehow exists (double-check to prevent duplicates)
                    $this->entityManager->clear();
                    $existingUnit = $this->entityManager->getRepository(GameUnit::class)->find($data['id']);

                    if ($existingUnit === null) {
                        $gameUnit = new GameUnit();

                        // Set the ID using the existing setter
                        $gameUnit->setId($data['id']);

                        // Apply all other data
                        $this->applyGameUnitData($gameUnit, $data);

                        // Get the class metadata and temporarily set ID generator to NONE
                        $metadata = $this->entityManager->getClassMetadata(GameUnit::class);
                        $originalGenerator = $metadata->idGenerator;
                        $metadata->setIdGeneratorType(\Doctrine\ORM\Mapping\ClassMetadata::GENERATOR_TYPE_NONE);

                        try {
                            $this->entityManager->persist($gameUnit);
                            $this->entityManager->flush();

                            // Restore the original generator
                            $metadata->setIdGenerator($originalGenerator);

                            $this->entityManager->clear();
                        } catch (\Exception $e) {
                            // Restore the original generator even on error
                            $metadata->setIdGenerator($originalGenerator);
                            $io->error("Failed to create GameUnit: " . $e->getMessage());
                            continue;
                        }
                    } else {
                        $io->warning("Unit with ID {$data['id']} already exists, skipping creation.");
                        continue;
                    }
                }
                $created++;
            } else {
                $differences = $this->detectDifferences($gameUnit, $data);

                if (empty($differences)) {
                    $unchanged++;
                    continue;
                }

                $io->section("Updating GameUnit: {$data['name']} (ID: {$data['id']})");
                $this->displayDifferences($io, $differences);

                if ($commit) {
                    $this->applyGameUnitData($gameUnit, $data);
                    $this->entityManager->persist($gameUnit);
                    $this->entityManager->flush();
                    $this->entityManager->clear(); // Clear after each update
                }
                $updated++;
            }
        }

        // Check for obsolete units
        if ($removeObsolete) {
            // Don't clear before this operation - we need managed entities
            /** @var GameUnit[] $allGameUnits */
            $allGameUnits = $this->entityManager->getRepository(GameUnit::class)->findAll();
            
            $obsoleteUnits = [];
            foreach ($allGameUnits as $gameUnit) {
                if (!in_array($gameUnit->getId(), $fixtureIds, true)) {
                    $obsoleteUnits[] = $gameUnit->getId(); // Store ID instead of entity
                }
            }
            
            // Now process removals with fresh entities
            foreach ($obsoleteUnits as $gameUnitId) {
                // Re-fetch the entity to ensure it's managed
                $gameUnit = $this->entityManager->getRepository(GameUnit::class)->find($gameUnitId);
                
                if ($gameUnit !== null) {
                    $io->section("Removing obsolete GameUnit: {$gameUnit->getName()} (ID: {$gameUnitId})");
                    
                    if ($commit) {
                        $this->removeGameUnitWithDependencies($io, $gameUnit);
                    } else {
                        $this->displayGameUnitDependencies($io, $gameUnit);
                    }
                    $removed++;
                }
            }
        }

        if ($commit) {
            $io->success('Changes committed to database!');
        } else {
            $io->note('No changes were made. Run with --commit to apply changes.');
        }

        $tableRows = [
            ['Created', $created],
            ['Updated', $updated],
            ['Unchanged', $unchanged],
        ];

        if ($removeObsolete) {
            $tableRows[] = ['Removed', $removed];
        }

        $tableRows[] = ['Total Fixtures', $created + $updated + $unchanged];

        $io->table(['Status', 'Count'], $tableRows);

        return Command::SUCCESS;
    }

    private function removeGameUnitWithDependencies(SymfonyStyle $io, GameUnit $gameUnit): void
    {
        $gameUnitId = $gameUnit->getId();

        // Remove WorldRegionUnits
        $worldRegionUnits = $this->entityManager->getRepository(WorldRegionUnit::class)
            ->findBy(['gameUnit' => $gameUnit]);

        if (!empty($worldRegionUnits)) {
            $io->text(sprintf('  Removing %d WorldRegionUnit(s)...', count($worldRegionUnits)));
            foreach ($worldRegionUnits as $worldRegionUnit) {
                $this->entityManager->remove($worldRegionUnit);
            }
        }

        // Remove FleetUnits
        $fleetUnits = $this->entityManager->getRepository(FleetUnit::class)
            ->findBy(['gameUnit' => $gameUnit]);

        if (!empty($fleetUnits)) {
            $io->text(sprintf('  Removing %d FleetUnit(s)...', count($fleetUnits)));
            foreach ($fleetUnits as $fleetUnit) {
                $this->entityManager->remove($fleetUnit);
            }
        }

        // Remove Constructions
        $constructions = $this->entityManager->getRepository(Construction::class)
            ->findBy(['gameUnit' => $gameUnit]);

        if (!empty($constructions)) {
            $io->text(sprintf('  Removing %d Construction(s)...', count($constructions)));
            foreach ($constructions as $construction) {
                $this->entityManager->remove($construction);
            }
        }

        // Unlink Operations
        $operations = $this->entityManager->getRepository(Operation::class)
            ->findBy(['gameUnit' => $gameUnit]);

        if (!empty($operations)) {
            $backupGameUnit = $this->entityManager->getRepository(GameUnit::class)
                ->find(401);

            $io->text(sprintf('  Removing %d Construction(s)...', count($constructions)));
            foreach ($operations as $operation) {
                $operation->setGameUnit($backupGameUnit);
                $this->entityManager->persist($operation);
            }
        }

        // Now remove the GameUnit itself
        $io->text('  Removing GameUnit...');
        $this->entityManager->remove($gameUnit);

        // Flush all removals together
        $this->entityManager->flush();

        // Clear after flush to free memory and detach entities
        $this->entityManager->clear();

        $io->success(sprintf('Successfully removed GameUnit ID %d and all dependencies', $gameUnitId));
    }

    private function displayGameUnitDependencies(SymfonyStyle $io, GameUnit $gameUnit): void
    {
        $worldRegionUnitsCount = $this->entityManager->getRepository(WorldRegionUnit::class)
            ->count(['gameUnit' => $gameUnit]);

        $fleetUnitsCount = $this->entityManager->getRepository(FleetUnit::class)
            ->count(['gameUnit' => $gameUnit]);

        $constructionsCount = $this->entityManager->getRepository(Construction::class)
            ->count(['gameUnit' => $gameUnit]);

        $dependencies = [];

        if ($worldRegionUnitsCount > 0) {
            $dependencies[] = sprintf('%d WorldRegionUnit(s)', $worldRegionUnitsCount);
        }

        if ($fleetUnitsCount > 0) {
            $dependencies[] = sprintf('%d FleetUnit(s)', $fleetUnitsCount);
        }

        if ($constructionsCount > 0) {
            $dependencies[] = sprintf('%d Construction(s)', $constructionsCount);
        }

        if (!empty($dependencies)) {
            $io->warning('This unit has dependencies that will be removed:');
            $io->listing($dependencies);
        } else {
            $io->text('  No dependencies found.');
        }
    }

    private function detectDifferences(GameUnit $gameUnit, array $data): array
    {
        $differences = [];

        // Check basic properties
        if ($gameUnit->getName() !== $data['name']) {
            $differences['name'] = ['old' => $gameUnit->getName(), 'new' => $data['name']];
        }
        if ($gameUnit->getNameMulti() !== $data['name_multi']) {
            $differences['name_multi'] = ['old' => $gameUnit->getNameMulti(), 'new' => $data['name_multi']];
        }
        if ($gameUnit->getRowName() !== $data['row_name']) {
            $differences['row_name'] = ['old' => $gameUnit->getRowName(), 'new' => $data['row_name']];
        }
        if ($gameUnit->getImage() !== $data['image']) {
            $differences['image'] = ['old' => $gameUnit->getImage(), 'new' => $data['image']];
        }
        if ($gameUnit->getNetWorth() !== $data['net_worth']) {
            $differences['net_worth'] = ['old' => $gameUnit->getNetWorth(), 'new' => $data['net_worth']];
        }
        if ($gameUnit->getTimestamp() !== $data['timestamp']) {
            $differences['timestamp'] = ['old' => $gameUnit->getTimestamp(), 'new' => $data['timestamp']];
        }
        if ($gameUnit->getDescription() !== $data['description']) {
            $differences['description'] = ['old' => $gameUnit->getDescription(), 'new' => $data['description']];
        }
        if ($gameUnit->getGameUnitCategory()->value !== $data['game_unit_category']) {
            $differences['game_unit_category'] = [
                'old' => $gameUnit->getGameUnitCategory()->value,
                'new' => $data['game_unit_category']
            ];
        }

        // Check battle stats
        $battleStats = $gameUnit->getBattleStats();
        $battleStatsReflection = new \ReflectionClass($battleStats);

        $healthValue = $battleStatsReflection->getProperty('health')->getValue($battleStats);
        if ($healthValue !== $data['battle_stats']['health']) {
            $differences['battle_stats.health'] = ['old' => $healthValue, 'new' => $data['battle_stats']['health']];
        }

        $armorValue = $battleStatsReflection->getProperty('armor')->getValue($battleStats);
        if ($armorValue !== $data['battle_stats']['armor']) {
            $differences['battle_stats.armor'] = ['old' => $armorValue, 'new' => $data['battle_stats']['armor']];
        }

        $travelSpeedValue = $battleStatsReflection->getProperty('travelSpeed')->getValue($battleStats);
        if ($travelSpeedValue !== $data['battle_stats']['travel_speed']) {
            $differences['battle_stats.travel_speed'] = ['old' => $travelSpeedValue, 'new' => $data['battle_stats']['travel_speed']];
        }

        // Check air battle stats
        $airStats = $battleStats->getAirBattleStats();
        $airReflection = new \ReflectionClass($airStats);

        $airAttack = $airReflection->getProperty('attack')->getValue($airStats);
        if ($airAttack !== $data['battle_stats']['air_attack']) {
            $differences['battle_stats.air_attack'] = ['old' => $airAttack, 'new' => $data['battle_stats']['air_attack']];
        }

        $airAttackSpeed = $airReflection->getProperty('attackSpeed')->getValue($airStats);
        if ($airAttackSpeed !== $data['battle_stats']['air_attack_speed']) {
            $differences['battle_stats.air_attack_speed'] = ['old' => $airAttackSpeed, 'new' => $data['battle_stats']['air_attack_speed']];
        }

        $airDefence = $airReflection->getProperty('defence')->getValue($airStats);
        if ($airDefence !== $data['battle_stats']['air_defence']) {
            $differences['battle_stats.air_defence'] = ['old' => $airDefence, 'new' => $data['battle_stats']['air_defence']];
        }

        $airDefenceSpeed = $airReflection->getProperty('defenceSpeed')->getValue($airStats);
        if ($airDefenceSpeed !== $data['battle_stats']['air_defence_speed']) {
            $differences['battle_stats.air_defence_speed'] = ['old' => $airDefenceSpeed, 'new' => $data['battle_stats']['air_defence_speed']];
        }

        // Check sea battle stats
        $seaStats = $battleStats->getSeaBattleStats();
        $seaReflection = new \ReflectionClass($seaStats);

        $seaAttack = $seaReflection->getProperty('attack')->getValue($seaStats);
        if ($seaAttack !== $data['battle_stats']['sea_attack']) {
            $differences['battle_stats.sea_attack'] = ['old' => $seaAttack, 'new' => $data['battle_stats']['sea_attack']];
        }

        $seaAttackSpeed = $seaReflection->getProperty('attackSpeed')->getValue($seaStats);
        if ($seaAttackSpeed !== $data['battle_stats']['sea_attack_speed']) {
            $differences['battle_stats.sea_attack_speed'] = ['old' => $seaAttackSpeed, 'new' => $data['battle_stats']['sea_attack_speed']];
        }

        $seaDefence = $seaReflection->getProperty('defence')->getValue($seaStats);
        if ($seaDefence !== $data['battle_stats']['sea_defence']) {
            $differences['battle_stats.sea_defence'] = ['old' => $seaDefence, 'new' => $data['battle_stats']['sea_defence']];
        }

        $seaDefenceSpeed = $seaReflection->getProperty('defenceSpeed')->getValue($seaStats);
        if ($seaDefenceSpeed !== $data['battle_stats']['sea_defence_speed']) {
            $differences['battle_stats.sea_defence_speed'] = ['old' => $seaDefenceSpeed, 'new' => $data['battle_stats']['sea_defence_speed']];
        }

        // Check ground battle stats
        $groundStats = $battleStats->getGroundBattleStats();
        $groundReflection = new \ReflectionClass($groundStats);

        $groundAttack = $groundReflection->getProperty('attack')->getValue($groundStats);
        if ($groundAttack !== $data['battle_stats']['ground_attack']) {
            $differences['battle_stats.ground_attack'] = ['old' => $groundAttack, 'new' => $data['battle_stats']['ground_attack']];
        }

        $groundAttackSpeed = $groundReflection->getProperty('attackSpeed')->getValue($groundStats);
        if ($groundAttackSpeed !== $data['battle_stats']['ground_attack_speed']) {
            $differences['battle_stats.ground_attack_speed'] = ['old' => $groundAttackSpeed, 'new' => $data['battle_stats']['ground_attack_speed']];
        }

        $groundDefence = $groundReflection->getProperty('defence')->getValue($groundStats);
        if ($groundDefence !== $data['battle_stats']['ground_defence']) {
            $differences['battle_stats.ground_defence'] = ['old' => $groundDefence, 'new' => $data['battle_stats']['ground_defence']];
        }

        $groundDefenceSpeed = $groundReflection->getProperty('defenceSpeed')->getValue($groundStats);
        if ($groundDefenceSpeed !== $data['battle_stats']['ground_defence_speed']) {
            $differences['battle_stats.ground_defence_speed'] = ['old' => $groundDefenceSpeed, 'new' => $data['battle_stats']['ground_defence_speed']];
        }

        // Check cost
        $cost = $gameUnit->getCost();
        if ($cost->getCash() !== $data['cost']['cash']) {
            $differences['cost.cash'] = ['old' => $cost->getCash(), 'new' => $data['cost']['cash']];
        }
        if ($cost->getSteel() !== $data['cost']['steel']) {
            $differences['cost.steel'] = ['old' => $cost->getSteel(), 'new' => $data['cost']['steel']];
        }
        if ($cost->getWood() !== $data['cost']['wood']) {
            $differences['cost.wood'] = ['old' => $cost->getWood(), 'new' => $data['cost']['wood']];
        }
        if ($cost->getFood() !== $data['cost']['food']) {
            $differences['cost.food'] = ['old' => $cost->getFood(), 'new' => $data['cost']['food']];
        }

        // Check income
        $income = $gameUnit->getIncome();
        if ($income->getCash() !== $data['income']['cash']) {
            $differences['income.cash'] = ['old' => $income->getCash(), 'new' => $data['income']['cash']];
        }
        if ($income->getSteel() !== $data['income']['steel']) {
            $differences['income.steel'] = ['old' => $income->getSteel(), 'new' => $data['income']['steel']];
        }
        if ($income->getWood() !== $data['income']['wood']) {
            $differences['income.wood'] = ['old' => $income->getWood(), 'new' => $data['income']['wood']];
        }
        if ($income->getFood() !== $data['income']['food']) {
            $differences['income.food'] = ['old' => $income->getFood(), 'new' => $data['income']['food']];
        }

        // Check upkeep
        $upkeep = $gameUnit->getUpkeep();
        if ($upkeep->getCash() !== $data['upkeep']['cash']) {
            $differences['upkeep.cash'] = ['old' => $upkeep->getCash(), 'new' => $data['upkeep']['cash']];
        }
        if ($upkeep->getSteel() !== $data['upkeep']['steel']) {
            $differences['upkeep.steel'] = ['old' => $upkeep->getSteel(), 'new' => $data['upkeep']['steel']];
        }
        if ($upkeep->getWood() !== $data['upkeep']['wood']) {
            $differences['upkeep.wood'] = ['old' => $upkeep->getWood(), 'new' => $data['upkeep']['wood']];
        }
        if ($upkeep->getFood() !== $data['upkeep']['food']) {
            $differences['upkeep.food'] = ['old' => $upkeep->getFood(), 'new' => $data['upkeep']['food']];
        }

        return $differences;
    }

    private function displayDifferences(SymfonyStyle $io, array $differences): void
    {
        $rows = [];
        foreach ($differences as $field => $values) {
            $rows[] = [
                $field,
                $this->formatValue($values['old']),
                $this->formatValue($values['new'])
            ];
        }

        $io->table(['Field', 'Old Value', 'New Value'], $rows);
    }

    private function displayNewUnit(SymfonyStyle $io, array $data): void
    {
        $io->listing([
            "Name: {$data['name']}",
            "Name Multi: {$data['name_multi']}",
            "Row Name: {$data['row_name']}",
            "Category: {$data['game_unit_category']}",
            "Net Worth: {$data['net_worth']}",
            "Timestamp: {$data['timestamp']}",
        ]);
    }

    private function formatValue(mixed $value): string
    {
        if (is_string($value) && $value === '') {
            return '(empty)';
        }
        return (string)$value;
    }

    private function applyGameUnitData(GameUnit $gameUnit, array $data): void
    {
        // Set basic properties
        $gameUnit->setName($data['name']);
        $gameUnit->setNameMulti($data['name_multi']);
        $gameUnit->setRowName($data['row_name']);
        $gameUnit->setImage($data['image']);
        $gameUnit->setNetWorth($data['net_worth']);
        $gameUnit->setTimestamp($data['timestamp']);
        $gameUnit->setDescription($data['description']);
        $gameUnit->setGameUnitCategory(GameUnitCategory::from($data['game_unit_category']));

        // Set battle stats
        $battleStats = $gameUnit->getBattleStats();
        $reflection = new \ReflectionClass($battleStats);

        $healthProp = $reflection->getProperty('health');
        $healthProp->setValue($battleStats, $data['battle_stats']['health']);

        $armorProp = $reflection->getProperty('armor');
        $armorProp->setValue($battleStats, $data['battle_stats']['armor']);

        $travelSpeedProp = $reflection->getProperty('travelSpeed');
        $travelSpeedProp->setValue($battleStats, $data['battle_stats']['travel_speed']);

        // Set air battle stats
        $airStats = $battleStats->getAirBattleStats();
        $airReflection = new \ReflectionClass($airStats);

        $airAttackProp = $airReflection->getProperty('attack');
        $airAttackProp->setValue($airStats, $data['battle_stats']['air_attack']);

        $airAttackSpeedProp = $airReflection->getProperty('attackSpeed');
        $airAttackSpeedProp->setValue($airStats, $data['battle_stats']['air_attack_speed']);

        $airDefenceProp = $airReflection->getProperty('defence');
        $airDefenceProp->setValue($airStats, $data['battle_stats']['air_defence']);

        $airDefenceSpeedProp = $airReflection->getProperty('defenceSpeed');
        $airDefenceSpeedProp->setValue($airStats, $data['battle_stats']['air_defence_speed']);

        // Set sea battle stats
        $seaStats = $battleStats->getSeaBattleStats();
        $seaReflection = new \ReflectionClass($seaStats);

        $seaAttackProp = $seaReflection->getProperty('attack');
        $seaAttackProp->setValue($seaStats, $data['battle_stats']['sea_attack']);

        $seaAttackSpeedProp = $seaReflection->getProperty('attackSpeed');
        $seaAttackSpeedProp->setValue($seaStats, $data['battle_stats']['sea_attack_speed']);

        $seaDefenceProp = $seaReflection->getProperty('defence');
        $seaDefenceProp->setValue($seaStats, $data['battle_stats']['sea_defence']);

        $seaDefenceSpeedProp = $seaReflection->getProperty('defenceSpeed');
        $seaDefenceSpeedProp->setValue($seaStats, $data['battle_stats']['sea_defence_speed']);

        // Set ground battle stats
        $groundStats = $battleStats->getGroundBattleStats();
        $groundReflection = new \ReflectionClass($groundStats);

        $groundAttackProp = $groundReflection->getProperty('attack');
        $groundAttackProp->setValue($groundStats, $data['battle_stats']['ground_attack']);

        $groundAttackSpeedProp = $groundReflection->getProperty('attackSpeed');
        $groundAttackSpeedProp->setValue($groundStats, $data['battle_stats']['ground_attack_speed']);

        $groundDefenceProp = $groundReflection->getProperty('defence');
        $groundDefenceProp->setValue($groundStats, $data['battle_stats']['ground_defence']);

        $groundDefenceSpeedProp = $groundReflection->getProperty('defenceSpeed');
        $groundDefenceSpeedProp->setValue($groundStats, $data['battle_stats']['ground_defence_speed']);

        // Set cost
        $cost = $gameUnit->getCost();
        $cost->setCash($data['cost']['cash']);
        $cost->setSteel($data['cost']['steel']);
        $cost->setWood($data['cost']['wood']);
        $cost->setFood($data['cost']['food']);

        // Set income
        $income = $gameUnit->getIncome();
        $income->setCash($data['income']['cash']);
        $income->setSteel($data['income']['steel']);
        $income->setWood($data['income']['wood']);
        $income->setFood($data['income']['food']);

        // Set upkeep
        $upkeep = $gameUnit->getUpkeep();
        $upkeep->setCash($data['upkeep']['cash']);
        $upkeep->setSteel($data['upkeep']['steel']);
        $upkeep->setWood($data['upkeep']['wood']);
        $upkeep->setFood($data['upkeep']['food']);
    }

    private function getGameUnitsData(): array
    {
        return [
            // Buildings (Category 1)
            [
                'id' => 1,
                'game_unit_category' => 1,
                'name' => 'Economic Center',
                'name_multi' => 'Economic Centers',
                'row_name' => 'economic',
                'image' => 'ec.gif',
                'net_worth' => 1,
                'timestamp' => 7200,
                'description' => 'An Economic Center makes 15 cash per hour.',
                'battle_stats' => [
                    'health' => 0, 'armor' => 0, 'travel_speed' => 0,
                    'air_attack' => 0, 'air_attack_speed' => 0, 'air_defence' => 0, 'air_defence_speed' => 0,
                    'sea_attack' => 0, 'sea_attack_speed' => 0, 'sea_defence' => 0, 'sea_defence_speed' => 0,
                    'ground_attack' => 0, 'ground_attack_speed' => 0, 'ground_defence' => 0, 'ground_defence_speed' => 0,
                ],
                'cost' => ['cash' => 100, 'steel' => 1, 'wood' => 5, 'food' => 0],
                'income' => ['cash' => 15, 'steel' => 0, 'wood' => 0, 'food' => 0],
                'upkeep' => ['cash' => 0, 'steel' => 0, 'wood' => 0, 'food' => 0],
            ],
            [
                'id' => 2,
                'game_unit_category' => 1,
                'name' => 'Farm',
                'name_multi' => 'Farms',
                'row_name' => 'farm',
                'image' => 'farm.gif',
                'net_worth' => 1,
                'timestamp' => 3600,
                'description' => 'A farm makes 50 food per hour.',
                'battle_stats' => [
                    'health' => 0, 'armor' => 0, 'travel_speed' => 0,
                    'air_attack' => 0, 'air_attack_speed' => 0, 'air_defence' => 0, 'air_defence_speed' => 0,
                    'sea_attack' => 0, 'sea_attack_speed' => 0, 'sea_defence' => 0, 'sea_defence_speed' => 0,
                    'ground_attack' => 0, 'ground_attack_speed' => 0, 'ground_defence' => 0, 'ground_defence_speed' => 0,
                ],
                'cost' => ['cash' => 300, 'steel' => 5, 'wood' => 15, 'food' => 0],
                'income' => ['cash' => 0, 'steel' => 0, 'wood' => 0, 'food' => 50],
                'upkeep' => ['cash' => 0, 'steel' => 0, 'wood' => 0, 'food' => 0],
            ],
            [
                'id' => 3,
                'game_unit_category' => 1,
                'name' => 'Mine',
                'name_multi' => 'Mines',
                'row_name' => 'mine',
                'image' => 'mine.gif',
                'net_worth' => 1,
                'timestamp' => 18000,
                'description' => 'A Mine makes 1 Steel per hour.',
                'battle_stats' => [
                    'health' => 0, 'armor' => 0, 'travel_speed' => 0,
                    'air_attack' => 0, 'air_attack_speed' => 0, 'air_defence' => 0, 'air_defence_speed' => 0,
                    'sea_attack' => 0, 'sea_attack_speed' => 0, 'sea_defence' => 0, 'sea_defence_speed' => 0,
                    'ground_attack' => 0, 'ground_attack_speed' => 0, 'ground_defence' => 0, 'ground_defence_speed' => 0,
                ],
                'cost' => ['cash' => 1500, 'steel' => 0, 'wood' => 50, 'food' => 0],
                'income' => ['cash' => 0, 'steel' => 1, 'wood' => 0, 'food' => 0],
                'upkeep' => ['cash' => 0, 'steel' => 0, 'wood' => 0, 'food' => 0],
            ],
            [
                'id' => 4,
                'game_unit_category' => 1,
                'name' => 'Woodcutter',
                'name_multi' => 'Woodcutters',
                'row_name' => 'woodcutter',
                'image' => 'wc.gif',
                'net_worth' => 1,
                'timestamp' => 3600,
                'description' => 'A woodcutter makes 1 wood per hour.',
                'battle_stats' => [
                    'health' => 0, 'armor' => 0, 'travel_speed' => 0,
                    'air_attack' => 0, 'air_attack_speed' => 0, 'air_defence' => 0, 'air_defence_speed' => 0,
                    'sea_attack' => 0, 'sea_attack_speed' => 0, 'sea_defence' => 0, 'sea_defence_speed' => 0,
                    'ground_attack' => 0, 'ground_attack_speed' => 0, 'ground_defence' => 0, 'ground_defence_speed' => 0,
                ],
                'cost' => ['cash' => 600, 'steel' => 2, 'wood' => 0, 'food' => 0],
                'income' => ['cash' => 0, 'steel' => 0, 'wood' => 1, 'food' => 0],
                'upkeep' => ['cash' => 0, 'steel' => 0, 'wood' => 0, 'food' => 0],
            ],
            [
                'id' => 5,
                'game_unit_category' => 1,
                'name' => 'House',
                'name_multi' => 'Houses',
                'row_name' => 'house',
                'image' => 'house.gif',
                'net_worth' => 1,
                'timestamp' => 900,
                'description' => 'This house can keep a population of 500.',
                'battle_stats' => [
                    'health' => 0, 'armor' => 0, 'travel_speed' => 0,
                    'air_attack' => 0, 'air_attack_speed' => 0, 'air_defence' => 0, 'air_defence_speed' => 0,
                    'sea_attack' => 0, 'sea_attack_speed' => 0, 'sea_defence' => 0, 'sea_defence_speed' => 0,
                    'ground_attack' => 0, 'ground_attack_speed' => 0, 'ground_defence' => 0, 'ground_defence_speed' => 0,
                ],
                'cost' => ['cash' => 150, 'steel' => 1, 'wood' => 5, 'food' => 50],
                'income' => ['cash' => 0, 'steel' => 0, 'wood' => 0, 'food' => 0],
                'upkeep' => ['cash' => 0, 'steel' => 0, 'wood' => 0, 'food' => 0],
            ],

            // Defense Buildings (Category 2)
            [
                'id' => 100,
                'game_unit_category' => 2,
                'name' => 'Sea Mine',
                'name_multi' => 'Sea Mines',
                'row_name' => 'sea_mine',
                'image' => 'seamine.gif',
                'net_worth' => 0,
                'timestamp' => 7200,
                'description' => 'Seamines have 0.01% chance of sinking an enemy ship.',
                'battle_stats' => [
                    'health' => 10, 'armor' => 1, 'travel_speed' => 0,
                    'air_attack' => 0, 'air_attack_speed' => 0, 'air_defence' => 0, 'air_defence_speed' => 0,
                    'sea_attack' => 0, 'sea_attack_speed' => 0, 'sea_defence' => 5, 'sea_defence_speed' => 500,
                    'ground_attack' => 0, 'ground_attack_speed' => 0, 'ground_defence' => 0, 'ground_defence_speed' => 0,
                ],
                'cost' => ['cash' => 3500, 'steel' => 1, 'wood' => 0, 'food' => 0],
                'income' => ['cash' => 0, 'steel' => 0, 'wood' => 0, 'food' => 0],
                'upkeep' => ['cash' => 0, 'steel' => 0, 'wood' => 0, 'food' => 0],
            ],
            [
                'id' => 101,
                'game_unit_category' => 2,
                'name' => 'Land Mine',
                'name_multi' => 'Land Mines',
                'row_name' => 'land_mine',
                'image' => 'landmine.gif',
                'net_worth' => 0,
                'timestamp' => 3600,
                'description' => 'Landmines have 0.5% chance of destroying a soldier or a tank.',
                'battle_stats' => [
                    'health' => 10, 'armor' => 1, 'travel_speed' => 0,
                    'air_attack' => 0, 'air_attack_speed' => 0, 'air_defence' => 0, 'air_defence_speed' => 0,
                    'sea_attack' => 0, 'sea_attack_speed' => 0, 'sea_defence' => 0, 'sea_defence_speed' => 0,
                    'ground_attack' => 0, 'ground_attack_speed' => 0, 'ground_defence' => 5, 'ground_defence_speed' => 500,
                ],
                'cost' => ['cash' => 5500, 'steel' => 1, 'wood' => 0, 'food' => 0],
                'income' => ['cash' => 0, 'steel' => 0, 'wood' => 0, 'food' => 0],
                'upkeep' => ['cash' => 0, 'steel' => 0, 'wood' => 0, 'food' => 0],
            ],
            [
                'id' => 102,
                'game_unit_category' => 2,
                'name' => 'Bunker',
                'name_multi' => 'Bunkers',
                'row_name' => 'bunker',
                'image' => 'bunker.gif',
                'net_worth' => 0,
                'timestamp' => 900,
                'description' => 'Every bunker can hold 100 soldiers and gives them 200% Defence bonus.',
                'battle_stats' => [
                    'health' => 4000, 'armor' => 35, 'travel_speed' => 0,
                    'air_attack' => 0, 'air_attack_speed' => 0, 'air_defence' => 0, 'air_defence_speed' => 0,
                    'sea_attack' => 0, 'sea_attack_speed' => 0, 'sea_defence' => 0, 'sea_defence_speed' => 0,
                    'ground_attack' => 0, 'ground_attack_speed' => 0, 'ground_defence' => 1, 'ground_defence_speed' => 0,
                ],
                'cost' => ['cash' => 8500, 'steel' => 150, 'wood' => 150, 'food' => 50],
                'income' => ['cash' => 0, 'steel' => 0, 'wood' => 0, 'food' => 0],
                'upkeep' => ['cash' => 0, 'steel' => 0, 'wood' => 0, 'food' => 0],
            ],
            [
                'id' => 103,
                'game_unit_category' => 2,
                'name' => 'Anti Air Missile',
                'name_multi' => 'Anti Air Missiles',
                'row_name' => 'anti_air_missile',
                'image' => 'anti_air_missile.gif',
                'net_worth' => 0,
                'timestamp' => 14400,
                'description' => 'Anti Air Missile have 1% chance of taking an enemy aircraft down.',
                'battle_stats' => [
                    'health' => 1500, 'armor' => 2, 'travel_speed' => 0,
                    'air_attack' => 0, 'air_attack_speed' => 0, 'air_defence' => 130, 'air_defence_speed' => 900,
                    'sea_attack' => 0, 'sea_attack_speed' => 0, 'sea_defence' => 80, 'sea_defence_speed' => 70,
                    'ground_attack' => 0, 'ground_attack_speed' => 0, 'ground_defence' => 70, 'ground_defence_speed' => 40,
                ],
                'cost' => ['cash' => 25000, 'steel' => 350, 'wood' => 150, 'food' => 0],
                'income' => ['cash' => 0, 'steel' => 0, 'wood' => 0, 'food' => 0],
                'upkeep' => ['cash' => 0, 'steel' => 0, 'wood' => 0, 'food' => 0],
            ],

            // Special Buildings (Category 3)
            [
                'id' => 200,
                'game_unit_category' => 3,
                'name' => 'Airport',
                'name_multi' => 'Airports',
                'row_name' => 'airport',
                'image' => 'airport.gif',
                'net_worth' => 20,
                'timestamp' => 14400,
                'description' => 'An airport can send 10 planes to your neighbour countries and help them defending when they are under attack.',
                'battle_stats' => [
                    'health' => 0, 'armor' => 0, 'travel_speed' => 0,
                    'air_attack' => 0, 'air_attack_speed' => 0, 'air_defence' => 0, 'air_defence_speed' => 0,
                    'sea_attack' => 0, 'sea_attack_speed' => 0, 'sea_defence' => 0, 'sea_defence_speed' => 0,
                    'ground_attack' => 0, 'ground_attack_speed' => 0, 'ground_defence' => 0, 'ground_defence_speed' => 0,
                ],
                'cost' => ['cash' => 15000, 'steel' => 75, 'wood' => 50, 'food' => 0],
                'income' => ['cash' => 0, 'steel' => 0, 'wood' => 0, 'food' => 0],
                'upkeep' => ['cash' => 0, 'steel' => 0, 'wood' => 0, 'food' => 0],
            ],
            [
                'id' => 201,
                'game_unit_category' => 3,
                'name' => 'Harbor',
                'name_multi' => 'Harbors',
                'row_name' => 'harbor',
                'image' => 'harbor.gif',
                'net_worth' => 15,
                'timestamp' => 28800,
                'description' => 'An harbor can send 1 ship to your neighbour countries and help them defending when they are under attack.',
                'battle_stats' => [
                    'health' => 0, 'armor' => 0, 'travel_speed' => 0,
                    'air_attack' => 0, 'air_attack_speed' => 0, 'air_defence' => 0, 'air_defence_speed' => 0,
                    'sea_attack' => 0, 'sea_attack_speed' => 0, 'sea_defence' => 0, 'sea_defence_speed' => 0,
                    'ground_attack' => 0, 'ground_attack_speed' => 0, 'ground_defence' => 0, 'ground_defence_speed' => 0,
                ],
                'cost' => ['cash' => 25000, 'steel' => 150, 'wood' => 600, 'food' => 0],
                'income' => ['cash' => 0, 'steel' => 0, 'wood' => 0, 'food' => 0],
                'upkeep' => ['cash' => 0, 'steel' => 0, 'wood' => 0, 'food' => 0],
            ],
            [
                'id' => 202,
                'game_unit_category' => 3,
                'name' => 'Train Station',
                'name_multi' => 'Train Stations',
                'row_name' => 'station',
                'image' => 'station.gif',
                'net_worth' => 10,
                'timestamp' => 10800,
                'description' => 'An Train station can send 25 soldiers and 1 tank to your neighbour countries and help them defending when they are under attack.',
                'battle_stats' => [
                    'health' => 0, 'armor' => 0, 'travel_speed' => 0,
                    'air_attack' => 0, 'air_attack_speed' => 0, 'air_defence' => 0, 'air_defence_speed' => 0,
                    'sea_attack' => 0, 'sea_attack_speed' => 0, 'sea_defence' => 0, 'sea_defence_speed' => 0,
                    'ground_attack' => 0, 'ground_attack_speed' => 0, 'ground_defence' => 0, 'ground_defence_speed' => 0,
                ],
                'cost' => ['cash' => 10000, 'steel' => 750, 'wood' => 500, 'food' => 0],
                'income' => ['cash' => 0, 'steel' => 0, 'wood' => 0, 'food' => 0],
                'upkeep' => ['cash' => 0, 'steel' => 0, 'wood' => 0, 'food' => 0],
            ],
            [
                'id' => 203,
                'game_unit_category' => 3,
                'name' => 'Barrack',
                'name_multi' => 'Baracks',
                'row_name' => 'barrack',
                'image' => 'barrack.gif',
                'net_worth' => 10,
                'timestamp' => 3600,
                'description' => 'Within a barrack you can train troops.',
                'battle_stats' => [
                    'health' => 0, 'armor' => 0, 'travel_speed' => 0,
                    'air_attack' => 0, 'air_attack_speed' => 0, 'air_defence' => 0, 'air_defence_speed' => 0,
                    'sea_attack' => 0, 'sea_attack_speed' => 0, 'sea_defence' => 0, 'sea_defence_speed' => 0,
                    'ground_attack' => 0, 'ground_attack_speed' => 0, 'ground_defence' => 0, 'ground_defence_speed' => 0,
                ],
                'cost' => ['cash' => 2000, 'steel' => 250, 'wood' => 100, 'food' => 0],
                'income' => ['cash' => 0, 'steel' => 0, 'wood' => 0, 'food' => 0],
                'upkeep' => ['cash' => 0, 'steel' => 0, 'wood' => 0, 'food' => 0],
            ],
            [
                'id' => 204,
                'game_unit_category' => 3,
                'name' => 'Factory',
                'name_multi' => 'Factories',
                'row_name' => 'factory',
                'image' => 'factory.gif',
                'net_worth' => 10,
                'timestamp' => 10800,
                'description' => 'Factory can build armor and mechanized vehicles.',
                'battle_stats' => [
                    'health' => 0, 'armor' => 0, 'travel_speed' => 0,
                    'air_attack' => 0, 'air_attack_speed' => 0, 'air_defence' => 0, 'air_defence_speed' => 0,
                    'sea_attack' => 0, 'sea_attack_speed' => 0, 'sea_defence' => 0, 'sea_defence_speed' => 0,
                    'ground_attack' => 0, 'ground_attack_speed' => 0, 'ground_defence' => 0, 'ground_defence_speed' => 0,
                ],
                'cost' => ['cash' => 10000, 'steel' => 750, 'wood' => 500, 'food' => 0],
                'income' => ['cash' => 0, 'steel' => 0, 'wood' => 0, 'food' => 0],
                'upkeep' => ['cash' => 0, 'steel' => 0, 'wood' => 0, 'food' => 0],
            ],
            [
                'id' => 205,
                'game_unit_category' => 3,
                'name' => 'Radar Station',
                'name_multi' => 'Radar Stations',
                'row_name' => 'radar_station',
                'image' => 'radar_station.gif',
                'net_worth' => 10,
                'timestamp' => 10800,
                'description' => 'Radar stations can detect enemy troop movements.',
                'battle_stats' => [
                    'health' => 0, 'armor' => 0, 'travel_speed' => 0,
                    'air_attack' => 0, 'air_attack_speed' => 0, 'air_defence' => 0, 'air_defence_speed' => 0,
                    'sea_attack' => 0, 'sea_attack_speed' => 0, 'sea_defence' => 0, 'sea_defence_speed' => 0,
                    'ground_attack' => 0, 'ground_attack_speed' => 0, 'ground_defence' => 0, 'ground_defence_speed' => 0,
                ],
                'cost' => ['cash' => 50000, 'steel' => 5000, 'wood' => 2500, 'food' => 0],
                'income' => ['cash' => 0, 'steel' => 0, 'wood' => 0, 'food' => 0],
                'upkeep' => ['cash' => 0, 'steel' => 0, 'wood' => 0, 'food' => 0],
            ],
            [
                'id' => 206,
                'game_unit_category' => 3,
                'name' => 'Missile Silo',
                'name_multi' => 'Missile Silos',
                'row_name' => 'missle_silo',
                'image' => 'misile_silo.gif',
                'net_worth' => 10,
                'timestamp' => 10800,
                'description' => 'Used to build and launch missiles',
                'battle_stats' => [
                    'health' => 0, 'armor' => 0, 'travel_speed' => 0,
                    'air_attack' => 0, 'air_attack_speed' => 0, 'air_defence' => 0, 'air_defence_speed' => 0,
                    'sea_attack' => 0, 'sea_attack_speed' => 0, 'sea_defence' => 0, 'sea_defence_speed' => 0,
                    'ground_attack' => 0, 'ground_attack_speed' => 0, 'ground_defence' => 0, 'ground_defence_speed' => 0,
                ],
                'cost' => ['cash' => 50000, 'steel' => 5000, 'wood' => 2500, 'food' => 0],
                'income' => ['cash' => 0, 'steel' => 0, 'wood' => 0, 'food' => 0],
                'upkeep' => ['cash' => 0, 'steel' => 0, 'wood' => 0, 'food' => 0],
            ],





            // Special Units (Category 5)
            [
                'id' => 400,
                'game_unit_category' => 5,
                'name' => 'Guard',
                'name_multi' => 'Guards',
                'row_name' => 'guard',
                'image' => 'guard.gif',
                'net_worth' => 1,
                'timestamp' => 1800,
                'description' => 'Guards will protect your countries against enemy Operations',
                'battle_stats' => [
                    'health' => 0, 'armor' => 0, 'travel_speed' => 0,
                    'air_attack' => 0, 'air_attack_speed' => 0, 'air_defence' => 0, 'air_defence_speed' => 0,
                    'sea_attack' => 0, 'sea_attack_speed' => 0, 'sea_defence' => 0, 'sea_defence_speed' => 0,
                    'ground_attack' => 0, 'ground_attack_speed' => 0, 'ground_defence' => 0, 'ground_defence_speed' => 0,
                ],
                'cost' => ['cash' => 500, 'steel' => 2, 'wood' => 1, 'food' => 0],
                'income' => ['cash' => 0, 'steel' => 0, 'wood' => 0, 'food' => 0],
                'upkeep' => ['cash' => 0, 'steel' => 0, 'wood' => 0, 'food' => 0],
            ],
            [
                'id' => 401,
                'game_unit_category' => 5,
                'name' => 'Saboteur',
                'name_multi' => 'Saboteurs',
                'row_name' => 'saboteur',
                'image' => 'sp_op.gif',
                'net_worth' => 1,
                'timestamp' => 900,
                'description' => 'This are your offensive special operation units. They can be used to sabotage buildings, destroy cash and much more.',
                'battle_stats' => [
                    'health' => 0, 'armor' => 0, 'travel_speed' => 0,
                    'air_attack' => 0, 'air_attack_speed' => 0, 'air_defence' => 0, 'air_defence_speed' => 0,
                    'sea_attack' => 0, 'sea_attack_speed' => 0, 'sea_defence' => 0, 'sea_defence_speed' => 0,
                    'ground_attack' => 0, 'ground_attack_speed' => 0, 'ground_defence' => 0, 'ground_defence_speed' => 0,
                ],
                'cost' => ['cash' => 1500, 'steel' => 5, 'wood' => 1, 'food' => 0],
                'income' => ['cash' => 0, 'steel' => 0, 'wood' => 0, 'food' => 0],
                'upkeep' => ['cash' => 0, 'steel' => 0, 'wood' => 0, 'food' => 0],
            ],
            [
                'id' => 407,
                'game_unit_category' => 5,
                'name' => 'Spy',
                'name_multi' => 'Spies',
                'row_name' => 'spy',
                'image' => 'spy.gif',
                'net_worth' => 1,
                'timestamp' => 1800,
                'description' => 'Spies can be used to spy on enemy countries. The more spies you send, the more information you gain',
                'battle_stats' => [
                    'health' => 0, 'armor' => 0, 'travel_speed' => 0,
                    'air_attack' => 0, 'air_attack_speed' => 0, 'air_defence' => 0, 'air_defence_speed' => 0,
                    'sea_attack' => 0, 'sea_attack_speed' => 0, 'sea_defence' => 0, 'sea_defence_speed' => 0,
                    'ground_attack' => 0, 'ground_attack_speed' => 0, 'ground_defence' => 0, 'ground_defence_speed' => 0,
                ],
                'cost' => ['cash' => 2500, 'steel' => 5, 'wood' => 1, 'food' => 0],
                'income' => ['cash' => 0, 'steel' => 0, 'wood' => 0, 'food' => 0],
                'upkeep' => ['cash' => 0, 'steel' => 0, 'wood' => 0, 'food' => 0],
            ],



            // Troops (Category 6)
            [
                'id' => 600,
                'game_unit_category' => 6,
                'name' => 'Soldier',
                'name_multi' => 'Soldiers',
                'row_name' => 'soldier',
                'image' => 'soldier.gif',
                'net_worth' => 1,
                'timestamp' => 1800,
                'description' => '',
                'battle_stats' => [
                    'health' => 50, 'armor' => 1, 'travel_speed' => 100,
                    'air_attack' => 0, 'air_attack_speed' => 0, 'air_defence' => 0, 'air_defence_speed' => 0,
                    'sea_attack' => 0, 'sea_attack_speed' => 0, 'sea_defence' => 0, 'sea_defence_speed' => 0,
                    'ground_attack' => 10, 'ground_attack_speed' => 100, 'ground_defence' => 12, 'ground_defence_speed' => 110,
                ],
                'cost' => ['cash' => 500, 'steel' => 5, 'wood' => 1, 'food' => 50],
                'income' => ['cash' => 0, 'steel' => 0, 'wood' => 0, 'food' => 0],
                'upkeep' => ['cash' => 1, 'steel' => 0, 'wood' => 0, 'food' => 1],
            ],
            [
                'id' => 601,
                'game_unit_category' => 6,
                'name' => 'Sniper',
                'name_multi' => 'Snipers',
                'row_name' => 'sniper',
                'image' => 'sniper.gif',
                'net_worth' => 1,
                'timestamp' => 7200,
                'description' => 'Snipers can be sent to enemy countries to take down enemy soldiers',
                'battle_stats' => [
                    'health' => 0, 'armor' => 0, 'travel_speed' => 0,
                    'air_attack' => 0, 'air_attack_speed' => 0, 'air_defence' => 0, 'air_defence_speed' => 0,
                    'sea_attack' => 0, 'sea_attack_speed' => 0, 'sea_defence' => 0, 'sea_defence_speed' => 0,
                    'ground_attack' => 0, 'ground_attack_speed' => 0, 'ground_defence' => 0, 'ground_defence_speed' => 0,
                ],
                'cost' => ['cash' => 2500, 'steel' => 5, 'wood' => 1, 'food' => 0],
                'income' => ['cash' => 0, 'steel' => 0, 'wood' => 0, 'food' => 0],
                'upkeep' => ['cash' => 0, 'steel' => 0, 'wood' => 0, 'food' => 0],
            ],
            [
                'id' => 602,
                'game_unit_category' => 6,
                'name' => 'Tank',
                'name_multi' => 'Tanks',
                'row_name' => 'tank',
                'image' => 'tank.gif',
                'net_worth' => 10,
                'timestamp' => 10800,
                'description' => '',
                'battle_stats' => [
                    'health' => 1500, 'armor' => 10, 'travel_speed' => 200,
                    'air_attack' => 0, 'air_attack_speed' => 0, 'air_defence' => 0, 'air_defence_speed' => 0,
                    'sea_attack' => 0, 'sea_attack_speed' => 0, 'sea_defence' => 0, 'sea_defence_speed' => 0,
                    'ground_attack' => 50, 'ground_attack_speed' => 130, 'ground_defence' => 55, 'ground_defence_speed' => 135,
                ],
                'cost' => ['cash' => 5000, 'steel' => 55, 'wood' => 20, 'food' => 1000],
                'income' => ['cash' => 0, 'steel' => 0, 'wood' => 0, 'food' => 0],
                'upkeep' => ['cash' => 15, 'steel' => 0, 'wood' => 0, 'food' => 5],
            ],
            [
                'id' => 603,
                'game_unit_category' => 6,
                'name' => 'Artillery',
                'name_multi' => 'Artillery',
                'row_name' => 'artillery',
                'image' => 'tank.gif',
                'net_worth' => 10,
                'timestamp' => 10800,
                'description' => '',
                'battle_stats' => [
                    'health' => 1500, 'armor' => 10, 'travel_speed' => 200,
                    'air_attack' => 0, 'air_attack_speed' => 0, 'air_defence' => 0, 'air_defence_speed' => 0,
                    'sea_attack' => 0, 'sea_attack_speed' => 0, 'sea_defence' => 0, 'sea_defence_speed' => 0,
                    'ground_attack' => 50, 'ground_attack_speed' => 130, 'ground_defence' => 55, 'ground_defence_speed' => 135,
                ],
                'cost' => ['cash' => 5000, 'steel' => 55, 'wood' => 20, 'food' => 1000],
                'income' => ['cash' => 0, 'steel' => 0, 'wood' => 0, 'food' => 0],
                'upkeep' => ['cash' => 15, 'steel' => 0, 'wood' => 0, 'food' => 5],
            ],
            [
                'id' => 604,
                'game_unit_category' => 6,
                'name' => 'Mine Sweeper',
                'name_multi' => 'Mine Sweepers',
                'row_name' => 'minesweeper',
                'image' => 'minesweeper.gif',
                'net_worth' => 1,
                'timestamp' => 3600,
                'description' => 'This Soldier is trained to find and disarm mines.',
                'battle_stats' => [
                    'health' => 150, 'armor' => 1, 'travel_speed' => 200,
                    'air_attack' => 0, 'air_attack_speed' => 0, 'air_defence' => 0, 'air_defence_speed' => 0,
                    'sea_attack' => 0, 'sea_attack_speed' => 0, 'sea_defence' => 0, 'sea_defence_speed' => 0,
                    'ground_attack' => 15, 'ground_attack_speed' => 100, 'ground_defence' => 10, 'ground_defence_speed' => 100,
                ],
                'cost' => ['cash' => 1500, 'steel' => 15, 'wood' => 2, 'food' => 75],
                'income' => ['cash' => 0, 'steel' => 0, 'wood' => 0, 'food' => 0],
                'upkeep' => ['cash' => 1, 'steel' => 0, 'wood' => 0, 'food' => 1],
            ],




            // Naval Units (Category 7)
            [
                'id' => 700,
                'game_unit_category' => 7,
                'name' => 'Patrol boat',
                'name_multi' => 'Patrol boats',
                'row_name' => 'patrol_boat',
                'image' => 'ship.gif',
                'net_worth' => 100,
                'timestamp' => 36000,
                'description' => '',
                'battle_stats' => [
                    'health' => 2500, 'armor' => 25, 'travel_speed' => 100,
                    'air_attack' => 0, 'air_attack_speed' => 0, 'air_defence' => 100, 'air_defence_speed' => 50,
                    'sea_attack' => 200, 'sea_attack_speed' => 150, 'sea_defence' => 230, 'sea_defence_speed' => 160,
                    'ground_attack' => 160, 'ground_attack_speed' => 40, 'ground_defence' => 0, 'ground_defence_speed' => 0,
                ],
                'cost' => ['cash' => 100000, 'steel' => 300, 'wood' => 250, 'food' => 20000],
                'income' => ['cash' => 0, 'steel' => 0, 'wood' => 0, 'food' => 0],
                'upkeep' => ['cash' => 500, 'steel' => 0, 'wood' => 0, 'food' => 200],
            ],
            [
                'id' => 701,
                'game_unit_category' => 7,
                'name' => 'Destroyer',
                'name_multi' => 'Destroyers',
                'row_name' => 'destroyer',
                'image' => 'ship.gif',
                'net_worth' => 100,
                'timestamp' => 36000,
                'description' => '',
                'battle_stats' => [
                    'health' => 2500, 'armor' => 25, 'travel_speed' => 100,
                    'air_attack' => 0, 'air_attack_speed' => 0, 'air_defence' => 100, 'air_defence_speed' => 50,
                    'sea_attack' => 200, 'sea_attack_speed' => 150, 'sea_defence' => 230, 'sea_defence_speed' => 160,
                    'ground_attack' => 160, 'ground_attack_speed' => 40, 'ground_defence' => 0, 'ground_defence_speed' => 0,
                ],
                'cost' => ['cash' => 100000, 'steel' => 300, 'wood' => 250, 'food' => 20000],
                'income' => ['cash' => 0, 'steel' => 0, 'wood' => 0, 'food' => 0],
                'upkeep' => ['cash' => 500, 'steel' => 0, 'wood' => 0, 'food' => 200],
            ],
            [
                'id' => 702,
                'game_unit_category' => 7,
                'name' => 'Cruiser',
                'name_multi' => 'Cruisers',
                'row_name' => 'cruiser',
                'image' => 'ship.gif',
                'net_worth' => 100,
                'timestamp' => 36000,
                'description' => '',
                'battle_stats' => [
                    'health' => 2500, 'armor' => 25, 'travel_speed' => 100,
                    'air_attack' => 0, 'air_attack_speed' => 0, 'air_defence' => 100, 'air_defence_speed' => 50,
                    'sea_attack' => 200, 'sea_attack_speed' => 150, 'sea_defence' => 230, 'sea_defence_speed' => 160,
                    'ground_attack' => 160, 'ground_attack_speed' => 40, 'ground_defence' => 0, 'ground_defence_speed' => 0,
                ],
                'cost' => ['cash' => 100000, 'steel' => 300, 'wood' => 250, 'food' => 20000],
                'income' => ['cash' => 0, 'steel' => 0, 'wood' => 0, 'food' => 0],
                'upkeep' => ['cash' => 500, 'steel' => 0, 'wood' => 0, 'food' => 200],
            ],
            [
                'id' => 703,
                'game_unit_category' => 7,
                'name' => 'Submarine',
                'name_multi' => 'Submarines',
                'row_name' => 'submarine',
                'image' => 'submarine.gif',
                'net_worth' => 100,
                'timestamp' => 36000,
                'description' => '',
                'battle_stats' => [
                    'health' => 2500, 'armor' => 25, 'travel_speed' => 100,
                    'air_attack' => 0, 'air_attack_speed' => 0, 'air_defence' => 100, 'air_defence_speed' => 50,
                    'sea_attack' => 200, 'sea_attack_speed' => 150, 'sea_defence' => 230, 'sea_defence_speed' => 160,
                    'ground_attack' => 160, 'ground_attack_speed' => 40, 'ground_defence' => 0, 'ground_defence_speed' => 0,
                ],
                'cost' => ['cash' => 100000, 'steel' => 300, 'wood' => 250, 'food' => 20000],
                'income' => ['cash' => 0, 'steel' => 0, 'wood' => 0, 'food' => 0],
                'upkeep' => ['cash' => 500, 'steel' => 0, 'wood' => 0, 'food' => 200],
            ],
            [
                'id' => 704,
                'game_unit_category' => 7,
                'name' => 'Mine Countermeasures Ship',
                'name_multi' => 'Mine Countermeasures Ships',
                'row_name' => 'mine_countermeasures_ship',
                'image' => 'ship.gif',
                'net_worth' => 100,
                'timestamp' => 36000,
                'description' => '',
                'battle_stats' => [
                    'health' => 2500, 'armor' => 25, 'travel_speed' => 100,
                    'air_attack' => 0, 'air_attack_speed' => 0, 'air_defence' => 100, 'air_defence_speed' => 50,
                    'sea_attack' => 200, 'sea_attack_speed' => 150, 'sea_defence' => 230, 'sea_defence_speed' => 160,
                    'ground_attack' => 160, 'ground_attack_speed' => 40, 'ground_defence' => 0, 'ground_defence_speed' => 0,
                ],
                'cost' => ['cash' => 100000, 'steel' => 300, 'wood' => 250, 'food' => 20000],
                'income' => ['cash' => 0, 'steel' => 0, 'wood' => 0, 'food' => 0],
                'upkeep' => ['cash' => 500, 'steel' => 0, 'wood' => 0, 'food' => 200],
            ],



            // Air Units (Category 8)
            [
                'id' => 800,
                'game_unit_category' => 8,
                'name' => 'Fighter',
                'name_multi' => 'Fighters',
                'row_name' => 'fighter',
                'image' => 'plane.gif',
                'net_worth' => 10,
                'timestamp' => 18000,
                'description' => '',
                'battle_stats' => [
                    'health' => 1000, 'armor' => 2, 'travel_speed' => 500,
                    'air_attack' => 75, 'air_attack_speed' => 300, 'air_defence' => 80, 'air_defence_speed' => 310,
                    'sea_attack' => 0, 'sea_attack_speed' => 0, 'sea_defence' => 0, 'sea_defence_speed' => 0,
                    'ground_attack' => 20, 'ground_attack_speed' => 100, 'ground_defence' => 0, 'ground_defence_speed' => 0,
                ],
                'cost' => ['cash' => 25000, 'steel' => 50, 'wood' => 100, 'food' => 1000],
                'income' => ['cash' => 0, 'steel' => 0, 'wood' => 0, 'food' => 0],
                'upkeep' => ['cash' => 75, 'steel' => 0, 'wood' => 0, 'food' => 10],
            ],
            [
                'id' => 801,
                'game_unit_category' => 8,
                'name' => 'Bomber',
                'name_multi' => 'Bombers',
                'row_name' => 'bomber',
                'image' => 'stealth_bomber.gif',
                'net_worth' => 10,
                'timestamp' => 18000,
                'description' => '',
                'battle_stats' => [
                    'health' => 1000, 'armor' => 2, 'travel_speed' => 500,
                    'air_attack' => 75, 'air_attack_speed' => 300, 'air_defence' => 80, 'air_defence_speed' => 310,
                    'sea_attack' => 0, 'sea_attack_speed' => 0, 'sea_defence' => 0, 'sea_defence_speed' => 0,
                    'ground_attack' => 20, 'ground_attack_speed' => 100, 'ground_defence' => 0, 'ground_defence_speed' => 0,
                ],
                'cost' => ['cash' => 25000, 'steel' => 50, 'wood' => 100, 'food' => 1000],
                'income' => ['cash' => 0, 'steel' => 0, 'wood' => 0, 'food' => 0],
                'upkeep' => ['cash' => 75, 'steel' => 0, 'wood' => 0, 'food' => 10],
            ],
            [
                'id' => 802,
                'game_unit_category' => 8,
                'name' => 'Strategic Bomber',
                'name_multi' => 'Strategic Bombers',
                'row_name' => 'strategic_bomber',
                'image' => 'stealth_bomber.gif',
                'net_worth' => 10,
                'timestamp' => 18000,
                'description' => '',
                'battle_stats' => [
                    'health' => 1000, 'armor' => 2, 'travel_speed' => 500,
                    'air_attack' => 75, 'air_attack_speed' => 300, 'air_defence' => 80, 'air_defence_speed' => 310,
                    'sea_attack' => 0, 'sea_attack_speed' => 0, 'sea_defence' => 0, 'sea_defence_speed' => 0,
                    'ground_attack' => 20, 'ground_attack_speed' => 100, 'ground_defence' => 0, 'ground_defence_speed' => 0,
                ],
                'cost' => ['cash' => 25000, 'steel' => 50, 'wood' => 100, 'food' => 1000],
                'income' => ['cash' => 0, 'steel' => 0, 'wood' => 0, 'food' => 0],
                'upkeep' => ['cash' => 75, 'steel' => 0, 'wood' => 0, 'food' => 10],
            ],



            // Rockets / Missiles (Category 9)
            [
                'id' => 900,
                'game_unit_category' => 9,
                'name' => 'Rocket',
                'name_multi' => 'Rockets',
                'row_name' => 'rocket',
                'image' => 'rocket.gif',
                'net_worth' => 1,
                'timestamp' => 900,
                'description' => 'Rockets can be used to bomb an enemy country',
                'battle_stats' => [
                    'health' => 0, 'armor' => 0, 'travel_speed' => 0,
                    'air_attack' => 0, 'air_attack_speed' => 0, 'air_defence' => 0, 'air_defence_speed' => 0,
                    'sea_attack' => 0, 'sea_attack_speed' => 0, 'sea_defence' => 0, 'sea_defence_speed' => 0,
                    'ground_attack' => 0, 'ground_attack_speed' => 0, 'ground_defence' => 0, 'ground_defence_speed' => 0,
                ],
                'cost' => ['cash' => 100, 'steel' => 5, 'wood' => 1, 'food' => 0],
                'income' => ['cash' => 0, 'steel' => 0, 'wood' => 0, 'food' => 0],
                'upkeep' => ['cash' => 0, 'steel' => 0, 'wood' => 0, 'food' => 0],
            ],
            [
                'id' => 901,
                'game_unit_category' => 9,
                'name' => 'Chemical rocket',
                'name_multi' => 'Chemical rockets',
                'row_name' => 'chem_rocket',
                'image' => 'chem_rocket.gif',
                'net_worth' => 1,
                'timestamp' => 3600,
                'description' => 'Chemical rockets can be used to posion enemy population.',
                'battle_stats' => [
                    'health' => 0, 'armor' => 0, 'travel_speed' => 0,
                    'air_attack' => 0, 'air_attack_speed' => 0, 'air_defence' => 0, 'air_defence_speed' => 0,
                    'sea_attack' => 0, 'sea_attack_speed' => 0, 'sea_defence' => 0, 'sea_defence_speed' => 0,
                    'ground_attack' => 0, 'ground_attack_speed' => 0, 'ground_defence' => 0, 'ground_defence_speed' => 0,
                ],
                'cost' => ['cash' => 250, 'steel' => 5, 'wood' => 1, 'food' => 0],
                'income' => ['cash' => 0, 'steel' => 0, 'wood' => 0, 'food' => 0],
                'upkeep' => ['cash' => 0, 'steel' => 0, 'wood' => 0, 'food' => 0],
            ],
            [
                'id' => 902,
                'game_unit_category' => 9,
                'name' => 'Nuclear Missile',
                'name_multi' => 'Nuclear Missiles',
                'row_name' => 'nuclear_missile',
                'image' => 'nuclear.gif',
                'net_worth' => 10,
                'timestamp' => 36000,
                'description' => '',
                'battle_stats' => [
                    'health' => 0, 'armor' => 0, 'travel_speed' => 0,
                    'air_attack' => 0, 'air_attack_speed' => 0, 'air_defence' => 0, 'air_defence_speed' => 0,
                    'sea_attack' => 0, 'sea_attack_speed' => 0, 'sea_defence' => 0, 'sea_defence_speed' => 0,
                    'ground_attack' => 0, 'ground_attack_speed' => 0, 'ground_defence' => 0, 'ground_defence_speed' => 0,
                ],
                'cost' => ['cash' => 1000000, 'steel' => 0, 'wood' => 0, 'food' => 0],
                'income' => ['cash' => 0, 'steel' => 0, 'wood' => 0, 'food' => 0],
                'upkeep' => ['cash' => 0, 'steel' => 0, 'wood' => 0, 'food' => 0],
            ],
        ];
    }
}
