<?php

namespace Jankx\Extensions\UserCredits\Credit;

final class CreditTransaction
{
    private int $id;

    private int $userId;

    private string $walletId;

    private string $action;

    private float $amount;

    private float $balanceAfter;

    private string $note;

    private string $date;

    private string $title;

    public function __construct(
        int $id,
        int $userId,
        string $walletId,
        string $action,
        float $amount,
        float $balanceAfter,
        string $note = '',
        string $date = '',
        string $title = ''
    ) {
        $this->id = $id;
        $this->userId = $userId;
        $this->walletId = $walletId;
        $this->action = $action;
        $this->amount = $amount;
        $this->balanceAfter = $balanceAfter;
        $this->note = $note;
        $this->date = $date;
        $this->title = $title;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getWalletId(): string
    {
        return $this->walletId;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function getBalanceAfter(): float
    {
        return $this->balanceAfter;
    }

    public function getNote(): string
    {
        return $this->note;
    }

    public function getDate(): string
    {
        return $this->date;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function isCredit(): bool
    {
        return CreditTransactionAction::isCredit($this->action);
    }

    public function getSignedAmount(): float
    {
        return $this->isCredit() ? $this->amount : -$this->amount;
    }

    public function getActionLabel(): string
    {
        return CreditTransactionAction::label($this->action);
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'user_id' => $this->userId,
            'type' => $this->action,
            'action' => $this->action,
            'action_label' => $this->getActionLabel(),
            'wallet' => $this->walletId,
            'amount' => $this->amount,
            'signed_amount' => $this->getSignedAmount(),
            'balance_after' => $this->balanceAfter,
            'note' => $this->note,
            'date' => $this->date,
        ];
    }
}
