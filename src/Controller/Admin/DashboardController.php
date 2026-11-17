<?php

declare(strict_types=1);

namespace FrankProjects\UltimateWarfare\Controller\Admin;

use DateTimeImmutable;
use FrankProjects\UltimateWarfare\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

final class DashboardController extends AbstractController
{
    private UserRepository $userRepository;

    public function __construct(
        UserRepository $userRepository
    ) {
        $this->userRepository = $userRepository;
    }

    public function dashboard(): Response
    {
        $firstDateTime = new DateTimeImmutable('-24 hours');
        $lastDateTime = new DateTimeImmutable();

        return $this->render(
            'admin/dashboard.html.twig',
            [
                'userCount' => $this->userRepository->countForAdminList(''),
                'loggedInCount' => count($this->userRepository->findByLastSeenBetween($firstDateTime, $lastDateTime))
            ]
        );
    }
}
