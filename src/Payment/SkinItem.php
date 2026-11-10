<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Payment;

use c975L\CardBundle\Entity\CardSkin;

// A skin as it is sold, for one pack: what the basket holds under its store product id, the same in the stores and on the site
final readonly class SkinItem
{
    public function __construct(
        public CardSkin $skin,
        public string $pack,
    ) {
    }

    // Its store product id, the basket item's id as well: "skin.toon.gob"
    public function getId(): string
    {
        return (string) $this->skin->getProductId($this->pack);
    }

    // On sale while its skin is and still dresses that pack
    public function isForSale(): bool
    {
        return $this->skin->isForSale() && $this->skin->dresses($this->pack);
    }
}
