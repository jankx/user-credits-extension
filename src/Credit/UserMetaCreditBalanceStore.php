<?php

namespace Jankx\Extensions\UserCredits\Credit;

use Jankx\Extensions\UserCredits\CreditType\CreditType;

final class UserMetaCreditBalanceStore implements CreditBalanceStoreInterface
{
    public function get(int $userId, CreditType $type): float
    {
        if ($userId <= 0) {
            return 0.0;
        }

        $value = get_user_meta($userId, $type->getMetaKey(), true);

        return is_numeric($value) ? (float) $value : 0.0;
    }

    public function set(int $userId, CreditType $type, float $balance): void
    {
        if ($userId <= 0) {
            return;
        }

        update_user_meta($userId, $type->getMetaKey(), max(0.0, $balance));
    }
}
