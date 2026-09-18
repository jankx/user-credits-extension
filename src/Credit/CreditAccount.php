<?php

namespace Jankx\Extensions\UserCredits\Credit;

use InvalidArgumentException;
use Jankx\Extensions\UserCredits\CreditType\CreditType;
use Jankx\Extensions\UserCredits\CreditType\CreditTypeRegistryInterface;

final class CreditAccount
{
    private CreditBalanceStoreInterface $balances;

    private CreditTransactionRepositoryInterface $transactions;

    private CreditTypeRegistryInterface $registry;

    public function __construct(
        CreditBalanceStoreInterface $balances,
        CreditTransactionRepositoryInterface $transactions,
        CreditTypeRegistryInterface $registry
    ) {
        $this->balances = $balances;
        $this->transactions = $transactions;
        $this->registry = $registry;
    }

    public function registry(): CreditTypeRegistryInterface
    {
        return $this->registry;
    }

    public function resolveType(?string $typeId = null): CreditType
    {
        if ($typeId === null || $typeId === '') {
            return $this->registry->getDefault();
        }

        return $this->registry->get($typeId);
    }

    public function getBalance(int $userId, ?string $typeId = null): float
    {
        return $this->balances->get($userId, $this->resolveType($typeId));
    }

    public function setBalance(int $userId, float $balance, ?string $typeId = null): void
    {
        $this->balances->set($userId, $this->resolveType($typeId), $balance);
    }

    public function deposit(int $userId, float $amount, string $note = '', ?string $typeId = null, string $title = ''): CreditTransaction
    {
        return $this->transact($userId, CreditTransactionAction::TOPUP, $amount, $note, $typeId, $title);
    }

    public function withdraw(int $userId, float $amount, string $note = '', ?string $typeId = null, string $title = ''): CreditTransaction
    {
        return $this->transact($userId, CreditTransactionAction::DEDUCT, $amount, $note, $typeId, $title);
    }

    public function transact(
        int $userId,
        string $action,
        float $amount,
        string $note = '',
        ?string $typeId = null,
        string $title = ''
    ): CreditTransaction {
        if ($userId <= 0) {
            throw new InvalidArgumentException('A valid user id is required.');
        }

        if ($amount <= 0) {
            throw new InvalidArgumentException('The credit amount must be greater than zero.');
        }

        if (!CreditTransactionAction::exists($action)) {
            throw new InvalidArgumentException(sprintf('Unknown credit action "%s".', $action));
        }

        $type = $this->resolveType($typeId);
        $balance = $this->balances->get($userId, $type);
        $isCredit = CreditTransactionAction::isCredit($action);
        $newBalance = $isCredit ? $balance + $amount : $balance - $amount;

        if ($newBalance < 0) {
            throw new InsufficientCreditBalanceException($type, $balance, $amount);
        }

        $this->balances->set($userId, $type, $newBalance);

        $transaction = $this->transactions->record(
            $userId,
            $type,
            $action,
            $amount,
            $newBalance,
            $note,
            $title
        );

        do_action('jankx/user-credits/transacted', $transaction, $type);
        do_action('jankx/user-credits/balance-changed', $userId, $type, $newBalance, $balance);

        return $transaction;
    }

    /**
     * @return CreditTransaction[]
     */
    public function getTransactions(int $userId, int $limit = 20, ?string $typeId = null): array
    {
        return $this->transactions->findByUser($userId, $limit, $typeId);
    }

    public function countTransactions(int $userId, ?string $typeId = null): int
    {
        return $this->transactions->countByUser($userId, $typeId);
    }
}
