<?php
namespace Jankx\Extensions\UserCredits\Rest;

use Jankx\Extensions\UserCredits\Meta\UserCreditMetaBoxes;
use Jankx\Extensions\UserCredits\PostTypes\CreditTransactionPostType;

class CreditApiController
{
    const NAMESPACE = 'jankx/v1';

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
                'per_page' => [
                    'required'          => false,
                    'default'           => 20,
                    'validate_callback' => function ($param) {
                        return is_numeric($param) && (int) $param > 0;
                    },
                ],
            ],
        ]);
    }

    public function checkUserPermission(): bool
    {
        return is_user_logged_in();
    }

    public function checkAdminPermission(): bool
    {
        return current_user_can('manage_options');
    }

    public function getBalance(\WP_REST_Request $request): \WP_REST_Response
    {
        $userId = get_current_user_id();
        $balance = (float) get_user_meta($userId, UserCreditMetaBoxes::BALANCE_META_KEY, true);
        $currency = get_option('jankx_credit_currency_symbol', 'đ');

        return new \WP_REST_Response([
            'success'  => true,
            'user_id'  => $userId,
            'balance'  => $balance,
            'currency' => $currency,
            'formatted' => number_format($balance, 0, ',', '.') . ' ' . $currency,
        ]);
    }

    public function addCredits(\WP_REST_Request $request): \WP_REST_Response
    {
        $userId = (int) $request->get_param('user_id');
        $amount = (float) $request->get_param('amount');
        $note = $request->get_param('note') ?: '';

        $user = get_userdata($userId);
        if (!$user) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => __('Không tìm thấy người dùng.', 'jankx'),
            ], 404);
        }

        $currentBalance = (float) get_user_meta($userId, UserCreditMetaBoxes::BALANCE_META_KEY, true);
        $newBalance = $currentBalance + $amount;

        update_user_meta($userId, UserCreditMetaBoxes::BALANCE_META_KEY, $newBalance);

        $postId = wp_insert_post([
            'post_type'    => CreditTransactionPostType::POST_TYPE,
            'post_title'   => sprintf(__('Nạp %s cho %s', 'jankx'), $note ?: number_format($amount, 0, ',', '.'), $user->display_name),
            'post_status'  => 'publish',
            'meta_input'   => [
                '_credit_type'          => 'topup',
                '_credit_amount'        => $amount,
                '_credit_balance_after' => $newBalance,
                '_credit_user_id'       => $userId,
                '_credit_note'          => $note,
            ],
        ]);

        return new \WP_REST_Response([
            'success'        => true,
            'transaction_id' => $postId,
            'balance'        => $newBalance,
            'added'          => $amount,
            'message'        => sprintf(__('Đã nạp %s tín dụng cho %s.', 'jankx'), number_format($amount, 0, ',', '.'), $user->display_name),
        ]);
    }

    public function deductCredits(\WP_REST_Request $request): \WP_REST_Response
    {
        $userId = (int) $request->get_param('user_id');
        $amount = (float) $request->get_param('amount');
        $note = $request->get_param('note') ?: '';

        $user = get_userdata($userId);
        if (!$user) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => __('Không tìm thấy người dùng.', 'jankx'),
            ], 404);
        }

        $currentBalance = (float) get_user_meta($userId, UserCreditMetaBoxes::BALANCE_META_KEY, true);
        $newBalance = $currentBalance - $amount;

        if ($newBalance < 0) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => sprintf(__('Số dư không đủ. Số dư hiện tại: %s', 'jankx'), number_format($currentBalance, 0, ',', '.')),
            ], 400);
        }

        update_user_meta($userId, UserCreditMetaBoxes::BALANCE_META_KEY, $newBalance);

        $postId = wp_insert_post([
            'post_type'    => CreditTransactionPostType::POST_TYPE,
            'post_title'   => sprintf(__('Trừ %s từ %s', 'jankx'), $note ?: number_format($amount, 0, ',', '.'), $user->display_name),
            'post_status'  => 'publish',
            'meta_input'   => [
                '_credit_type'          => 'deduct',
                '_credit_amount'        => $amount,
                '_credit_balance_after' => $newBalance,
                '_credit_user_id'       => $userId,
                '_credit_note'          => $note,
            ],
        ]);

        return new \WP_REST_Response([
            'success'        => true,
            'transaction_id' => $postId,
            'balance'        => $newBalance,
            'deducted'       => $amount,
            'message'        => sprintf(__('Đã trừ %s tín dụng từ %s.', 'jankx'), number_format($amount, 0, ',', '.'), $user->display_name),
        ]);
    }

    public function getHistory(\WP_REST_Request $request): \WP_REST_Response
    {
        $userId = $request->get_param('user_id') ? (int) $request->get_param('user_id') : get_current_user_id();
        $perPage = (int) $request->get_param('per_page');

        if (!current_user_can('manage_options') && $userId !== get_current_user_id()) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => __('Không có quyền xem lịch sử giao dịch này.', 'jankx'),
            ], 403);
        }

        $args = [
            'post_type'      => CreditTransactionPostType::POST_TYPE,
            'post_status'    => 'any',
            'posts_per_page' => $perPage,
            'meta_query'     => [
                [
                    'key'     => '_credit_user_id',
                    'value'   => $userId,
                    'compare' => '=',
                ],
            ],
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];

        $query = new \WP_Query($args);
        $transactions = [];

        foreach ($query->posts as $post) {
            $transactions[] = [
                'id'             => $post->ID,
                'title'          => $post->post_title,
                'type'           => get_post_meta($post->ID, '_credit_type', true),
                'amount'         => (float) get_post_meta($post->ID, '_credit_amount', true),
                'balance_after'  => (float) get_post_meta($post->ID, '_credit_balance_after', true),
                'note'           => get_post_meta($post->ID, '_credit_note', true),
                'date'           => $post->post_date,
            ];
        }

        return new \WP_REST_Response([
            'success'      => true,
            'transactions' => $transactions,
            'total'        => $query->found_posts,
        ]);
    }
}
