<?php

namespace Jankx\Extensions\UserCredits\Credit;

use Jankx\Extensions\UserCredits\CreditType\CreditType;
use RuntimeException;

final class InsufficientCreditBalanceException extends RuntimeException
{
    private CreditType $type;

    private float $balance;

    private float $requested;

    public function __construct(CreditType $type, float $balance, float $requested)
    {
        $this->type = $type;
        $this->balance = $balance;
        $this->requested = $requested;

        parent::__construct(sprintf(
            'Insufficient %s balance: requested %s, available %s.',
            $type->getId(),
            $type->format($requested),
            $type->format($balance)
        ));
    }

    public function getType(): CreditType
    {
        return $this->type;
    }

    public function getBalance(): float
    {
        return $this->balance;
    }

    public function getRequested(): float
    {
        return $this->requested;
    }
}
