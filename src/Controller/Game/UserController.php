<?php

declare(strict_types=1);

namespace FrankProjects\UltimateWarfare\Controller\Game;

use FrankProjects\UltimateWarfare\Entity\UnbanRequest;
use FrankProjects\UltimateWarfare\Repository\UnbanRequestRepository;
use FrankProjects\UltimateWarfare\Repository\UserRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class UserController extends BaseGameController
{
    private UserRepository $userRepository;
    private UnbanRequestRepository $unbanRequestRepository;

    public function __construct(
        UserRepository $userRepository,
        UnbanRequestRepository $unbanRequestRepository
    ) {
        $this->userRepository = $userRepository;
        $this->unbanRequestRepository = $unbanRequestRepository;
    }

    public function banned(Request $request): Response
    {
        $user = $this->getGameUser(false);
        if ($user->getActive()) {
            $this->addFlash('error', 'You are not banned!');
            return $this->redirectToRoute('Game/WorldMap');
        }

        $unbanRequest = $this->unbanRequestRepository->findByUser($user);

        if ($unbanRequest === null) {
            $unbanRequest = new UnbanRequest();
        }

        if ($request->isMethod(Request::METHOD_POST)) {
            $unbanReason = trim((string) $request->request->get('post'));

            $unbanRequest->setPost($unbanReason);
            $unbanRequest->setUser($user);
            $this->unbanRequestRepository->save($unbanRequest);

            $this->addFlash('success', 'We have received your request, we will try to read your request ASAP...');
        }

        return $this->render(
            'game/banned.html.twig',
            [
                'user' => $user,
                'unbanRequest' => $unbanRequest
            ]
        );
    }

    public function profileApi(): JsonResponse
    {
        $user = $this->getGameUser();
        $avatarBase64 = '';
        if ($user->hasAvatar()) {
            $avatar = $user->getAvatar();
            if (is_resource($avatar)) {
                $avatarBase64 = base64_encode((string) stream_get_contents($avatar));
            }
        }

        return new JsonResponse([
            'success' => true,
            'data' => [
                'username' => $user->getUsername(),
                'email' => $user->getEmail(),
                'signup' => $user->getSignup()->format('Y-m-d H:i:s'),
                'accountType' => $this->getAccountType(),
                'active' => $user->getActive(),
                'hasAvatar' => $user->hasAvatar(),
                'avatarBase64' => $avatarBase64,
            ]
        ]);
    }

    public function changePasswordApi(
        Request $request,
        UserPasswordHasherInterface $passwordHasher
    ): JsonResponse {
        $user = $this->getGameUser();

        try {
            /** @var array{oldPassword?: string, newPassword?: string, newPasswordRepeat?: string} $data */
            $data = json_decode($request->getContent(), true);
            $oldPassword = $data['oldPassword'] ?? '';
            $newPassword = $data['newPassword'] ?? '';
            $newPasswordRepeat = $data['newPasswordRepeat'] ?? '';

            if ($oldPassword === '' || $newPassword === '') {
                return new JsonResponse(['success' => false, 'message' => 'All fields are required.']);
            }

            if (strlen($newPassword) < 8) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'New password must be at least 8 characters.',
                ]);
            }

            if ($newPassword !== $newPasswordRepeat) {
                return new JsonResponse(['success' => false, 'message' => 'New passwords do not match.']);
            }

            if (!$passwordHasher->isPasswordValid($user, $oldPassword)) {
                return new JsonResponse(['success' => false, 'message' => 'Old password is invalid.']);
            }

            $newEncodedPassword = $passwordHasher->hashPassword($user, $newPassword);
            $user->setPassword($newEncodedPassword);
            $this->userRepository->save($user);

            return new JsonResponse(['success' => true, 'message' => 'Password changed successfully!']);
        } catch (\Throwable $e) {
            return new JsonResponse(['success' => false, 'message' => 'An error occurred.']);
        }
    }

    public function uploadAvatarApi(Request $request): JsonResponse
    {
        $user = $this->getGameUser();

        try {
            $avatar = $request->files->get('avatar');
            if ($avatar === null) {
                return new JsonResponse(['success' => false, 'message' => 'No file uploaded.']);
            }

            /** @var \Symfony\Component\HttpFoundation\File\UploadedFile $avatar */
            if ($avatar->getSize() > 1024 * 1024) {
                return new JsonResponse(['success' => false, 'message' => 'File is too large. Maximum size is 1MB.']);
            }

            $uploadedFile = $user->getId() . '-' . uniqid('', true) . '.' . $avatar->guessExtension();

            $avatar->move(
                $this->getParameter('app.avatars_directory'),
                $uploadedFile
            );

            $image = new \Imagick($this->getParameter('app.avatars_directory') . '/' . $uploadedFile);
            $image->setImageFormat('png');
            $image->setImageBackgroundColor('transparent');
            $image->setImageAlphaChannel(\Imagick::ALPHACHANNEL_OPAQUE);
            $image->cropThumbnailImage(200, 200);
            $image->roundCornersImage(100, 100);

            unlink($this->getParameter('app.avatars_directory') . '/' . $uploadedFile);

            $user->setAvatar($image->getImageBlob());
            $this->userRepository->save($user);

            return new JsonResponse(['success' => true, 'message' => 'Avatar uploaded successfully!']);
        } catch (\Throwable $e) {
            return new JsonResponse(['success' => false, 'message' => 'Could not upload avatar.']);
        }
    }

    public function deleteAvatarApi(): JsonResponse
    {
        $user = $this->getGameUser();
        $user->setAvatar('');
        $this->userRepository->save($user);

        return new JsonResponse(['success' => true, 'message' => 'Avatar deleted successfully!']);
    }

    private function getAccountType(): string
    {
        $user = $this->getGameUser();
        $roles = $user->getRoles();

        if (in_array('ROLE_PLAYER', $roles, true)) {
            return 'Player';
        }

        if (in_array('ROLE_ADMIN', $roles, true)) {
            return 'Admin';
        }

        return 'Guest';
    }
}
