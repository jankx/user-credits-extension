<?php
namespace Jankx\Extensions\UserCredits\Rest;

use InvalidArgumentException;
use Jankx\Extensions\UserCredits\Credit\CreditAccount;
use Jankx\Extensions\UserCredits\Credit\InsufficientCreditBalanceException;
use Jankx\Extensions\UserCredits\CreditType\CreditManager;
use Jankx\Extensions\UserCredits\CreditType\CreditType;
use Jankx\Extensions\UserCredits\Integration\CheckoutIntegration;

class CreditApiController
{
    const NAMESPACE = 'jankx/v1';

    protected CreditAccount $account;

    public function __construct(CreditAccount $account)
    {
        $this->account = $account;
    }

    public function init(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/credits/balance', [
            'methods'             => 'GET',
            'callback'            => [$this, 'getBalance'],
            'permission_callback' => [$this, 'checkUserPermission'],
            'args'                => [
                'type' => $this->getTypeArg(),
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/credits/add', [
            'methods'             => 'POST',
            'callback'            => [$this, 'addCredits'],
            'permission_callback' => [$this, 'checkAdminPermission'],
            'args'                => [
                'user_id' => [
                    'required'          => true,
                    'validate_callback' => function ($param) {
                        return is_numeric($param) && (int) $param > 0;
                    },
                ],
                'amount' => [
                    'required'          => true,
                    'validate_callback' => function ($param) {
                        return is_numeric($param) && (float) $param > 0;
                    },
                ],
                'type' => $this->getTypeArg(),
                'note' => [
                    'required'          => false,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/credits/deduct', [
            'methods'             => 'POST',
            'callback'            => [$this, 'deductCredits'],
            'permission_callback' => [$this, 'checkAdminPermission'],
            'args'                => [
                'user_id' => [
                    'required'          => true,
                    'validate_callback' => function ($param) {
                        return is_numeric($param) && (int) $param > 0;
                    },
                ],
                'amount' => [
                    'required'          => true,
                    'validate_callback' => function ($param) {
                        return is_numeric($param) && (float) $param > 0;
                    },
                ],
                'type' => $this->getTypeArg(),
                'note' => [
                    'required'          => false,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/credits/history', [
            'methods'             => 'GET',
            'callback'            => [$this, 'getHistory'],
            'permission_callback' => [$this, 'checkUserPermission'],
            'args'                => [
                'user_id' => [
                    'required'          => false,
                    'validate_callback' => function ($param) {
                        return is_numeric($param) && (int) $param > 0;
                    },
                ],
                'type' => $this->getTypeArg(),
                'per_page' => [
                    'required'          => false,
                    'default'           => 20,
                    'validate_callback' => function ($param) {
                        return is_numeric($param) && (int) $param > 0;
                    },
                ],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/credits/types', [
            'methods'             => 'GET',
            'callback'            => [$this, 'getTypes'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::NAMESPACE, '/credits/cart/apply', [
            'methods'             => 'POST',
            'callback'            => [$this, 'applyCreditsToCart'],
            'permission_callback' => [$this, 'checkUserPermission'],
            'args'                => [
                'use' => [
                    'required'          => false,
                    'default'           => true,
                    'sanitize_callback' => function ($param) {
                        return filter_var($param, FILTER_VALIDATE_BOOLEAN);
                    },
                ],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/credits/cart/remove', [
            'methods'             => 'POST',
            'callback'            => [$this, 'removeCreditsFromCart'],
            'permission_callback' => [$this, 'checkUserPermission'],
        ]);
    }

    protected function getTypeArg(): array
    {
        return [
            'required'          => false,
            'sanitize_callback' => 'sanitize_key',
            'validate_callback' => function ($param) {
                return $param === null || $param === ''
                    || CreditManager::instance()->registry()->has((string) $param);
            },
        ];
    }

    protected function resolveType(\WP_REST_Request $request): CreditType
    {
        $type = $request->get_param('type');

        return $this->account->resolveType(is_string($type) ? $type : null);
    }

    public function checkUserPermission(): bool
    {
        return is_user_logged_in();
    }

    public function checkAdminPermission(): bool
    {
        return current_user_can('manage_options');
    }

    public function getTypes(): \WP_REST_Response
    {
        return new \WP_REST_Response([
            'success' => true,
            'types'   => array_map(function (CreditType $type): array {
                return $type->toArray();
            }, $this->account->registry()->all()),
            'default' => $this->account->registry()->getDefault()->getId(),
        ]);
    }

    public function getBalance(\WP_REST_Request $request): \WP_REST_Response
    {
        $userId = get_current_user_id();
        $type = $this->resolveType($request);
        $balance = $this->account->getBalance($userId, $type->getId());

        return new \WP_REST_Response([
            'success'   => true,
            'user_id'   => $userId,
            'type'      => $type->getId(),
            'label'     => $type->getLabel(),
            'balance'   => $balance,
            'currency'  => $type->getSymbol(),
            'formatted' => $type->format($balance),
        ]);
    }

    public function addCredits(\WP_REST_Request $request): \WP_REST_Response
    {
        $userId = (int) $request->get_param('user_id');
        $amount = (float) $request->get_param('amount');
        $note = (string) ($request->get_param('note') ?: '');
        $type = $this->resolveType($request);

        $user = get_userdata($userId);
        if (!$user) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => __('Không tìm thấy người dùng.', 'jankx'),
            ], 404);
        }

        $title = sprintf(
            __('Nạp %s cho %s', 'jankx'),
            $note !== '' ? $note : $type->format($amount),
            $user->display_name
        );

        try {
            $transaction = $this->account->deposit($userId, $amount, $note, $type->getId(), $title);
        } catch (InvalidArgumentException $exception) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 400);
        }

        return new \WP_REST_Response([
            'success'        => true,
            'transaction_id' => $transaction->getId(),
            'type'           => $type->getId(),
            'balance'        => $transaction->getBalanceAfter(),
            'added'          => $amount,
            'message'        => sprintf(
                __('Đã nạp %s cho %s.', 'jankx'),
                $type->format($amount),
                $user->display_name
            ),
        ]);
    }

    public function deductCredits(\WP_REST_Request $request): \WP_REST_Response
    {
        $userId = (int) $request->get_param('user_id');
        $amount = (float) $request->get_param('amount');
        $note = (string) ($request->get_param('note') ?: '');
        $type = $this->resolveType($request);

        $user = get_userdata($userId);
        if (!$user) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => __('Không tìm thấy người dùng.', 'jankx'),
            ], 404);
        }

        $title = sprintf(
            __('Trừ %s từ %s', 'jankx'),
            $note !== '' ? $note : $type->format($amount),
            $user->display_name
        );

        try {
            $transaction = $this->account->withdraw($userId, $amount, $note, $type->getId(), $title);
        } catch (InsufficientCreditBalanceException $exception) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => sprintf(
                    __('Số dư không đủ. Số dư hiện tại: %s', 'jankx'),
                    $type->format($exception->getBalance())
                ),
            ], 400);
        } catch (InvalidArgumentException $exception) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 400);
        }

        return new \WP_REST_Response([
            'success'        => true,
            'transaction_id' => $transaction->getId(),
            'type'           => $type->getId(),
            'balance'        => $transaction->getBalanceAfter(),
            'deducted'       => $amount,
            'message'        => sprintf(
                __('Đã trừ %s từ %s.', 'jankx'),
                $type->format($amount),
                $user->display_name
            ),
        ]);
    }

    public function getHistory(\WP_REST_Request $request): \WP_REST_Response
    {
        $userId = $request->get_param('user_id') ? (int) $request->get_param('user_id') : get_current_user_id();
        $perPage = (int) $request->get_param('per_page');
        $typeId = $request->get_param('type') ?: null;

        if (!current_user_can('manage_options') && $userId !== get_current_user_id()) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => __('Không có quyền xem lịch sử giao dịch này.', 'jankx'),
            ], 403);
        }

        $transactions = $this->account->getTransactions($userId, $perPage, $typeId);

        return new \WP_REST_Response([
            'success'      => true,
            'type'         => $typeId,
            'transactions' => array_map(function ($transaction): array {
                return $transaction->toArray();
            }, $transactions),
            'total'        => $this->account->countTransactions($userId, $typeId),
        ]);
    }

    public function applyCreditsToCart(\WP_REST_Request $request): \WP_REST_Response
    {
        $use = $request->get_param('use');
        if (is_string($use)) {
            $use = filter_var($use, FILTER_VALIDATE_BOOLEAN);
        }
        $use = ($use === null) ? true : (bool) $use;

        $result = CheckoutIntegration::get_instance()->apply($use);

        if (empty($result['success'])) {
            return new \WP_REST_Response($result, 400);
        }

        return rest_ensure_response($this->buildCreditPaymentResponse($result));
    }

    public function removeCreditsFromCart(\WP_REST_Request $request): \WP_REST_Response
    {
        $result = CheckoutIntegration::get_instance()->removeApplied();

        return rest_ensure_response($this->buildCreditPaymentResponse($result));
    }

    protected function buildCreditPaymentResponse(array $result): array
    {
        $integration = CheckoutIntegration::get_instance();
        $type = $this->account->resolveType();

        $cartPayload = [];
        $creditDiscount = 0.0;
        $couponDiscount = 0.0;

        if (class_exists('\Jankx\Extensions\Ecommerce\Cart\Cart')) {
            $cart = \Jankx\Extensions\Ecommerce\Cart\Cart::get_instance();
            $cartPayload = method_exists($cart, 'toArray') ? $cart->toArray() : [];
            $creditDiscount = $integration->getAppliedCreditDiscount($cart);
            $couponDiscount = max(0, (float) ($cartPayload['discount'] ?? 0) - $creditDiscount);
        }

        return array_merge($result, [
            'type'                      => $type->getId(),
            'applied'                   => $integration->isApplied(),
            'balance'                   => $integration->getBalance(),
            'credit_discount'           => $creditDiscount,
            'coupon_discount'           => $couponDiscount,
            'formatted_credit_discount' => $this->formatPrice($creditDiscount),
            'formatted_coupon_discount' => $this->formatPrice($couponDiscount),
            'cart'                      => $cartPayload,
        ]);
    }

    protected function formatPrice(float $price): string
    {
        if (class_exists('\Jankx\Extensions\Ecommerce\Currency\Converters\CurrencyConverterManager')) {
            return \Jankx\Extensions\Ecommerce\Currency\Converters\CurrencyConverterManager::getInstance()
                ->formatPriceWithConversion($price);
        }

        return number_format($price, 0, ',', '.');
    }
}
