<?php

namespace Jankx\Extensions\UserCredits\CreditType;

use InvalidArgumentException;

final class CreditType
{
    public const DEFAULT_ID = 'coin';

    public const LEGACY_META_KEY = 'user_credits_balance';

    private string $id;

    private string $label;

    private string $symbol;

    private string $metaKey;

    private int $decimals;

    private int $priority;

    public function __construct(
        string $id,
        string $label = '',
        string $symbol = '',
        string $metaKey = '',
        int $decimals = 0,
        int $priority = 10
    ) {
        $id = strtolower(trim($id));

        if (!preg_match('/^[a-z0-9][a-z0-9_-]*$/', $id)) {
            throw new InvalidArgumentException(sprintf('Invalid credit type id: "%s".', $id));
        }

        if ($decimals < 0 || $decimals > 8) {
            throw new InvalidArgumentException('Credit type decimals must be between 0 and 8.');
        }

        $this->id = $id;
        $this->label = $label !== '' ? $label : ucfirst(str_replace(['-', '_'], ' ', $id));
        $this->symbol = $symbol !== '' ? $symbol : $id;
        $this->metaKey = $metaKey !== '' ? $metaKey : self::defaultMetaKey($id);
        $this->decimals = $decimals;
        $this->priority = $priority;
    }

    public static function fromArray(array $config): self
    {
        if (empty($config['id'])) {
            throw new InvalidArgumentException('A credit type requires an "id".');
        }

        return new self(
            (string) $config['id'],
            isset($config['label']) ? (string) $config['label'] : '',
            isset($config['symbol']) ? (string) $config['symbol'] : '',
            isset($config['meta_key']) ? (string) $config['meta_key'] : '',
            isset($config['decimals']) ? (int) $config['decimals'] : 0,
            isset($config['priority']) ? (int) $config['priority'] : 10
        );
    }

    public static function defaultMetaKey(string $id): string
    {
        return $id === self::DEFAULT_ID
            ? self::LEGACY_META_KEY
            : 'jankx_credit_balance_' . strtolower($id);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getSymbol(): string
    {
        return $this->symbol;
    }

    public function getMetaKey(): string
    {
        return $this->metaKey;
    }

    public function getDecimals(): int
    {
        return $this->decimals;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function is(string $id): bool
    {
        return $this->id === strtolower($id);
    }

    public function format(float $amount): string
    {
        return number_format($amount, $this->decimals, ',', '.') . ' ' . $this->symbol;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'symbol' => $this->symbol,
            'meta_key' => $this->metaKey,
            'decimals' => $this->decimals,
            'priority' => $this->priority,
        ];
    }
}
