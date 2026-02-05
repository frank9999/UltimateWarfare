<?php

declare(strict_types=1);

namespace FrankProjects\UltimateWarfare\Entity\Enum;

enum GameUnitCategory: int
{
    case BUILDINGS = 1;
    case DEFENSE_BUILDINGS = 2;
    case SPECIAL_BUILDINGS = 3;
    case UNITS = 4;
    case SPECIAL_UNITS = 5;

    public function getImageDir(): string
    {
        return match($this) {
            self::BUILDINGS => 'units/buildings/',
            self::DEFENSE_BUILDINGS => 'units/defense_buildings/',
            self::SPECIAL_BUILDINGS => 'units/special_buildings/',
            self::UNITS => 'units/units/',
            self::SPECIAL_UNITS => 'units/special_units/',
        };
    }

    public function getLabel(): string
    {
        return match($this) {
            self::BUILDINGS => 'Buildings',
            self::DEFENSE_BUILDINGS => 'Defense Buildings',
            self::SPECIAL_BUILDINGS => 'Special Buildings',
            self::UNITS => 'Units',
            self::SPECIAL_UNITS => 'Elite Units',
        };
    }

    public static function isValid(int $integer): bool
    {
        foreach (GameUnitCategory::getAll() as $gameUnitCategory) {
            if ($gameUnitCategory->value === $integer) {
                return true;
            }
        }

        return false;
    }

    public static function fromInteger(int $integer): ?GameUnitCategory
    {
        foreach (GameUnitCategory::getAll() as $gameUnitCategory) {
            if ($gameUnitCategory->value === $integer) {
                return $gameUnitCategory;
            }
        }

        return null;
    }

    public static function getAll(): array
    {
        return [
            self::BUILDINGS,
            self::DEFENSE_BUILDINGS,
            self::SPECIAL_BUILDINGS,
            self::UNITS,
            self::SPECIAL_UNITS
        ];
    }
}