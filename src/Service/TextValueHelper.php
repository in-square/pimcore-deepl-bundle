<?php

declare(strict_types=1);

namespace InSquare\PimcoreDeeplBundle\Service;

final class TextValueHelper
{
    public function isEmpty(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        if (is_string($value)) {
            $decoded = html_entity_decode($value, ENT_QUOTES | ENT_HTML5);
            $stripped = trim(strip_tags($decoded));

            if ($stripped === '') {
                return true;
            }

            return false;
        }

        if (is_array($value)) {
            return count($value) === 0;
        }

        return false;
    }
}
