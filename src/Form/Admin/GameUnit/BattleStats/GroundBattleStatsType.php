<?php

declare(strict_types=1);

namespace FrankProjects\UltimateWarfare\Form\Admin\GameUnit\BattleStats;

use FrankProjects\UltimateWarfare\Entity\GameUnit\BattleStats\GroundBattleStats;
use Symfony\Component\OptionsResolver\OptionsResolver;

class GroundBattleStatsType extends AbstractBattleStatsType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(
            [
                'data_class' => GroundBattleStats::class,
                'translation_domain' => 'gameunit'
            ]
        );
    }
}
