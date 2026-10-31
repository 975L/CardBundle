<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Payment;

use c975L\CardBundle\Entity\CardBack;
use c975L\CardBundle\Repository\CardBackRepository;
use c975L\CardBundle\Repository\CollectedBackRepository;
use c975L\CardBundle\Service\BackCollector;
use c975L\CardBundle\Service\CardCatalog;
use c975L\CardBundle\Service\CardHolder;
use c975L\ConfigBundle\Contract\UserInterface;
use c975L\PaymentBundle\Contract\AccountBasketItemProviderInterface;
use c975L\PaymentBundle\Contract\BasketItemProviderInterface;
use c975L\PaymentBundle\Entity\Basket;
use c975L\PaymentBundle\Service\VatCalculator;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Contracts\Translation\TranslatorInterface;

// The backs on sale, in PaymentBundle's basket (see BasketItemProviderInterface): the site's own sale, beside the app stores'. A back lands on an account, so the visitor is asked to sign in at the checkout (see AccountBasketItemProviderInterface), and once paid it is given through BackCollector as "shop"
class BackBasketItemProvider implements BasketItemProviderInterface, AccountBasketItemProviderInterface
{
    public const string KIND = 'cardback';

    // What a back bought on the site is given as, beside the stores' "apple" and "google"
    public const string SOURCE = 'shop';

    public function __construct(
        private readonly CardBackRepository $backRepository,
        private readonly CollectedBackRepository $collectedBackRepository,
        private readonly BackCollector $backCollector,
        private readonly CardCatalog $cardCatalog,
        private readonly Security $security,
        private readonly TranslatorInterface $translator,
        private readonly LoggerInterface $logger,
    ) {
    }

    // The basket item type the backs are filed under
    public function getKind(): string
    {
        return self::KIND;
    }

    // The back an item id names, only while it is on sale
    public function findItem(int | string $id): ?object
    {
        $back = $this->backRepository->find((int) $id);

        return null !== $back && $back->isForSale() ? $back : null;
    }

    // Refuses a back withdrawn, asked twice or already held, as the visitor fills the basket
    public function validateAddition(object $item, int $quantity): ?string
    {
        // A removal is never refused
        if ($quantity <= 0) {
            return null;
        }

        if (!$item instanceof CardBack || !$item->isForSale()) {
            return $this->translator->trans('label.back_unavailable', [], 'card');
        }

        // A back is held once: a second in the basket would be paid for nothing
        if ($quantity > 1) {
            return $this->translator->trans('label.back_once', [], 'card');
        }

        $user = $this->security->getUser();

        return $user instanceof UserInterface && $this->holds($user, $item) ? $this->translator->trans('label.back_already_held', [], 'card') : null;
    }

    // Asked again at the checkout: a back may have been withdrawn since, it needs an account to land on - the one signed in now when the basket was filled before signing in - and that account may have earned it in the meantime
    public function validateCheckout(Basket $basket, array $itemsOfThisKind): ?string
    {
        if (null === $basket->getUser()) {
            $user = $this->security->getUser();
            if (!$user instanceof UserInterface) {
                return $this->translator->trans('label.sign_in_required', [], 'card');
            }
            $basket->setUser($user);
        }

        foreach ($itemsOfThisKind as $id => $itemContent) {
            $back = $this->findItem($id);
            if (null === $back) {
                return $this->translator->trans('label.back_unavailable', [], 'card');
            }
            if ((int) ($itemContent['quantity'] ?? 0) > 1) {
                return $this->translator->trans('label.back_once', [], 'card');
            }
            if ($this->holds($basket->getUser(), $back)) {
                return $this->translator->trans('label.back_already_held', [], 'card');
            }
        }

        return null;
    }

    // The back as the basket stores it, under the card backs' heading, VAT included in its price
    public function toBasketData(object $item, int $quantity): array
    {
        /** @var CardBack $item */
        $total = $quantity * (int) $item->getPrice();

        return [
            'item' => [
                'id' => $item->getId(),
                'title' => $item->getName(),
                'price' => $item->getPrice(),
                'currency' => $this->cardCatalog->currency(),
                'vat' => $item->getVat(),
                'limitedQuantity' => 0,
                'orderedQuantity' => 0,
            ],
            // No catalogue page of its own: a back is bought where the collection shows it
            'parent' => [
                'title' => $this->translator->trans('label.card_backs', [], 'card'),
            ],
            'type' => self::KIND,
            'quantity' => $quantity,
            'totalVat' => VatCalculator::included($total, $item->getVat()),
            'total' => $total,
        ];
    }

    // Digital content supplied at once on the account, which is what asks for the waiver of the right of withdrawal (see Basket::asksWithdrawalWaiver())
    public function getContentFlags(array $itemData): int
    {
        return Basket::CONTENT_FLAG_DIGITAL;
    }

    // Nothing to ask at validation: the account is all a back needs
    public function onBasketValidated(Basket $basket, array $itemsOfThisKind, array $requestData): array
    {
        return [];
    }

    // Gives each back to the account, validateCheckout() having made sure just before the payment that it was still on sale - one withdrawn since is given all the same, the purchase being owed, and one earned in the meantime is logged for the admin to refund
    public function onBasketPaid(Basket $basket, array $itemsOfThisKind, array $checkoutData): void
    {
        $user = $basket->getUser();
        if (null === $user) {
            return;
        }

        foreach (array_keys($itemsOfThisKind) as $id) {
            $back = $this->backRepository->find((int) $id);
            if (null !== $back && !$this->backCollector->grant(CardHolder::ofUser($user), $back, self::SOURCE, paid: true)) {
                $this->logger->warning('Card back {back} paid in basket {basket} but not given to {holder}', ['back' => $back->getSlug(), 'basket' => $basket->getId(), 'holder' => CardHolder::ofUser($user)]);
            }
        }
    }

    // Whether the account already holds the back, whichever way it got it
    private function holds(UserInterface $user, CardBack $back): bool
    {
        return null !== $this->collectedBackRepository->findOneBy(['holder' => CardHolder::ofUser($user), 'back' => $back]);
    }
}
