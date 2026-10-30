<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Tests\Service;

use Doctrine\DBAL\Driver\Exception as DriverException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;

// The same gift sent twice at once, as the database answers it: the check passed, the unique index refusing the flush
trait DuplicateGiftTrait
{
    private function entityManagerRefusingADuplicate(): EntityManagerInterface
    {
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('flush')->willThrowException(new UniqueConstraintViolationException($this->createStub(DriverException::class), null));

        return $entityManager;
    }
}
