<?php

declare(strict_types=1);

namespace FrankProjects\UltimateWarfare\Controller\Game;

use FrankProjects\UltimateWarfare\Entity\Message;
use FrankProjects\UltimateWarfare\Repository\MessageRepository;
use FrankProjects\UltimateWarfare\Service\Action\MessageActionService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class MessageController extends BaseGameController
{
    private MessageRepository $messageRepository;
    private MessageActionService $messageActionService;

    public function __construct(
        MessageRepository $messageRepository,
        MessageActionService $messageActionService
    ) {
        $this->messageRepository = $messageRepository;
        $this->messageActionService = $messageActionService;
    }

    public function inbox(Request $request): Response
    {
        /**
         * XXX TODO: Fix pagination
         */
        $player = $this->getPlayer();
        if ($player->getNotifications()->getMessage()) {
            $this->messageActionService->disableMessageNotification($player);
        }

        foreach ($this->getSelectedMessagesFromRequest($request) as $messageId) {
            $this->messageActionService->deleteMessageFromInbox($player, $messageId);
            $this->addFlash('success', 'Message successfully deleted!');
        }

        $messages = $this->messageRepository->findNonDeletedMessagesToPlayer($player);

        return $this->render(
            'game/message/inbox.html.twig',
            [
                'player' => $player,
                'messages' => $messages
            ]
        );
    }

    public function inboxRead(int $messageId): Response
    {
        /**
         * XXX TODO: Fix smilies display
         */
        $player = $this->getPlayer();

        try {
            $message = $this->messageActionService->getMessageByIdAndToPlayer($messageId, $player);
            if ($message->getStatus() === Message::MESSAGE_STATUS_NEW) {
                $message->setStatus(Message::MESSAGE_STATUS_READ);
                $this->messageRepository->save($message);
            };
        } catch (Throwable $e) {
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute('Game/Message/Inbox');
        }

        return $this->render(
            'game/message/inboxRead.html.twig',
            [
                'player' => $player,
                'message' => $message
            ]
        );
    }

    public function inboxDelete(int $messageId): RedirectResponse
    {
        try {
            $this->messageActionService->deleteMessageFromInbox($this->getPlayer(), $messageId);
            $this->addFlash('success', 'Message successfully deleted!');
        } catch (Throwable $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('Game/Message/Inbox');
    }

    public function outbox(Request $request): Response
    {
        /**
         * XXX TODO: Fix pagination
         */
        $player = $this->getPlayer();

        foreach ($this->getSelectedMessagesFromRequest($request) as $messageId) {
            $this->messageActionService->deleteMessageFromOutbox($player, $messageId);
            $this->addFlash('success', 'Message successfully deleted!');
        }

        $messages = $this->messageRepository->findNonDeletedMessagesFromPlayer($player);

        return $this->render(
            'game/message/outbox.html.twig',
            [
                'player' => $player,
                'messages' => $messages
            ]
        );
    }

    public function outboxRead(int $messageId): Response
    {
        /**
         * XXX TODO: Fix smilies display
         */
        try {
            $message = $this->messageActionService->getMessageByIdAndFromPlayer($messageId, $this->getPlayer());
        } catch (Throwable $e) {
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute('Game/Message/Outbox');
        }

        return $this->render(
            'game/message/outboxRead.html.twig',
            [
                'player' => $this->getPlayer(),
                'message' => $message
            ]
        );
    }

    public function outboxDelete(int $messageId): RedirectResponse
    {
        try {
            $this->messageActionService->deleteMessageFromOutbox($this->getPlayer(), $messageId);
            $this->addFlash('success', 'Message successfully deleted!');
        } catch (Throwable $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('Game/Message/Outbox');
    }

    public function newMessage(Request $request, string $playerName = ''): Response
    {
        $player = $this->getPlayer();

        if ($playerName === '') {
            $playerName = $request->request->getString('toPlayerName');
        }

        $adminMessage = $request->request->getBoolean('admin', false);

        // XXX TODO: Add form with isSubmitted && isValid
        if ($request->isMethod(Request::METHOD_POST)) {
            try {
                $this->messageActionService->sendMessage(
                    $player,
                    $request->request->getString('subject'),
                    $request->request->getString('message'),
                    $playerName,
                    $adminMessage
                );

                $this->addFlash('success', 'Message send!');
            } catch (Throwable $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render(
            'game/message/new.html.twig',
            [
                'player' => $player,
                'toPlayerName' => $playerName,
                'subject' => $request->request->getString('subject'),
                'message' => $request->request->getString('message')
            ]
        );
    }

    public function sendMessageApi(Request $request): JsonResponse
    {
        try {
            $player = $this->getPlayer();

            /** @var array{toPlayerName?: string, subject?: string, message?: string} $data */
            $data = json_decode($request->getContent(), true) ?? [];

            $this->messageActionService->sendMessage(
                $player,
                $data['subject'] ?? '',
                $data['message'] ?? '',
                $data['toPlayerName'] ?? '',
                false
            );

            return new JsonResponse([
                'success' => true,
                'message' => 'Message sent!'
            ]);
        } catch (Throwable $e) {
            return new JsonResponse([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function inboxApi(): JsonResponse
    {
        $player = $this->getPlayer();

        if ($player->getNotifications()->getMessage()) {
            $this->messageActionService->disableMessageNotification($player);
        }

        $messages = $this->messageRepository->findNonDeletedMessagesToPlayer($player);

        $messagesData = [];
        foreach ($messages as $message) {
            $messagesData[] = [
                'id' => $message->getId(),
                'from' => $message->getFromPlayer()->getName(),
                'subject' => $message->getSubject(),
                'date' => date('M d, Y H:i', $message->getTimestamp()),
                'timestamp' => $message->getTimestamp(),
                'isNew' => $message->getStatus() === Message::MESSAGE_STATUS_NEW,
                'isAdmin' => $message->getAdminMessage(),
            ];
        }

        return new JsonResponse([
            'success' => true,
            'messages' => $messagesData,
        ]);
    }

    public function outboxApi(): JsonResponse
    {
        $player = $this->getPlayer();
        $messages = $this->messageRepository->findNonDeletedMessagesFromPlayer($player);

        $messagesData = [];
        foreach ($messages as $message) {
            $messagesData[] = [
                'id' => $message->getId(),
                'to' => $message->getToPlayer()->getName(),
                'subject' => $message->getSubject(),
                'date' => date('M d, Y H:i', $message->getTimestamp()),
                'timestamp' => $message->getTimestamp(),
            ];
        }

        return new JsonResponse([
            'success' => true,
            'messages' => $messagesData,
        ]);
    }

    public function readMessageApi(int $messageId): JsonResponse
    {
        try {
            $player = $this->getPlayer();
            $message = $this->messageActionService->getMessageByIdAndToPlayer($messageId, $player);

            if ($message->getStatus() === Message::MESSAGE_STATUS_NEW) {
                $message->setStatus(Message::MESSAGE_STATUS_READ);
                $this->messageRepository->save($message);
            }

            return new JsonResponse([
                'success' => true,
                'message' => [
                    'id' => $message->getId(),
                    'from' => $message->getFromPlayer()->getName(),
                    'subject' => $message->getSubject(),
                    'body' => $message->getMessage(),
                    'date' => date('M d, Y H:i', $message->getTimestamp()),
                    'isAdmin' => $message->getAdminMessage(),
                ],
            ]);
        } catch (Throwable $e) {
            return new JsonResponse([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function readOutboxMessageApi(int $messageId): JsonResponse
    {
        try {
            $player = $this->getPlayer();
            $message = $this->messageActionService->getMessageByIdAndFromPlayer($messageId, $player);

            return new JsonResponse([
                'success' => true,
                'message' => [
                    'id' => $message->getId(),
                    'to' => $message->getToPlayer()->getName(),
                    'subject' => $message->getSubject(),
                    'body' => $message->getMessage(),
                    'date' => date('M d, Y H:i', $message->getTimestamp()),
                ],
            ]);
        } catch (Throwable $e) {
            return new JsonResponse([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function deleteInboxMessageApi(int $messageId): JsonResponse
    {
        try {
            $this->messageActionService->deleteMessageFromInbox($this->getPlayer(), $messageId);

            return new JsonResponse([
                'success' => true,
                'message' => 'Message deleted!'
            ]);
        } catch (Throwable $e) {
            return new JsonResponse([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function deleteOutboxMessageApi(int $messageId): JsonResponse
    {
        try {
            $this->messageActionService->deleteMessageFromOutbox($this->getPlayer(), $messageId);

            return new JsonResponse([
                'success' => true,
                'message' => 'Message deleted!'
            ]);
        } catch (Throwable $e) {
            return new JsonResponse([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * @return array<int>
     */
    private function getSelectedMessagesFromRequest(Request $request): array
    {
        $selectedMessages = [];

        if (
            $request->isMethod(Request::METHOD_POST) &&
            $request->request->get('del') !== null
        ) {
            /** @var array<int> $selectedMessageArray */
            $selectedMessageArray = $request->request->all('selected_messages');
            foreach ($selectedMessageArray as $messageId) {
                $selectedMessages[] = intval($messageId);
            }
        }

        return $selectedMessages;
    }
}
