<?php

declare(strict_types=1);

namespace FrankProjects\UltimateWarfare\Form\DTO;

class BankTransactionFormDTO
{
    public int $cash = 0;
    public int $wood = 0;
    public int $steel = 0;
    public int $food = 0;

    /**
     * @return array<string, string>
     */
    public function toResourceArray(): array
    {
        return [
            'cash' => (string) $this->cash,
            'wood' => (string) $this->wood,
            'steel' => (string) $this->steel,
            'food' => (string) $this->food,
        ];
    }
}
