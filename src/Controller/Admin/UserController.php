<?php

declare(strict_types=1);

namespace FrankProjects\UltimateWarfare\Controller\Admin;

use FrankProjects\UltimateWarfare\Entity\User;
use FrankProjects\UltimateWarfare\Repository\UserRepository;
use FrankProjects\UltimateWarfare\Service\Action\UserActionService;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class UserController extends AbstractController
{
    private UserRepository $userRepository;
    private UserActionService $userActionService;

    public function __construct(
        UserRepository $userRepository,
        UserActionService $userActionService
    ) {
        $this->userRepository = $userRepository;
        $this->userActionService = $userActionService;
    }

    public function ban(int $userId): RedirectResponse
    {
        $user = $this->getUserObject($userId);
        if (!$user->getActive()) {
            $this->addFlash('error', 'User is already banned');
        } else {
            $user->setActive(false);
            $this->userRepository->save($user);
            $this->addFlash('success', 'User banned!');
        }

        return $this->redirectToRoute('Admin/User/Read', ['userId' => $userId], 302);
    }

    public function unban(int $userId): RedirectResponse
    {
        $user = $this->getUserObject($userId);
        if ($user->getActive()) {
            $this->addFlash('error', 'User is not banned');
        } else {
            $user->setActive(true);
            $this->userRepository->save($user);
            $this->addFlash('success', 'User unbanned!');
        }

        return $this->redirectToRoute('Admin/User/Read', ['userId' => $userId], 302);
    }

    public function enable(int $userId): RedirectResponse
    {
        $user = $this->getUserObject($userId);
        if ($user->isEnabled()) {
            $this->addFlash('error', 'User is already enabled');
        } else {
            $user->setEnabled(true);
            $user->setConfirmationToken(null);
            $this->userRepository->save($user);
            $this->addFlash('success', 'User enabled!');
        }

        return $this->redirectToRoute('Admin/User/Read', ['userId' => $userId], 302);
    }

    public function list(): Response
    {
        return $this->render(
            'admin/user/list.html.twig',
            [
                'users' => $this->userRepository->findAll()
            ]
        );
    }

    public function makeAdmin(int $userId): RedirectResponse
    {
        try {
            $user = $this->getUserObject($userId);
            $this->userActionService->addRoleToUser($user, User::ROLE_ADMIN);
            $this->addFlash('success', 'Add admin role to user!');
        } catch (Throwable $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('Admin/User/Read', ['userId' => $userId], 302);
    }

    public function removeAdmin(int $userId): RedirectResponse
    {
        try {
            $user = $this->getUserObject($userId);
            $this->userActionService->removeRoleFromUser($user, User::ROLE_ADMIN);
            $this->addFlash('success', 'Removed admin role from user!');
        } catch (Throwable $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('Admin/User/Read', ['userId' => $userId], 302);
    }

    public function read(int $userId): Response
    {
        return $this->render(
            'admin/user/read.html.twig',
            [
                'user' => $this->userRepository->find($userId)
            ]
        );
    }

    private function getUserObject(int $userId): User
    {
        $user = $this->userRepository->find($userId);
        if ($user === null) {
            throw new RuntimeException('User does not exist');
        }

        return $user;
    }
}
