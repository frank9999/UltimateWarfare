<?php

declare(strict_types=1);

namespace FrankProjects\UltimateWarfare\Repository;

use FrankProjects\UltimateWarfare\Entity\Enum\GameUnitCategory;
use FrankProjects\UltimateWarfare\Entity\Enum\GameUnitEnum;
use FrankProjects\UltimateWarfare\Entity\GameUnit;
use FrankProjects\UltimateWarfare\Entity\WorldRegion;
use FrankProjects\UltimateWarfare\Entity\GameUnit\AntiAircraftGun;
use FrankProjects\UltimateWarfare\Entity\GameUnit\Airfield;
use FrankProjects\UltimateWarfare\Entity\GameUnit\Artillery;
use FrankProjects\UltimateWarfare\Entity\GameUnit\Barrack;
use FrankProjects\UltimateWarfare\Entity\GameUnit\Bomber;
use FrankProjects\UltimateWarfare\Entity\GameUnit\Bunker;
use FrankProjects\UltimateWarfare\Entity\GameUnit\ChemicalRocket;
use FrankProjects\UltimateWarfare\Entity\GameUnit\Cruiser;
use FrankProjects\UltimateWarfare\Entity\GameUnit\Destroyer;
use FrankProjects\UltimateWarfare\Entity\GameUnit\EconomicCenter;
use FrankProjects\UltimateWarfare\Entity\GameUnit\Factory;
use FrankProjects\UltimateWarfare\Entity\GameUnit\Farm;
use FrankProjects\UltimateWarfare\Entity\GameUnit\Fighter;
use FrankProjects\UltimateWarfare\Entity\GameUnit\Guard;
use FrankProjects\UltimateWarfare\Entity\GameUnit\Harbor;
use FrankProjects\UltimateWarfare\Entity\GameUnit\House;
use FrankProjects\UltimateWarfare\Entity\GameUnit\LandMine;
use FrankProjects\UltimateWarfare\Entity\GameUnit\IronMine;
use FrankProjects\UltimateWarfare\Entity\GameUnit\MineCountermeasuresShip;
use FrankProjects\UltimateWarfare\Entity\GameUnit\MineSweeper;
use FrankProjects\UltimateWarfare\Entity\GameUnit\MissileFactory;
use FrankProjects\UltimateWarfare\Entity\GameUnit\NuclearMissile;
use FrankProjects\UltimateWarfare\Entity\GameUnit\PatrolBoat;
use FrankProjects\UltimateWarfare\Entity\GameUnit\RadarStation;
use FrankProjects\UltimateWarfare\Entity\GameUnit\Rocket;
use FrankProjects\UltimateWarfare\Entity\GameUnit\Saboteur;
use FrankProjects\UltimateWarfare\Entity\GameUnit\SeaMine;
use FrankProjects\UltimateWarfare\Entity\GameUnit\Sniper;
use FrankProjects\UltimateWarfare\Entity\GameUnit\Soldier;
use FrankProjects\UltimateWarfare\Entity\GameUnit\Spy;
use FrankProjects\UltimateWarfare\Entity\GameUnit\StrategicBomber;
use FrankProjects\UltimateWarfare\Entity\GameUnit\Submarine;
use FrankProjects\UltimateWarfare\Entity\GameUnit\Tank;
use FrankProjects\UltimateWarfare\Entity\GameUnit\TrainStation;
use FrankProjects\UltimateWarfare\Entity\GameUnit\Woodcutter;

final class GameUnitRegistry
{
    /** @var array<int, GameUnit> */
    private array $gameUnits;

    public function __construct()
    {
        $gameUnitList = [
            new EconomicCenter(),
            new Farm(),
            new IronMine(),
            new Woodcutter(),
            new House(),
            new SeaMine(),
            new LandMine(),
            new Bunker(),
            new AntiAircraftGun(),
            new Airfield(),
            new Harbor(),
            new TrainStation(),
            new Barrack(),
            new Factory(),
            new RadarStation(),
            new MissileFactory(),
            new Guard(),
            new Saboteur(),
            new Spy(),
            new Soldier(),
            new Sniper(),
            new Tank(),
            new Artillery(),
            new MineSweeper(),
            new PatrolBoat(),
            new Destroyer(),
            new Cruiser(),
            new Submarine(),
            new MineCountermeasuresShip(),
            new Fighter(),
            new Bomber(),
            new StrategicBomber(),
            new Rocket(),
            new ChemicalRocket(),
            new NuclearMissile(),
        ];

        $this->gameUnits = [];
        foreach ($gameUnitList as $gameUnit) {
            $this->gameUnits[$gameUnit->getGameUnitEnum()->value] = $gameUnit;
        }
    }

    public function find(GameUnitEnum $gameUnitEnum): GameUnit
    {
        return $this->gameUnits[$gameUnitEnum->value]
            ?? throw new \RuntimeException("GameUnit not found for enum: {$gameUnitEnum->name}");
    }

    /**
     * @return GameUnit[]
     */
    public function findAll(): array
    {
        return array_values($this->gameUnits);
    }

    /**
     * @return GameUnit[]
     */
    public function findByCategory(GameUnitCategory $category): array
    {
        return array_values(
            array_filter(
                $this->gameUnits,
                static fn (GameUnit $gameUnit): bool => $gameUnit->getGameUnitCategory() === $category
            )
        );
    }

    /**
     * @param GameUnitCategory[] $categories
     * @return GameUnit[]
     */
    public function findByCategories(array $categories): array
    {
        return array_values(
            array_filter(
                $this->gameUnits,
                static fn (GameUnit $gameUnit): bool => in_array(
                    $gameUnit->getGameUnitCategory(),
                    $categories,
                    true
                )
            )
        );
    }

    /**
     * @return GameUnitEnum[]
     */
    public function getIdsByCategory(GameUnitCategory $category): array
    {
        return array_map(
            static fn (GameUnit $gameUnit): GameUnitEnum => $gameUnit->getGameUnitEnum(),
            $this->findByCategory($category)
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function getRegionUnitSummary(WorldRegion $region): array
    {
        $summary = [
            'buildings' => 0,
            'defences' => 0,
            'special' => 0,
            'specialUnits' => 0,
            'troops' => 0,
            'navalUnits' => 0,
            'airUnits' => 0,
            'missiles' => 0,
            'details' => [
                'buildings' => [],
                'defences' => [],
                'special' => [],
                'specialUnits' => [],
                'troops' => [],
                'navalUnits' => [],
                'airUnits' => [],
                'missiles' => [],
            ],
        ];

        foreach ($region->getWorldRegionUnits() as $worldRegionUnit) {
            $gameUnit = $this->find($worldRegionUnit->getGameUnit());
            $amount = $worldRegionUnit->getAmount();
            $unitName = $gameUnit->getName();
            $key = match ($gameUnit->getGameUnitCategory()) {
                GameUnitCategory::BUILDINGS => 'buildings',
                GameUnitCategory::DEFENSE_BUILDINGS => 'defences',
                GameUnitCategory::SPECIAL_BUILDINGS => 'special',
                GameUnitCategory::SPECIAL_UNITS => 'specialUnits',
                GameUnitCategory::TROOPS => 'troops',
                GameUnitCategory::NAVAL_UNITS => 'navalUnits',
                GameUnitCategory::AIR_UNITS => 'airUnits',
                GameUnitCategory::MISSILES => 'missiles',
            };

            $summary[$key] += $amount;
            $summary['details'][$key][] = ['name' => $unitName, 'amount' => $amount];
        }

        return $summary;
    }

    /**
     * Get which unit categories are present in a region (without revealing counts).
     * Used for showing enemy unit indicators with hidden amounts.
     *
     * @return array<string, bool>
     */
    public function getRegionUnitCategoriesPresence(WorldRegion $region): array
    {
        $presence = [
            'buildings' => false,
            'defences' => false,
            'special' => false,
            'specialUnits' => false,
            'troops' => false,
            'navalUnits' => false,
            'airUnits' => false,
            'missiles' => false,
        ];

        foreach ($region->getWorldRegionUnits() as $worldRegionUnit) {
            if ($worldRegionUnit->getAmount() > 0) {
                $gameUnit = $this->find($worldRegionUnit->getGameUnit());
                $key = match ($gameUnit->getGameUnitCategory()) {
                    GameUnitCategory::BUILDINGS => 'buildings',
                    GameUnitCategory::DEFENSE_BUILDINGS => 'defences',
                    GameUnitCategory::SPECIAL_BUILDINGS => 'special',
                    GameUnitCategory::SPECIAL_UNITS => 'specialUnits',
                    GameUnitCategory::TROOPS => 'troops',
                    GameUnitCategory::NAVAL_UNITS => 'navalUnits',
                    GameUnitCategory::AIR_UNITS => 'airUnits',
                    GameUnitCategory::MISSILES => 'missiles',
                };
                $presence[$key] = true;
            }
        }

        return $presence;
    }
}
