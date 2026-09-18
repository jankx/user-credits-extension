<?php
namespace Jankx\Extensions\UserCredits\Reward;

use Jankx\Extensions\UserCredits\Credit\CreditAccount;
use Jankx\Extensions\UserCredits\Credit\CreditTransactionAction;
use Jankx\Extensions\UserCredits\CreditType\CreditManager;

/**
 * Awards reward (bonus) credits to a customer once an order is recorded via
 * the base-ecommerce checkout flow.
 *
 * Only logged-in customers with a WordPress account earn rewards, so every
 * order (including ones placed manually by telesales on behalf of the
 * customer) is linked to a user. The conversion rate is configurable in the
 * credit settings screen: "X VND of order value = 1 credit".
 *
 * @package Jankx\Extensions\UserCredits
 */
class OrderRewardIntegration
{
    const OPTION_ENABLED           = 'jankx_credit_reward_enabled';
    const OPTION_AMOUNT_PER_CREDIT = 'jankx_credit_reward_amount_per_credit';
    const OPTION_MIN_ORDER_TOTAL   = 'jankx_credit_reward_min_order_total';
    const OPTION_WALLET            = 'jankx_credit_reward_wallet';

    const AWARDED_META_PREFIX = '_jankx_credit_reward_order_';

    const HOOK_PRIORITY = 30;

    /**
     * @var OrderRewardIntegration|null
     */
    protected static $instance;

    protected ?CreditAccount $account = null;

    public function __construct(?CreditAccount $account = null)
    {
        $this->account = $account;
    }

    public static function get_instance(?CreditAccount $account = null): self
    {
        if (!self::$instance) {
            self::$instance = new self($account);
        } elseif ($account !== null) {
            self::$instance->account = $account;
        }

        return self::$instance;
    }

    protected function account(): CreditAccount
    {
        if ($this->account === null) {
            $this->account = CreditManager::instance()->account();
        }

        return $this->account;
    }

    public function register(): void
    {
        add_action('jankx/ecommerce/checkout/completed', [$this, 'onCheckoutCompleted'], self::HOOK_PRIORITY);
    }

    /* ---------------------------------------------------------------------
     * Options
     * ------------------------------------------------------------------- */

    public function getOption(string $key, $default = null)
    {
        if (class_exists('\Jankx\Adapter\Options\Helper')) {
            try {
                $value = \Jankx\Adapter\Options\Helper::getOption($key, null);
                if ($value !== null) {
                    return $value;
                }
            } catch (\Throwable $e) {
                // Fall back to the plain option below.
            }
        }

        return get_option($key, $default);
    }

    public function isEnabled(): bool
    {
        $value = $this->getOption(self::OPTION_ENABLED, 'no');

        if (is_string($value)) {
            return !in_array(strtolower(trim($value)), ['', '0', 'off', 'no', 'false'], true);
        }

        return (bool) $value;
    }

    /**
     * Order value (in VND) needed to earn a single credit.
     */
    public function getAmountPerCredit(): float
    {
        return (float) max(0, (float) $this->getOption(self::OPTION_AMOUNT_PER_CREDIT, 10000));
    }

    /**
     * Minimum order value required before any reward is granted.
     */
    public function getMinOrderTotal(): float
    {
        return (float) max(0, (float) $this->getOption(self::OPTION_MIN_ORDER_TOTAL, 0));
    }

    /**
     * Wallet (credit type id) the reward is credited to.
     * Empty string means the default wallet.
     */
    public function getWalletId(): string
    {
        return trim((string) $this->getOption(self::OPTION_WALLET, ''));
    }

    /* ---------------------------------------------------------------------
     * Calculation
     * ------------------------------------------------------------------- */

    /**
     * Number of credits earned for a given order total.
     * Rounded down; override via the jankx/user-credits/reward_calculate filter.
     */
    public function calculateCredits(float $orderTotal, int $userId = 0): float
    {
        $perCredit = $this->getAmountPerCredit();
        $credits = $perCredit > 0 ? (int) floor($orderTotal / $perCredit) : 0;

        return (float) max(0, apply_filters('jankx/user-credits/reward_calculate', $credits, $orderTotal, $userId));
    }

    /* ---------------------------------------------------------------------
     * Checkout completion
     * ------------------------------------------------------------------- */

    /**
     * @param mixed $order
     */
    public function onCheckoutCompleted($order): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        if (!is_object($order) || !method_exists($order, 'getCustomerId')) {
            return;
        }

        $userId = (int) $order->getCustomerId();
        if (!$userId) {
            $userId = (int) get_current_user_id();
        }
        if (!$userId) {
            return;
        }

        $orderId = method_exists($order, 'getId') ? (int) $order->getId() : 0;
        if (!$orderId) {
            return;
        }

        $total = method_exists($order, 'getTotal') ? (float) $order->getTotal() : 0.0;
        if ($total <= 0 || $total < $this->getMinOrderTotal()) {
            return;
        }

        $credits = $this->calculateCredits($total, $userId);
        if ($credits <= 0) {
            return;
        }

        if ($this->alreadyAwarded($userId, $orderId)) {
            return;
        }

        $orderNumber = method_exists($order, 'getOrderNumber') ? (string) $order->getOrderNumber() : '';

        $note = $orderNumber ? sprintf(__('Đơn hàng %s', 'jankx'), $orderNumber) : '';
        $title = $orderNumber
            ? sprintf(__('Thưởng xu đơn hàng %s', 'jankx'), $orderNumber)
            : __('Thưởng xu khi hoàn thành đơn hàng', 'jankx');

        try {
            $transaction = $this->account()->transact(
                $userId,
                CreditTransactionAction::REWARD,
                $credits,
                $note,
                $this->getWalletId() !== '' ? $this->getWalletId() : null,
                $title
            );
        } catch (\Jankx\Extensions\UserCredits\CreditType\CreditTypeNotFoundException $exception) {
            return;
        } catch (\InvalidArgumentException $exception) {
            return;
        }

        update_user_meta($userId, self::AWARDED_META_PREFIX . $orderId, $credits);

        do_action('jankx/user-credits/reward_awarded', $order, $credits, $userId, $transaction->getBalanceAfter());
    }

    /**
     * Whether the reward for a given order has already been granted.
     */
    public function alreadyAwarded(int $userId, int $orderId): bool
    {
        return $userId > 0 && $orderId > 0 && (float) get_user_meta($userId, self::AWARDED_META_PREFIX . $orderId, true) > 0;
    }
}