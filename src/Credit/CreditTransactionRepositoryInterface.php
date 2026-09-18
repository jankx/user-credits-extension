<?php

namespace Jankx\Extensions\UserCredits\Credit;

use Jankx\Extensions\UserCredits\CreditType\CreditType;

interface CreditTransactionRepositoryInterface
{
    public function record(
        int $userId,
        CreditType $type,
        string $action,
        float $amount,
        float $balanceAfter,
        string $note = '',
        string $title = ''
    ): CreditTransaction;

    /**
     * @return CreditTransaction[]
     */
    public function findByUser(int $userId, int $limit = 20, ?string $typeId = null): array;

    public function countByUser(int $userId, ?string $typeId = null): int;
}
