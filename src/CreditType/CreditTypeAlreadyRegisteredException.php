<?php

namespace Jankx\Extensions\UserCredits\CreditType;

use InvalidArgumentException;

final class CreditTypeAlreadyRegisteredException extends InvalidArgumentException
{
    public function __construct(string $id)
    {
        parent::__construct(sprintf('Credit type "%s" is already registered.', $id));
    }
}
