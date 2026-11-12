<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Payment;

use c975L\ConfigBundle\Contract\UserInterface;
use c975L\PaymentBundle\Entity\Basket;
use c975L\PaymentBundle\Enum\BasketStatus;
use c975L\PaymentBundle\Service\VatCalculator;

// What the backs' and the skins' baskets share, the class holding $security, $cardCatalog and $translator: digital content landing on an account, one line per item, VAT included in its price
trait CardBasketTrait
{
    // Digital content supplied at once on the account, which is what asks for the waiver of the right of withdrawal (see Basket::asksWithdrawalWaiver())
    public function getContentFlags(array $itemData): int
    {
        return Basket::CONTENT_FLAG_DIGITAL;
    }

    // Nothing to ask at validation: the account is all an item needs
    public function onBasketValidated(Basket $basket, array $itemsOfThisKind, array $requestData): array
    {
        return [];
    }

    // The account the basket lands on, the one signed in now when it was filled before signing in, null when none is - or when it is already an order (a shared one paid by a third party) whose account is gone, a third party paying a shared order never receiving it
    private function accountOf(Basket $basket): ?UserInterface
    {
        if (null === $basket->getUser() && BasketStatus::New === $basket->getStatus()) {
            $user = $this->security->getUser();
            if (!$user instanceof UserInterface) {
                return null;
            }
            $basket->setUser($user);
        }

        return $basket->getUser();
    }

    // Why a basket has no account to land on: nobody signed in while it is being filled, its account gone once it is an order
    private function noAccount(Basket $basket, string $signIn): string
    {
        return $this->translator->trans(BasketStatus::New === $basket->getStatus() ? $signIn : 'label.account_not_found', [], 'card');
    }

    // An item as the basket stores it, under its kind's heading, with no catalogue page of its own: it is bought where the collection shows it, the basket row drawing its image without a link (see PaymentBundle's Basket:Item)
    /** @return array<string, mixed> */
    private function basketData(int | string $id, string $title, ?string $media, int $price, float $vat, string $heading, int $quantity): array
    {
        $total = $quantity * $price;

        return [
            'item' => [
                'id' => $id,
                'title' => $title,
                'media' => $media,
                'price' => $price,
                'currency' => $this->cardCatalog->currency(),
                'vat' => $vat,
                'limitedQuantity' => 0,
                'orderedQuantity' => 0,
            ],
            'parent' => [
                'title' => $this->translator->trans($heading, [], 'card'),
            ],
            'type' => self::KIND,
            'quantity' => $quantity,
            'totalVat' => VatCalculator::included($total, $vat),
            'total' => $total,
        ];
    }
}
