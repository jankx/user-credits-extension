<?php

namespace Jankx\Extensions\UserCredits\Blocks;

use Jankx\Extensions\UserCredits\Block;
use Jankx\Extensions\UserCredits\CreditType\CreditManager;

class CreditsHistoryBlock extends Block
{
    protected $blockId = 'jankx/credits-history';

    public function render($attributes, $content = '', $block = null)
    {
        if (!is_user_logged_in()) {
            return '';
        }

        $account = CreditManager::instance()->account();
        $type = $account->resolveType();
        $user = wp_get_current_user();
        $history = $account->getTransactions($user->ID, 20);

        $wrapperAttrs = get_block_wrapper_attributes([
            'class' => 'jankx-credits-section jankx-credits-history',
        ]);

        $output = sprintf('<div %s>', $wrapperAttrs);
        $output .= '<h3 class="jankx-section-title">' . esc_html__('Lịch sử giao dịch', 'jankx') . '</h3>';

        if (empty($history)) {
            $output .= '<p class="text-muted">' . esc_html__('Chưa có giao dịch nào.', 'jankx') . '</p>';
        } else {
            $output .= '<table class="jankx-table">';
            $output .= '<thead><tr>';
            $output .= '<th>' . esc_html__('Ngày', 'jankx') . '</th>';
            $output .= '<th>' . esc_html__('Mô tả', 'jankx') . '</th>';
            $output .= '<th>' . esc_html__('Số tiền', 'jankx') . '</th>';
            $output .= '</tr></thead>';
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
        return $output;
    }
}
