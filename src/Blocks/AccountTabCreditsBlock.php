<?php
/**
 * Account Tab Credits Block
 *
 * @package Jankx\Gutenberg\Blocks
 */

namespace Jankx\Extensions\UserCredits\Blocks;

use Jankx\Extensions\UserCredits\Block;

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

        $user = wp_get_current_user();
        $balance = $this->getUserCredits($user->ID);
        $history = $this->getCreditHistory($user->ID);

        $wrapperAttrs = get_block_wrapper_attributes([
            'class' => 'jankx-tab-panel jankx-tab-credits',
        ]);

        $output = sprintf('<div %s>', $wrapperAttrs);
        $output .= '<h2 class="jankx-section-title">Your Credits</h2>';

        // Balance card
        $output .= '<div class="jankx-credit-card">';
        $output .= '<div class="jankx-credit-label">Current Balance</div>';
        $output .= '<div class="jankx-credit-amount">' . number_format((float) $balance, 0, ',', '.') . ' CREDITS</div>';
        $output .= '</div>';

        // Transaction history
        $output .= '<div class="jankx-credit-history">';
        $output .= '<h3>Transaction History</h3>';

        if (empty($history)) {
            $output .= '<p class="text-muted">No transactions yet.</p>';
        } else {
            $output .= '<table class="jankx-table">';
            $output .= '<thead><tr><th>Date</th><th>Description</th><th>Amount</th></tr></thead>';
            $output .= '<tbody>';
            foreach ($history as $item) {
                $amountClass = $item->amount > 0 ? 'text-success' : 'text-danger';
                $amountPrefix = $item->amount > 0 ? '+' : '';
                $output .= '<tr>';
                $output .= '<td>' . esc_html(date('d/m/Y H:i', strtotime($item->date))) . '</td>';
                $output .= '<td>' . esc_html($item->description) . '</td>';
                $output .= '<td class="' . $amountClass . '">' . $amountPrefix . number_format((float) $item->amount, 0, ',', '.') . ' CREDITS</td>';
                $output .= '</tr>';
            }
            $output .= '</tbody></table>';
        }

        $output .= '</div>';
        $output .= '</div>';

        return $output;
    }

    protected function getUserCredits(int $userId): float
    {
        return (float) get_user_meta($userId, 'jankx_credits', true) ?: 0;
    }

    protected function getCreditHistory(int $userId): array
    {
        return get_user_meta($userId, 'jankx_credit_history', true) ?: [];
    }
}
