<?php

declare(strict_types=1);

namespace FrankProjects\UltimateWarfare\Controller\Game;

use FrankProjects\UltimateWarfare\Form\DTO\BankTransactionFormDTO;
use FrankProjects\UltimateWarfare\Form\Game\BankTransactionType;
use FrankProjects\UltimateWarfare\Service\Action\FederationBankActionService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class FederationBankController extends BaseGameController
{
    private FederationBankActionService $federationBankActionService;

    public function __construct(
        FederationBankActionService $federationBankActionService
    ) {
        $this->federationBankActionService = $federationBankActionService;
    }

    public function deposit(Request $request): Response
    {
        $player = $this->getPlayer();
        $federation = $player->getFederation();
        if ($federation === null) {
            return $this->render(
                'game/federation/noFederation.html.twig',
                [
                    'player' => $player
                ]
            );
        }

        $bankTransaction = new BankTransactionFormDTO();
        $form = $this->createForm(BankTransactionType::class, $bankTransaction, [
            'submit_label' => 'Deposit',
        ]);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->federationBankActionService->deposit($player, $bankTransaction->toResourceArray());
                $this->addFlash('success', 'You successfully made a deposit!');
            } catch (Throwable $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render(
            'game/federation/bank/deposit.html.twig',
            [
                'player' => $player,
                'federationResources' => $federation->getResources(),
                'form' => $form->createView(),
            ]
        );
    }

    public function withdraw(Request $request): Response
    {
        $player = $this->getPlayer();
        $federation = $player->getFederation();
        if ($federation === null) {
            return $this->render(
                'game/federation/noFederation.html.twig',
                [
                    'player' => $player
                ]
            );
        }

        $bankTransaction = new BankTransactionFormDTO();
        $form = $this->createForm(BankTransactionType::class, $bankTransaction, [
            'submit_label' => 'Withdraw',
        ]);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->federationBankActionService->withdraw($player, $bankTransaction->toResourceArray());
                $this->addFlash('success', 'You successfully made a withdrawal!');
            } catch (Throwable $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render(
            'game/federation/bank/withdraw.html.twig',
            [
                'player' => $player,
                'federationResources' => $federation->getResources(),
                'form' => $form->createView(),
            ]
        );
    }
}
