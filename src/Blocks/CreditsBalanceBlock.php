<?php

namespace Jankx\Extensions\UserCredits\Blocks;

use Jankx\Extensions\UserCredits\Block;
use Jankx\Extensions\UserCredits\CreditType\CreditManager;

class CreditsBalanceBlock extends Block
{
    protected $blockId = 'jankx/credits-balance';

    public function render($attributes, $content = '', $block = null)
    {
        if (!is_user_logged_in()) {
            return '';
        }

        $account = CreditManager::instance()->account();
        $type = $account->resolveType();
        $user = wp_get_current_user();
        $balance = $account->getBalance($user->ID);

        $wrapperAttrs = get_block_wrapper_attributes([
            'class' => 'jankx-credits-section jankx-credits-balance',
        ]);

        $output = sprintf('<div %s>', $wrapperAttrs);
        $output .= '<h3 class="jankx-section-title">' . esc_html($type->getLabel()) . '</h3>';
        $output .= '<div class="jankx-credit-card">';
        $output .= '<div class="jankx-credit-label">' . esc_html__('Số dư hiện tại', 'jankx') . '</div>';
        $output .= '<div class="jankx-credit-amount">' . esc_html($type->format($balance)) . '</div>';
        $output .= '</div>';
        $output .= '</div>';

        return $output;
    }
}
