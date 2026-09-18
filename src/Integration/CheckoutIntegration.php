<?php
namespace Jankx\Extensions\UserCredits\Integration;

use Jankx\Extensions\UserCredits\Credit\CreditAccount;
use Jankx\Extensions\UserCredits\Credit\InsufficientCreditBalanceException;
use Jankx\Extensions\UserCredits\CreditType\CreditManager;
use Jankx\Extensions\UserCredits\CreditType\CreditType;

/**
 * Bridges the user credits wallet into the base-ecommerce cart & checkout flow:
 *
 *  - Lets a logged-in customer redeem credits through the
 *    `jankx/ecommerce/cart/discount` filter (1 credit = 1 unit of the store's
 *    default currency; other currencies are handled by the currency converter
 *    at display time).
 *  - Deducts the redeemed credits from the customer balance and records a
 *    transaction once an order is completed.
 *
 * @package Jankx\Extensions\UserCredits
 */
class CheckoutIntegration
{
    const OPTION_PAYMENT_ENABLED = 'jankx_credit_payment_enabled';
    const OPTION_PAYMENT_LABEL   = 'jankx_credit_payment_label';

    const SESSION_PREFIX = 'jankx_credit_payment_';
    const AMOUNT_SUFFIX  = '_amount';
    const SESSION_TTL    = 30 * DAY_IN_SECONDS;

    const FILTER_PRIORITY = 20;

    /**
     * @var CheckoutIntegration|null
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

    public function getCreditType(): CreditType
    {
        return $this->account()->resolveType();
    }

    public function register(): void
    {
        add_filter('jankx/ecommerce/cart/discount', [$this, 'applyDiscount'], self::FILTER_PRIORITY, 2);
        add_action('jankx/ecommerce/checkout/completed', [$this, 'onCheckoutCompleted'], 20);
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
        $value = $this->getOption(self::OPTION_PAYMENT_ENABLED, 1);

        if (is_string($value)) {
            return !in_array(strtolower(trim($value)), ['', '0', 'off', 'no', 'false'], true);
        }

        return (bool) $value;
    }

    public function getLabel(): string
    {
        $label = trim((string) $this->getOption(self::OPTION_PAYMENT_LABEL, ''));

        return $label !== '' ? $label : __('Dùng tín dụng để thanh toán', 'jankx');
    }

    /* ---------------------------------------------------------------------
     * Applied credit (session based)
     * ------------------------------------------------------------------- */

    public function getSessionKey(): string
    {
        if (class_exists('\Jankx\Extensions\Ecommerce\Cart\Cart')) {
            return self::SESSION_PREFIX . \Jankx\Extensions\Ecommerce\Cart\Cart::get_instance()->getCartKey();
        }

        return self::SESSION_PREFIX . md5((string) get_current_user_id());
    }

    public function isApplied(): bool
    {
        return (int) get_transient($this->getSessionKey()) === 1;
    }

    /**
     * Enable or disable using credits for the current cart.
     *
     * @return array{success: bool, message: string, applied?: bool}
     */
    public function apply(bool $use): array
    {
        if (!$this->isEnabled()) {
            return [
                'success' => false,
                'message' => __('Thanh toán bằng tín dụng đang tắt.', 'jankx'),
            ];
        }

        if (!is_user_logged_in()) {
            return [
                'success' => false,
                'message' => __('Vui lòng đăng nhập để dùng tín dụng.', 'jankx'),
            ];
        }

        if (!$use) {
            return $this->removeApplied();
        }

        if ($this->getBalance() <= 0) {
            return [
                'success' => false,
                'message' => __('Số dư tín dụng của bạn không đủ.', 'jankx'),
            ];
        }

        set_transient($this->getSessionKey(), 1, self::SESSION_TTL);

        do_action('jankx/ecommerce/credits/applied', get_current_user_id());

        return [
            'success' => true,
            'message' => __('Đã áp dụng tín dụng cho đơn hàng.', 'jankx'),
            'applied' => true,
        ];
    }

    public function removeApplied(): array
    {
        $key = $this->getSessionKey();
        delete_transient($key);
        delete_transient($key . self::AMOUNT_SUFFIX);

        do_action('jankx/ecommerce/credits/removed', get_current_user_id());

        return [
            'success' => true,
            'message' => __('Đã gỡ tín dụng.', 'jankx'),
            'applied' => false,
        ];
    }

    /* ---------------------------------------------------------------------
     * Balance
     * ------------------------------------------------------------------- */

    public function getBalance(int $userId = 0): float
    {
        $userId = $userId ?: get_current_user_id();
        if (!$userId) {
            return 0.0;
        }

        return $this->account()->getBalance($userId);
    }

    /* ---------------------------------------------------------------------
     * Cart discount
     * ------------------------------------------------------------------- */

    /**
     * Filter callback: add the redeemed credits on top of the current discount.
     *
     * @param float  $discount
     * @param object $cart
     */
    public function applyDiscount(float $discount, $cart): float
    {
        $credit = $this->calculateCreditDiscount($cart, $discount);

        if ($credit > 0) {
            set_transient($this->getSessionKey() . self::AMOUNT_SUFFIX, $credit, self::SESSION_TTL);
        }

        return (float) ($discount + $credit);
    }

    /**
     * Discount contributed by credits alone.
     *
     * @param object $cart            Cart instance.
     * @param float  $currentDiscount Discount already applied by other extensions.
     */
    public function calculateCreditDiscount($cart, float $currentDiscount = 0.0): float
    {
        if (!$this->isEnabled() || !is_user_logged_in() || !$this->isApplied()) {
            return 0.0;
        }

        if (!is_object($cart) || !method_exists($cart, 'getSubtotal')) {
            return 0.0;
        }

        $balance = $this->getBalance();
        if ($balance <= 0) {
            return 0.0;
        }

        $payable = (float) $cart->getSubtotal() - $currentDiscount;
        if ($payable <= 0) {
            return 0.0;
        }

        return (float) min($balance, $payable);
    }

    /**
     * The credit-only discount for the current cart, for display purposes.
     * Computes the non-credit discount first so it is not double counted.
     */
    public function getAppliedCreditDiscount($cart): float
    {
        if (!$this->isEnabled() || !is_user_logged_in() || !$this->isApplied()) {
            return 0.0;
        }

        remove_filter('jankx/ecommerce/cart/discount', [$this, 'applyDiscount'], self::FILTER_PRIORITY);
        $otherDiscount = (is_object($cart) && method_exists($cart, 'getDiscount')) ? (float) $cart->getDiscount() : 0.0;
        add_filter('jankx/ecommerce/cart/discount', [$this, 'applyDiscount'], self::FILTER_PRIORITY, 2);

        return $this->calculateCreditDiscount($cart, $otherDiscount);
    }

    /* ---------------------------------------------------------------------
     * Checkout completion
     * ------------------------------------------------------------------- */

    /**
     * @param mixed $order
     */
    public function onCheckoutCompleted($order): void
    {
        $key = $this->getSessionKey();
        $applied = $this->isApplied();
        $amount = (float) get_transient($key . self::AMOUNT_SUFFIX);

        delete_transient($key);
        delete_transient($key . self::AMOUNT_SUFFIX);

        if (!$applied || $amount <= 0) {
            return;
        }

        $userId = 0;
        if (is_object($order) && method_exists($order, 'getCustomerId')) {
            $userId = (int) $order->getCustomerId();
        }
        if (!$userId) {
            $userId = get_current_user_id();
        }
        if (!$userId) {
            return;
        }

        $balance = $this->getBalance($userId);
        $deduct = min($amount, $balance);
        if ($deduct <= 0) {
            return;
        }

        $orderNumber = (is_object($order) && method_exists($order, 'getOrderNumber'))
            ? (string) $order->getOrderNumber()
            : '';

        $note = $orderNumber ? sprintf(__('Đơn hàng %s', 'jankx'), $orderNumber) : '';
        $title = $orderNumber
            ? sprintf(__('Thanh toán đơn hàng %s bằng tín dụng', 'jankx'), $orderNumber)
            : __('Thanh toán đơn hàng bằng tín dụng', 'jankx');

        try {
            $transaction = $this->account()->withdraw($userId, $deduct, $note, null, $title);
        } catch (InsufficientCreditBalanceException $exception) {
            return;
        }

        do_action('jankx/ecommerce/credits/used', $order, $deduct, $userId, $transaction->getBalanceAfter());
    }
}
