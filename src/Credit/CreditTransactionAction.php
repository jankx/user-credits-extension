<?php

namespace Jankx\Extensions\UserCredits\Credit;

final class CreditTransactionAction
{
    public const TOPUP = 'topup';
    public const DEDUCT = 'deduct';
    public const REFUND = 'refund';
    public const BOOKING = 'booking';
    public const COMMISSION = 'commission';

    private function __construct()
    {
    }

    public static function all(): array
    {
        return [
            self::TOPUP,
            self::DEDUCT,
            self::REFUND,
            self::BOOKING,
            self::COMMISSION,
        ];
    }

    /**
     * Actions that increase the balance.
     *
     * @return string[]
     */
    public static function credits(): array
    {
        return [
            self::TOPUP,
            self::REFUND,
            self::COMMISSION,
        ];
    }

    public static function isCredit(string $action): bool
    {
        return in_array($action, self::credits(), true);
    }

    public static function exists(string $action): bool
    {
        return in_array($action, self::all(), true);
    }

    public static function labels(): array
    {
        return [
            self::TOPUP => __('Nạp tiền', 'jankx'),
            self::DEDUCT => __('Trừ tiền', 'jankx'),
            self::REFUND => __('Hoàn tiền', 'jankx'),
            self::BOOKING => __('Thanh toán booking', 'jankx'),
            self::COMMISSION => __('Hoa hồng', 'jankx'),
        ];
    }

    public static function label(string $action): string
    {
        $labels = self::labels();

        return $labels[$action] ?? $action;
    }
}
