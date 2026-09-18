<?php

namespace Jankx\Extensions\UserCredits\Credit;

use Jankx\Extensions\UserCredits\CreditType\CreditType;

interface CreditBalanceStoreInterface
{
    public function get(int $userId, CreditType $type): float;

    public function set(int $userId, CreditType $type, float $balance): void;
}
