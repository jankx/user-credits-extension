<?php
namespace Jankx\Extensions\UserCredits\Admin;

use Jankx\Extensions\UserCredits\CreditType\CreditManager;
use Jankx\Extensions\UserCredits\CreditType\CreditType;
use Jankx\Extensions\UserCredits\CreditType\CreditTypeRegistryInterface;
use Jankx\Extensions\UserCredits\Reward\OrderRewardIntegration;

class SettingsPage
{
    const PAGE_SLUG = 'jankx-credit-settings';
    const OPTION_GROUP = 'jankx_credit_settings';

    protected CreditTypeRegistryInterface $registry;

    public function __construct(?CreditTypeRegistryInterface $registry = null)
    {
        $this->registry = $registry ?? CreditManager::instance()->registry();
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'addMenu'], 25);
        add_action('admin_init', [$this, 'registerSettings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    public function addMenu(): void
    {
        add_submenu_page(
            'jankx-theme-options',
            __('Credit Settings', 'jankx'),
            __('Cài đặt tín dụng', 'jankx'),
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'renderPage']
        );
    }

    public function registerSettings(): void
    {
        register_setting(self::OPTION_GROUP, 'jankx_credit_enabled', [
            'default'           => 'yes',
            'sanitize_callback' => [$this, 'sanitizeBoolean'],
        ]);

        register_setting(self::OPTION_GROUP, 'jankx_credit_currency_symbol', [
            'default'           => 'coin',
            'sanitize_callback' => 'sanitize_text_field',
        ]);

        register_setting(self::OPTION_GROUP, 'jankx_credit_min_topup', [
            'default'           => 10000,
            'sanitize_callback' => 'absint',
        ]);

        register_setting(self::OPTION_GROUP, 'jankx_credit_max_topup', [
            'default'           => 50000000,
            'sanitize_callback' => 'absint',
        ]);

        register_setting(self::OPTION_GROUP, 'jankx_credit_expiry_days', [
            'default'           => 0,
            'sanitize_callback' => 'absint',
        ]);

        register_setting(self::OPTION_GROUP, OrderRewardIntegration::OPTION_ENABLED, [
            'default'           => 'no',
            'sanitize_callback' => [$this, 'sanitizeBoolean'],
        ]);

        register_setting(self::OPTION_GROUP, OrderRewardIntegration::OPTION_AMOUNT_PER_CREDIT, [
            'default'           => 10000,
            'sanitize_callback' => [$this, 'sanitizeNonNegativeNumber'],
        ]);

        register_setting(self::OPTION_GROUP, OrderRewardIntegration::OPTION_MIN_ORDER_TOTAL, [
            'default'           => 0,
            'sanitize_callback' => [$this, 'sanitizeNonNegativeNumber'],
        ]);
    }

    public function sanitizeBoolean($value): string
    {
        return in_array($value, ['yes', 'no'], true) ? $value : 'no';
    }

    public function sanitizeNonNegativeNumber($value): float
    {
        $number = is_numeric($value) ? (float) $value : 0.0;

        return max(0, $number);
    }

    public function enqueueAssets(string $hook): void
    {
        if (strpos($hook, self::PAGE_SLUG) === false) {
            return;
        }

        $extension = \Jankx\Extensions\UserCredits\UserCreditsExtension::get_instance();
        if ($extension) {
            wp_enqueue_style(
                'jankx-user-credits-admin',
                $extension->get_extension_url() . '/assets/admin.css',
                [],
                '1.0.0'
            );
        }
    }

    public function renderPage(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap jankx-credit-wrap">
            <h1><?php esc_html_e('Cài đặt hệ thống tín dụng', 'jankx'); ?></h1>
            <p class="description"><?php esc_html_e('Quản lý cài đặt ví tiền và tín dụng người dùng.', 'jankx'); ?></p>

            <form method="post" action="options.php" style="max-width: 700px; margin-top: 20px;">
                <?php settings_fields(self::OPTION_GROUP); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="jankx_credit_enabled"><?php esc_html_e('Bật hệ thống tín dụng', 'jankx'); ?></label>
                        </th>
                        <td>
                            <select id="jankx_credit_enabled" name="jankx_credit_enabled">
                                <option value="yes" <?php selected(get_option('jankx_credit_enabled', 'yes'), 'yes'); ?>>
                                    <?php esc_html_e('Bật', 'jankx'); ?>
                                </option>
                                <option value="no" <?php selected(get_option('jankx_credit_enabled', 'yes'), 'no'); ?>>
                                    <?php esc_html_e('Tắt', 'jankx'); ?>
                                </option>
                            </select>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="jankx_credit_currency_symbol"><?php esc_html_e('Ký hiệu tiền tệ', 'jankx'); ?></label>
                        </th>
                        <td>
                            <input type="text"
                                   id="jankx_credit_currency_symbol"
                                   name="jankx_credit_currency_symbol"
                                   value="<?php echo esc_attr(get_option('jankx_credit_currency_symbol', 'coin')); ?>"
                                   class="small-text"
                                   placeholder="coin">
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="jankx_credit_min_topup"><?php esc_html_e('Số tiền nạp tối thiểu', 'jankx'); ?></label>
                        </th>
                        <td>
                            <input type="number"
                                   id="jankx_credit_min_topup"
                                   name="jankx_credit_min_topup"
                                   value="<?php echo esc_attr(get_option('jankx_credit_min_topup', 10000)); ?>"
                                   class="regular-text"
                                   step="1000"
                                   min="0">
                            <p class="description"><?php esc_html_e('Đơn vị: VND', 'jankx'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="jankx_credit_max_topup"><?php esc_html_e('Số tiền nạp tối đa', 'jankx'); ?></label>
                        </th>
                        <td>
                            <input type="number"
                                   id="jankx_credit_max_topup"
                                   name="jankx_credit_max_topup"
                                   value="<?php echo esc_attr(get_option('jankx_credit_max_topup', 50000000)); ?>"
                                   class="regular-text"
                                   step="1000"
                                   min="0">
                            <p class="description"><?php esc_html_e('Đơn vị: VND', 'jankx'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="jankx_credit_expiry_days"><?php esc_html_e('Hạn sử dụng (ngày)', 'jankx'); ?></label>
                        </th>
                        <td>
                            <input type="number"
                                   id="jankx_credit_expiry_days"
                                   name="jankx_credit_expiry_days"
                                   value="<?php echo esc_attr(get_option('jankx_credit_expiry_days', 0)); ?>"
                                   class="small-text"
                                   min="0">
                            <p class="description"><?php esc_html_e('Để 0 nếu tín dụng không hết hạn.', 'jankx'); ?></p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(__('Lưu cài đặt', 'jankx')); ?>
            </form>

            <h2 style="margin-top: 32px;"><?php esc_html_e('Thưởng xu khi hoàn thành đơn hàng', 'jankx'); ?></h2>
            <p class="description">
                <?php esc_html_e('Hệ thống sẽ tự động thưởng xu cho khách hàng khi đơn hàng được ghi nhận qua checkout (bao gồm đơn do telesale tạo online).', 'jankx'); ?>
            </p>

            <form method="post" action="options.php" style="max-width: 700px; margin-top: 20px;">
                <?php settings_fields(self::OPTION_GROUP); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="<?php echo esc_attr(OrderRewardIntegration::OPTION_ENABLED); ?>">
                                <?php esc_html_e('Bật thưởng xu', 'jankx'); ?>
                            </label>
                        </th>
                        <td>
                            <select id="<?php echo esc_attr(OrderRewardIntegration::OPTION_ENABLED); ?>"
                                    name="<?php echo esc_attr(OrderRewardIntegration::OPTION_ENABLED); ?>">
                                <option value="yes" <?php selected(get_option(OrderRewardIntegration::OPTION_ENABLED, 'no'), 'yes'); ?>>
                                    <?php esc_html_e('Bật', 'jankx'); ?>
                                </option>
                                <option value="no" <?php selected(get_option(OrderRewardIntegration::OPTION_ENABLED, 'no'), 'no'); ?>>
                                    <?php esc_html_e('Tắt', 'jankx'); ?>
                                </option>
                            </select>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="<?php echo esc_attr(OrderRewardIntegration::OPTION_AMOUNT_PER_CREDIT); ?>">
                                <?php esc_html_e('Tỷ lệ chuyển đổi', 'jankx'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="number"
                                   id="<?php echo esc_attr(OrderRewardIntegration::OPTION_AMOUNT_PER_CREDIT); ?>"
                                   name="<?php echo esc_attr(OrderRewardIntegration::OPTION_AMOUNT_PER_CREDIT); ?>"
                                   value="<?php echo esc_attr(get_option(OrderRewardIntegration::OPTION_AMOUNT_PER_CREDIT, 10000)); ?>"
                                   class="regular-text"
                                   step="100"
                                   min="0">
                            <p class="description">
                                <?php esc_html_e('Số tiền (VND) giá trị đơn hàng tương ứng với 1 xu. VD: nhập 10.000 → mỗi 10.000đ đơn hàng khách nhận 1 xu (làm tròn xuống).', 'jankx'); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="<?php echo esc_attr(OrderRewardIntegration::OPTION_MIN_ORDER_TOTAL); ?>">
                                <?php esc_html_e('Đơn hàng tối thiểu', 'jankx'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="number"
                                   id="<?php echo esc_attr(OrderRewardIntegration::OPTION_MIN_ORDER_TOTAL); ?>"
                                   name="<?php echo esc_attr(OrderRewardIntegration::OPTION_MIN_ORDER_TOTAL); ?>"
                                   value="<?php echo esc_attr(get_option(OrderRewardIntegration::OPTION_MIN_ORDER_TOTAL, 0)); ?>"
                                   class="regular-text"
                                   step="1000"
                                   min="0">
                            <p class="description">
                                <?php esc_html_e('Tổng giá trị đơn hàng tối thiểu (VND) mới được thưởng xu. Để 0 nếu không giới hạn.', 'jankx'); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(__('Lưu cài đặt', 'jankx')); ?>
            </form>

            <h2 style="margin-top: 32px;"><?php esc_html_e('Loại tín dụng đã đăng ký', 'jankx'); ?></h2>
            <p class="description">
                <?php esc_html_e('Các loại tín dụng có thể được mở rộng bởi extension khác thông qua hook jankx/user-credits/register_credit_types.', 'jankx'); ?>
            </p>
            <table class="wp-list-table widefat fixed striped" style="max-width: 900px;">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Mã', 'jankx'); ?></th>
                        <th><?php esc_html_e('Tên', 'jankx'); ?></th>
                        <th><?php esc_html_e('Ký hiệu', 'jankx'); ?></th>
                        <th><?php esc_html_e('Meta key', 'jankx'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($this->registry->all() as $type): ?>
                        <tr>
                            <td><code><?php echo esc_html($type->getId()); ?></code></td>
                            <td>
                                <?php echo esc_html($type->getLabel()); ?>
                                <?php if ($type->is(CreditType::DEFAULT_ID)): ?>
                                    <strong>(<?php esc_html_e('mặc định', 'jankx'); ?>)</strong>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($type->getSymbol()); ?></td>
                            <td><code><?php echo esc_html($type->getMetaKey()); ?></code></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}
