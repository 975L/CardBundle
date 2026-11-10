<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Payment;

use c975L\CardBundle\Repository\CardSkinRepository;
use c975L\CardBundle\Service\CardCatalog;
use c975L\CardBundle\Service\CardHolder;
use c975L\CardBundle\Service\SkinCollector;
use c975L\ConfigBundle\Contract\UserInterface;
use c975L\PaymentBundle\Contract\AccountBasketItemProviderInterface;
use c975L\PaymentBundle\Contract\BasketItemProviderInterface;
use c975L\PaymentBundle\Entity\Basket;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Contracts\Translation\TranslatorInterface;

// The skins on sale, pack by pack, in PaymentBundle's basket (see BasketItemProviderInterface), as BackBasketItemProvider sells the backs: an item is a skin for one pack, filed under its store product id ("skin.toon.gob"), landing on an account and given once paid through SkinCollector as "shop"
class SkinBasketItemProvider implements BasketItemProviderInterface, AccountBasketItemProviderInterface
{
    use CardBasketTrait;

    public const string KIND = 'cardskin';

    public function __construct(
        private readonly CardSkinRepository $skinRepository,
        private readonly SkinCollector $skinCollector,
        private readonly CardCatalog $cardCatalog,
        private readonly Security $security,
        private readonly TranslatorInterface $translator,
        private readonly LoggerInterface $logger,
    ) {
    }

    // The basket item type the skins are filed under
    public function getKind(): string
    {
        return self::KIND;
    }

    // The skin and pack an item id names, only while on sale
    public function findItem(int | string $id): ?object
    {
        $item = $this->item((string) $id);

        return true === $item?->isForSale() ? $item : null;
    }

    // Refuses a skin withdrawn, asked twice or already held for that pack, as the visitor fills the basket
    public function validateAddition(object $item, int $quantity): ?string
    {
        // A removal is never refused
        if ($quantity <= 0) {
            return null;
        }

        if (!$item instanceof SkinItem || !$item->isForSale()) {
            return $this->translator->trans('label.skin_unavailable', [], 'card');
        }

        // A skin is held once per pack: a second in the basket would be paid for nothing
        if ($quantity > 1) {
            return $this->translator->trans('label.skin_once', [], 'card');
        }

        $user = $this->security->getUser();

        return $user instanceof UserInterface && $this->holds($user, $item) ? $this->translator->trans('label.skin_already_held', [], 'card') : null;
    }

    // Asked again at the checkout: a skin may have been withdrawn since, it needs an account to land on - the one signed in now when the basket was filled before signing in - and that account may have earned it in the meantime
    public function validateCheckout(Basket $basket, array $itemsOfThisKind): ?string
    {
        $user = $this->accountOf($basket);
        if (null === $user) {
            return $this->translator->trans('label.sign_in_required_skin', [], 'card');
        }

        foreach ($itemsOfThisKind as $id => $itemContent) {
            $error = $this->checkoutError($user, (string) $id, (int) ($itemContent['quantity'] ?? 0));
            if (null !== $error) {
                return $error;
            }
        }

        return null;
    }

    // The skin as the basket stores it, named with its pack under the skins' heading
    public function toBasketData(object $item, int $quantity): array
    {
        \assert($item instanceof SkinItem);

        return $this->basketData($item->getId(), $item->skin->getName() . ' - ' . $this->cardCatalog->packName($item->pack), $item->skin->getImage(), (int) $item->skin->getPrice(), $item->skin->getVat(), 'label.card_skins', $quantity);
    }

    // Gives each skin for its pack to the account, one withdrawn since given all the same, the purchase being owed, and one earned in the meantime logged for the admin to refund
    public function onBasketPaid(Basket $basket, array $itemsOfThisKind, array $checkoutData): void
    {
        $user = $basket->getUser();
        if (null === $user) {
            return;
        }

        foreach (array_keys($itemsOfThisKind) as $id) {
            $item = $this->item((string) $id);
            if (null !== $item && !$this->skinCollector->grant(CardHolder::ofUser($user), $item->skin, $item->pack, BackBasketItemProvider::SOURCE, paid: true)) {
                $this->logger->warning('Card skin {skin} paid in basket {basket} but not given to {holder}', ['skin' => $id, 'basket' => $basket->getId(), 'holder' => CardHolder::ofUser($user)]);
            }
        }
    }

    // What refuses one line at the checkout, null when it can be paid
    private function checkoutError(UserInterface $user, string $id, int $quantity): ?string
    {
        $item = $this->findItem($id);
        if (null === $item) {
            return $this->translator->trans('label.skin_unavailable', [], 'card');
        }
        if ($quantity > 1) {
            return $this->translator->trans('label.skin_once', [], 'card');
        }

        return $this->holds($user, $item) ? $this->translator->trans('label.skin_already_held', [], 'card') : null;
    }

    // The skin and pack a product id names, on sale or not
    private function item(string $id): ?SkinItem
    {
        $found = $this->skinRepository->findByProductId($id);

        return null === $found ? null : new SkinItem(...$found);
    }

    // Whether the account already holds the skin for that pack, whichever way it got it
    private function holds(UserInterface $user, SkinItem $item): bool
    {
        return $this->skinCollector->holds(CardHolder::ofUser($user), $item->skin, $item->pack);
    }
}
