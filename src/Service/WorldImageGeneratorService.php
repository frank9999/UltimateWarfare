<?php

declare(strict_types=1);

namespace FrankProjects\UltimateWarfare\Service;

use FrankProjects\UltimateWarfare\Entity\World;
use FrankProjects\UltimateWarfare\Repository\WorldRepository;
use FrankProjects\UltimateWarfare\Service\WorldGenerator\ImageBuilder\WorldImageBuilder;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

final class WorldImageGeneratorService
{
    private ParameterBagInterface $params;
    private WorldRepository $worldRepository;

    public function __construct(
        ParameterBagInterface $params,
        WorldRepository $worldRepository
    ) {
        $this->params = $params;
        $this->worldRepository = $worldRepository;
    }

    public function generateWorldImage(World $world): void
    {
        $worldImageName = $world->getId() . '.jpg';
        $worldImagePath = $this->params->get('kernel.project_dir') . '/public/images/world/' . $worldImageName;

        // Refresh object from DB, otherwise world image generation will fail
        $this->worldRepository->refresh($world);

        $worldImageBuilder = new WorldImageBuilder();
        $worldImageBuilder->generateForWorld($world, $worldImagePath);

        $world->setImage($worldImageName);
        $this->worldRepository->save($world);
    }
}
