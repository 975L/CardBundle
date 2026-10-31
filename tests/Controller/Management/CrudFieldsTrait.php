<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\CardBundle\Tests\Controller\Management;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Controller\CrudControllerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;

// The fields a CRUD controller shows on a page, by property
trait CrudFieldsTrait
{
    /** @return array<string, FieldInterface> */
    private function fieldsOf(CrudControllerInterface $controller, string $pageName): array
    {
        $fields = [];
        foreach ($controller->configureFields($pageName) as $field) {
            if ($field->getAsDto()->isDisplayedOn($pageName)) {
                $fields[$field->getAsDto()->getProperty()] = $field;
            }
        }

        return $fields;
    }
}
