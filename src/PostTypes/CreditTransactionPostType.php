<?php
namespace Jankx\Extensions\UserCredits\PostTypes;

class CreditTransactionPostType
{
    const POST_TYPE = 'jankx_credit_transaction';

    public function register(): void
    {
        add_action('init', [$this, 'register_post_type']);
        add_filter('manage_' . self::POST_TYPE . '_posts_columns', [$this, 'setColumns']);
        add_action('manage_' . self::POST_TYPE . '_posts_custom_column', [$this, 'renderColumn'], 10, 2);
    }

    public function register_post_type(): void
    {
        $labels = [
            'name'                  => __('Giao dịch', 'jankx'),
            'singular_name'         => __('Giao dịch', 'jankx'),
            'menu_name'             => __('Giao dịch tín dụng', 'jankx'),
            'add_new'               => __('Thêm mới', 'jankx'),
            'add_new_item'          => __('Thêm giao dịch mới', 'jankx'),
            'edit_item'             => __('Chỉnh sửa giao dịch', 'jankx'),
            'new_item'              => __('Giao dịch mới', 'jankx'),
            'view_item'             => __('Xem giao dịch', 'jankx'),
            'search_items'          => __('Tìm giao dịch', 'jankx'),
            'not_found'             => __('Không tìm thấy giao dịch', 'jankx'),
            'not_found_in_trash'    => __('Không có giao dịch nào trong thùng rác', 'jankx'),
            'all_items'             => __('Tất cả giao dịch', 'jankx'),
        ];

        register_post_type(self::POST_TYPE, [
            'labels'            => $labels,
            'public'            => false,
            'show_ui'           => true,
            'show_in_menu'      => true,
            'show_in_rest'      => true,
            'menu_icon'         => 'dashicons-money-alt',
            'supports'          => ['title', 'editor', 'custom-fields'],
            'capability_type'   => 'post',
            'map_meta_cap'      => true,
            'capabilities'      => [
                'create_posts' => 'do_not_allow',
            ],
        ]);
    }

    public function setColumns(array $columns): array
    {
        $newColumns = [];
        $newColumns['cb'] = $columns['cb'];
        $newColumns['title'] = $columns['title'];
        $newColumns['transaction_type'] = __('Loại giao dịch', 'jankx');
        $newColumns['amount'] = __('Số tiền', 'jankx');
        $newColumns['balance_after'] = __('Số dư sau', 'jankx');
        $newColumns['user'] = __('Người dùng', 'jankx');
        $newColumns['date'] = $columns['date'];

        return $newColumns;
    }

    public function renderColumn(string $column, int $postId): void
    {
        switch ($column) {
            case 'transaction_type':
                $type = get_post_meta($postId, '_credit_type', true);
                $types = [
                    'topup'     => __('Nạp tiền', 'jankx'),
                    'deduct'    => __('Trừ tiền', 'jankx'),
                    'refund'    => __('Hoàn tiền', 'jankx'),
                    'booking'   => __('Thanh toán booking', 'jankx'),
                    'commission' => __('Hoa hồng', 'jankx'),
                ];
                echo esc_html($types[$type] ?? $type);
                break;

            case 'amount':
                $amount = get_post_meta($postId, '_credit_amount', true);
                $type = get_post_meta($postId, '_credit_type', true);
                $prefix = in_array($type, ['topup', 'refund', 'commission']) ? '+' : '-';
                printf(
                    '<span class="jankx-credit-amount jankx-credit-%s">%s%s</span>',
                    esc_attr($type),
                    esc_html($prefix),
                    esc_html(number_format((float) $amount, 0, ',', '.'))
                );
                break;

            case 'balance_after':
                $balance = get_post_meta($postId, '_credit_balance_after', true);
                echo esc_html(number_format((float) $balance, 0, ',', '.'));
                break;

            case 'user':
                $userId = get_post_meta($postId, '_credit_user_id', true);
                if ($userId) {
                    $user = get_userdata((int) $userId);
                    if ($user) {
                        printf(
                            '<a href="%s">%s</a>',
                            esc_url(get_edit_user_link($user->ID)),
                            esc_html($user->display_name)
                        );
                    } else {
                        echo esc_html__('Không xác định', 'jankx');
                    }
                }
                break;
        }
    }
}
