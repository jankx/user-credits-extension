<?php
namespace Jankx\Extensions\UserCredits\Meta;

class UserCreditMetaBoxes
{
    const BALANCE_META_KEY = 'user_credits_balance';

    public function register(): void
    {
        add_action('show_user_profile', [$this, 'renderProfileMetaBox']);
        add_action('edit_user_profile', [$this, 'renderProfileMetaBox']);
        add_action('personal_options_update', [$this, 'saveProfileMetaBox']);
        add_action('edit_user_profile_update', [$this, 'saveProfileMetaBox']);
    }

    public function renderProfileMetaBox(\WP_User $user): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $balance = get_user_meta($user->ID, self::BALANCE_META_KEY, true);
        if (!is_numeric($balance)) {
            $balance = 0;
        }

        $currency = get_option('jankx_credit_currency_symbol', 'đ');
        ?>
        <h2><?php esc_html_e('Tín dụng người dùng', 'jankx'); ?></h2>
        <table class="form-table" role="presentation">
            <tr>
                <th>
                    <label for="user_credits_balance"><?php esc_html_e('Số dư hiện tại', 'jankx'); ?></label>
                </th>
                <td>
                    <input type="number"
                           id="user_credits_balance"
                           name="user_credits_balance"
                           value="<?php echo esc_attr($balance); ?>"
                           class="regular-text"
                           step="1000"
                           min="0">
                    <span class="description">
                        <?php
                        printf(
                            /* translators: %s: currency symbol */
                            esc_html__('Đơn vị: %s', 'jankx'),
                            esc_html($currency)
                        );
                        ?>
                    </span>
                </td>
            </tr>
        </table>

        <h3><?php esc_html_e('Lịch sử giao dịch gần đây', 'jankx'); ?></h3>
        <?php
        $transactions = $this->getUserTransactions($user->ID);
        if (empty($transactions)) {
            echo '<p>' . esc_html__('Chưa có giao dịch nào.', 'jankx') . '</p>';
            return;
        }
        ?>
        <table class="wp-list-table widefat fixed striped" style="max-width: 700px;">
            <thead>
                <tr>
                    <th style="width: 25%;"><?php esc_html_e('Thời gian', 'jankx'); ?></th>
                    <th style="width: 25%;"><?php esc_html_e('Loại', 'jankx'); ?></th>
                    <th style="width: 25%;"><?php esc_html_e('Số tiền', 'jankx'); ?></th>
                    <th style="width: 25%;"><?php esc_html_e('Số dư', 'jankx'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($transactions as $transaction) :
                    $type = get_post_meta($transaction->ID, '_credit_type', true);
                    $amount = get_post_meta($transaction->ID, '_credit_amount', true);
                    $balanceAfter = get_post_meta($transaction->ID, '_credit_balance_after', true);
                    $types = [
                        'topup'     => __('Nạp tiền', 'jankx'),
                        'deduct'    => __('Trừ tiền', 'jankx'),
                        'refund'    => __('Hoàn tiền', 'jankx'),
                        'booking'   => __('Thanh toán booking', 'jankx'),
                        'commission' => __('Hoa hồng', 'jankx'),
                    ];
                    $prefix = in_array($type, ['topup', 'refund', 'commission']) ? '+' : '-';
                ?>
                    <tr>
                        <td><?php echo esc_html(date('d/m/Y H:i', strtotime($transaction->post_date))); ?></td>
                        <td><?php echo esc_html($types[$type] ?? $type); ?></td>
                        <td>
                            <span class="jankx-credit-<?php echo esc_attr($type); ?>">
                                <?php
                                printf(
                                    '%s%s',
                                    esc_html($prefix),
                                    esc_html(number_format((float) $amount, 0, ',', '.'))
                                );
                                ?>
                            </span>
                        </td>
                        <td><?php echo esc_html(number_format((float) $balanceAfter, 0, ',', '.')); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    public function saveProfileMetaBox(int $userId): void
    {
        if (!isset($_POST['user_credits_balance_nonce'])) {
            return;
        }

        if (!wp_verify_nonce($_POST['user_credits_balance_nonce'], 'save_user_credits_balance_' . $userId)) {
            return;
        }

        if (!current_user_can('edit_user', $userId)) {
            return;
        }

        $newBalance = isset($_POST['user_credits_balance'])
            ? sanitize_text_field($_POST['user_credits_balance'])
            : 0;

        update_user_meta($userId, self::BALANCE_META_KEY, (float) $newBalance);
    }

    protected function getUserTransactions(int $userId, int $limit = 10): array
    {
        $args = [
            'post_type'      => 'jankx_credit_txn',
            'post_status'    => 'any',
            'posts_per_page' => $limit,
            'meta_query'     => [
                [
                    'key'   => '_credit_user_id',
                    'value' => $userId,
                    'compare' => '=',
                ],
            ],
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];

        return get_posts($args);
    }
}
