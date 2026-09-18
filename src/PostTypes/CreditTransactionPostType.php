<?php
namespace Jankx\Extensions\UserCredits\PostTypes;

use Jankx\Extensions\UserCredits\Credit\CreditTransactionAction;
use Jankx\Extensions\UserCredits\Credit\PostTypeCreditTransactionRepository as Repository;
use Jankx\Extensions\UserCredits\CreditType\CreditManager;
use Jankx\Extensions\UserCredits\CreditType\CreditTypeRegistryInterface;

class CreditTransactionPostType
{
    const POST_TYPE = 'jankx_credit_txn';

    protected CreditTypeRegistryInterface $registry;

    public function __construct(?CreditTypeRegistryInterface $registry = null)
    {
        $this->registry = $registry ?? CreditManager::instance()->registry();
    }

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
        $newColumns['credit_wallet'] = __('Loại tín dụng', 'jankx');
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
                $action = (string) get_post_meta($postId, Repository::META_ACTION, true);
                echo esc_html(CreditTransactionAction::label($action));
                break;

            case 'credit_wallet':
                $walletId = (string) get_post_meta($postId, Repository::META_WALLET, true);
                if ($walletId !== '' && $this->registry->has($walletId)) {
                    echo esc_html($this->registry->get($walletId)->getLabel());
                } else {
                    echo esc_html($walletId);
                }
                break;

            case 'amount':
                $amount = (float) get_post_meta($postId, Repository::META_AMOUNT, true);
                $action = (string) get_post_meta($postId, Repository::META_ACTION, true);
                $prefix = CreditTransactionAction::isCredit($action) ? '+' : '-';
                printf(
                    '<span class="jankx-credit-amount jankx-credit-%s">%s%s</span>',
                    esc_attr($action),
                    esc_html($prefix),
                    esc_html(number_format($amount, 0, ',', '.'))
                );
                break;

            case 'balance_after':
                $balance = (float) get_post_meta($postId, Repository::META_BALANCE_AFTER, true);
                echo esc_html(number_format($balance, 0, ',', '.'));
                break;

            case 'user':
                $userId = (int) get_post_meta($postId, Repository::META_USER, true);
                if ($userId) {
                    $user = get_userdata($userId);
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
