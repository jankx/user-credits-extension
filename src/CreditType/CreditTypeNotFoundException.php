<?php

namespace Jankx\Extensions\UserCredits\CreditType;

use RuntimeException;

final class CreditTypeNotFoundException extends RuntimeException
{
    public function __construct(string $id)
    {
        parent::__construct(sprintf('Credit type "%s" is not registered.', $id));
    }
}
