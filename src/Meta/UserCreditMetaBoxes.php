<?php
namespace Jankx\Extensions\UserCredits\Meta;

use Jankx\Extensions\UserCredits\Credit\CreditAccount;
use Jankx\Extensions\UserCredits\Credit\CreditTransaction;
use Jankx\Extensions\UserCredits\CreditType\CreditType;
use Jankx\Extensions\UserCredits\CreditType\CreditTypeRegistryInterface;

class UserCreditMetaBoxes
{
    const BALANCE_META_KEY = CreditType::LEGACY_META_KEY;

    protected CreditAccount $account;

    protected CreditTypeRegistryInterface $registry;

    public function __construct(CreditAccount $account, CreditTypeRegistryInterface $registry)
    {
        $this->account = $account;
        $this->registry = $registry;
    }

    public function register(): void
    {
        add_action('show_user_profile', [$this, 'renderProfileMetaBox']);
        add_action('edit_user_profile', [$this, 'renderProfileMetaBox']);
        add_action('personal_options_update', [$this, 'saveProfileMetaBox']);
        add_action('edit_user_profile_update', [$this, 'saveProfileMetaBox']);
    }

    public function renderProfileMetaBox(\WP_User $user): void
    {
        $isAdmin = current_user_can('manage_options');
        ?>
        <h2><?php esc_html_e('Tín dụng người dùng', 'jankx'); ?></h2>
        <?php wp_nonce_field('save_user_credits_balance_' . $user->ID, 'user_credits_balance_nonce'); ?>
        <table class="form-table" role="presentation">
            <?php foreach ($this->registry->all() as $type): ?>
                <?php $balance = $this->account->getBalance($user->ID, $type->getId()); ?>
                <tr>
                    <th>
                        <label for="user_credits_balance_<?php echo esc_attr($type->getId()); ?>">
                            <?php echo esc_html($type->getLabel()); ?>
                        </label>
                    </th>
                    <td>
                        <?php if ($isAdmin): ?>
                            <input type="number"
                                   id="user_credits_balance_<?php echo esc_attr($type->getId()); ?>"
                                   name="user_credits_balance[<?php echo esc_attr($type->getId()); ?>]"
                                   value="<?php echo esc_attr($balance); ?>"
                                   class="regular-text"
                                   step="1"
                                   min="0">
                            <span class="description">
                                <?php
                                printf(
                                    /* translators: %s: credit unit symbol */
                                    esc_html__('Đơn vị: %s', 'jankx'),
                                    esc_html($type->getSymbol())
                                );
                                ?>
                            </span>
                        <?php else: ?>
                            <strong><?php echo esc_html($type->format($balance)); ?></strong>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>

        <h3><?php esc_html_e('Lịch sử giao dịch gần đây', 'jankx'); ?></h3>
        <?php
        $transactions = $this->account->getTransactions($user->ID, 10);
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
                <?php foreach ($transactions as $transaction): ?>
                    <tr>
                        <td><?php echo esc_html(date('d/m/Y H:i', strtotime($transaction->getDate()))); ?></td>
                        <td><?php echo esc_html($this->describe($transaction)); ?></td>
                        <td>
                            <span class="jankx-credit-<?php echo esc_attr($transaction->getAction()); ?>">
                                <?php
                                printf(
                                    '%s%s',
                                    esc_html($transaction->getSignedAmount() >= 0 ? '+' : '-'),
                                    esc_html($this->formatAmount($transaction))
                                );
                                ?>
                            </span>
                        </td>
                        <td><?php echo esc_html($this->formatBalance($transaction)); ?></td>
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

        if (!current_user_can('manage_options')) {
            return;
        }

        $posted = isset($_POST['user_credits_balance']) && is_array($_POST['user_credits_balance'])
            ? wp_unslash($_POST['user_credits_balance'])
            : [];

        foreach ($this->registry->all() as $type) {
            if (!array_key_exists($type->getId(), $posted)) {
                continue;
            }

            $value = sanitize_text_field((string) $posted[$type->getId()]);

            $this->account->setBalance($userId, is_numeric($value) ? (float) $value : 0.0, $type->getId());
        }
    }

    protected function resolveType(string $typeId): ?CreditType
    {
        return $this->registry->has($typeId) ? $this->registry->get($typeId) : null;
    }

    protected function describe(CreditTransaction $transaction): string
    {
        $type = $this->resolveType($transaction->getWalletId());

        return $type
            ? sprintf('%s (%s)', $transaction->getActionLabel(), $type->getLabel())
            : $transaction->getActionLabel();
    }

    protected function formatAmount(CreditTransaction $transaction): string
    {
        $type = $this->resolveType($transaction->getWalletId());

        return $type ? $type->format($transaction->getAmount()) : number_format($transaction->getAmount(), 0, ',', '.');
    }

    protected function formatBalance(CreditTransaction $transaction): string
    {
        $type = $this->resolveType($transaction->getWalletId());

        return $type
            ? $type->format($transaction->getBalanceAfter())
            : number_format($transaction->getBalanceAfter(), 0, ',', '.');
    }
}
