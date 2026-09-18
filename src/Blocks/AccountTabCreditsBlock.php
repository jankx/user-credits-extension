<?php
/**
 * Account Tab Credits Block
 *
 * @package Jankx\Gutenberg\Blocks
 */

namespace Jankx\Extensions\UserCredits\Blocks;

use Jankx\Extensions\UserCredits\Block;
use Jankx\Extensions\UserCredits\CreditType\CreditManager;

class AccountTabCreditsBlock extends Block
{
    protected $blockId = 'jankx/account-tab-credits';

    public function render($attributes, $content = '', $block = null)
    {
        if (!is_user_logged_in()) {
            return '';
        }

        $activeTab = get_query_var('jankx_account_page');
        if (empty($activeTab)) {
            $activeTab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'profile';
        }

        $is_editor = defined('REST_REQUEST') && REST_REQUEST && !empty($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/block-renderer/') !== false;

        if (!$is_editor && $activeTab !== 'credits') {
            return '';
        }

        $account = CreditManager::instance()->account();
        $type = $account->resolveType();
        $user = wp_get_current_user();
        $balance = $account->getBalance($user->ID);
        $history = $account->getTransactions($user->ID, 20);

        $wrapperAttrs = get_block_wrapper_attributes([
            'class' => 'jankx-tab-panel jankx-tab-credits',
        ]);

        $output = sprintf('<div %s>', $wrapperAttrs);
        $output .= '<h2 class="jankx-section-title">' . esc_html($type->getLabel()) . '</h2>';

        $output .= '<div class="jankx-credit-card">';
        $output .= '<div class="jankx-credit-label">' . esc_html__('Số dư hiện tại', 'jankx') . '</div>';
        $output .= '<div class="jankx-credit-amount">' . esc_html($type->format($balance)) . '</div>';
        $output .= '</div>';

        $output .= '<div class="jankx-credit-history">';
        $output .= '<h3>' . esc_html__('Lịch sử giao dịch', 'jankx') . '</h3>';

        if (empty($history)) {
            $output .= '<p class="text-muted">' . esc_html__('Chưa có giao dịch nào.', 'jankx') . '</p>';
        } else {
            $output .= '<table class="jankx-table">';
            $output .= '<thead><tr><th>' . esc_html__('Ngày', 'jankx') . '</th><th>' . esc_html__('Mô tả', 'jankx') . '</th><th>' . esc_html__('Số tiền', 'jankx') . '</th></tr></thead>';
            $output .= '<tbody>';
            foreach ($history as $item) {
                $signed = $item->getSignedAmount();
                $amountClass = $signed >= 0 ? 'text-success' : 'text-danger';
                $amountPrefix = $signed >= 0 ? '+' : '-';
                $description = $item->getNote() !== '' ? $item->getNote() : ($item->getTitle() !== '' ? $item->getTitle() : $item->getActionLabel());
                $output .= '<tr>';
                $output .= '<td>' . esc_html(date('d/m/Y H:i', strtotime($item->getDate()))) . '</td>';
                $output .= '<td>' . esc_html($description) . '</td>';
                $output .= '<td class="' . esc_attr($amountClass) . '">' . esc_html($amountPrefix . $type->format($item->getAmount())) . '</td>';
                $output .= '</tr>';
            }
            $output .= '</tbody></table>';
        }

        $output .= '</div>';
        $output .= '</div>';

        return $output;
    }
}
