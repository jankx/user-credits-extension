<?php
namespace Jankx\Extensions\UserCredits;

use Jankx\Extensions\AbstractExtension;
use Jankx\Extensions\UserCredits\PostTypes\CreditTransactionPostType;
use Jankx\Extensions\UserCredits\Meta\UserCreditMetaBoxes;
use Jankx\Extensions\UserCredits\Rest\CreditApiController;
use Jankx\Extensions\UserCredits\Admin\SettingsPage;
use Jankx\Extensions\UserCredits\Admin\ThemeOptionsIntegration;
use Jankx\Extensions\UserCredits\Integration\CheckoutIntegration;

class UserCreditsExtension extends AbstractExtension
{
    protected static $instance;

    public function __construct()
    {
        $this->register_autoloader();
        parent::__construct();
    }

    protected function register_autoloader()
    {
        spl_autoload_register(function ($class) {
            $prefix = 'Jankx\\Extensions\\UserCredits\\';
            $base_dir = __DIR__ . '/src/';

            $len = strlen($prefix);
            if (strncmp($prefix, $class, $len) !== 0) {
                return;
            }

            $relative_class = substr($class, $len);
            $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

            if (file_exists($file)) {
                require $file;
            }
        });
    }

    public function init(): void
    {
        self::$instance = $this;
    }

    public static function get_instance(): ?self
    {
        return self::$instance;
    }

    public function register_hooks(): void
    {
        $postTypes = new CreditTransactionPostType();
        $postTypes->register();

        $meta = new UserCreditMetaBoxes();
        $meta->register();

        $rest = new CreditApiController();
        $rest->init();

        // Allow paying for base-ecommerce orders with credits.
        CheckoutIntegration::get_instance()->register();

        // Inject the credits page into the Jankx theme options.
        (new ThemeOptionsIntegration())->register();

        // Register sub-page with My Account
        add_action('jankx/my_account/register_sub_pages', [$this, 'registerAccountSubPage']);

        // Frontend assets for the cart & checkout credit payment UI.
        add_action('wp_enqueue_scripts', [$this, 'enqueuePaymentAssets']);

        // Always register blocks so ServerSideRender works in editor
        $this->registerBlocks();

        if (is_admin()) {
            $settingsPage = new SettingsPage();
            $settingsPage->register();
        } else {
            add_action('template_redirect', [$this, 'maybeRegisterFrontendBlocks']);
        }
    }

    /**
     * Cart & checkout assets for the credit payment toggle.
     */
    public function enqueuePaymentAssets(): void
    {
        if (!is_user_logged_in() || !class_exists('\Jankx\Extensions\Ecommerce\EcommerceExtension')) {
            return;
        }

        $integration = CheckoutIntegration::get_instance();
        if (!$integration->isEnabled() || $integration->getBalance() <= 0) {
            return;
        }

        $cartPageId = \Jankx\Extensions\Ecommerce\EcommerceExtension::get_cart_page_id();
        $checkoutPageId = \Jankx\Extensions\Ecommerce\EcommerceExtension::get_checkout_page_id();

        $isCart = $cartPageId && is_page($cartPageId);
        $isCheckout = $checkoutPageId && is_page($checkoutPageId);

        if (!$isCart && !$isCheckout) {
            return;
        }

        $url = $this->get_extension_url();
        $path = $this->get_extension_path();

        wp_enqueue_style(
            'jankx-credits-payment',
            $url . '/assets/credits-payment.css',
            [],
            filemtime($path . '/assets/credits-payment.css')
        );

        wp_enqueue_script(
            'jankx-credits-payment',
            $url . '/assets/credits-payment.js',
            [],
            filemtime($path . '/assets/credits-payment.js'),
            true
        );

        wp_localize_script('jankx-credits-payment', 'jankxCreditsPayment', [
            'restUrl' => esc_url_raw(rest_url(CreditApiController::NAMESPACE)),
            'nonce'   => wp_create_nonce('wp_rest'),
            'i18n'    => [
                'error' => __('Đã xảy ra lỗi, vui lòng thử lại.', 'jankx'),
            ],
        ]);
    }

    /**
     * Register Gutenberg blocks for this extension
     */
    public function registerBlocks(): void
    {
        $blocksDir = __DIR__ . '/blocks';
        if (!is_dir($blocksDir)) {
            return;
        }

        $blockPath = $blocksDir;
        if (!file_exists($blockPath . '/block.json')) {
            return;
        }

        $block = new \Jankx\Extensions\UserCredits\Blocks\AccountTabCreditsBlock($blockPath);
        $block->setBlockPath($blockPath);
        $block->boot();
        $block->register();
    }

    /**
     * Check if current page is My Account page and register blocks if so
     */
    public function maybeRegisterFrontendBlocks(): void
    {
        if (!$this->isMyAccountPage()) {
            return;
        }

        $this->registerBlocks();
    }

    /**
     * Check if current page is My Account page or a sub-page
     */
    protected function isMyAccountPage(): bool
    {
        $pageId = get_option('jankx_my_account_page_id', 0);
        if (!$pageId) {
            return false;
        }

        if (is_page($pageId)) {
            return true;
        }

        $subPage = get_query_var('jankx_account_page');
        if (!empty($subPage)) {
            return true;
        }

        global $post;
        if ($post && has_shortcode($post->post_content, 'jankx_my_account')) {
            return true;
        }

        return false;
    }

    /**
     * Register credits sub-page with My Account
     */
    public function registerAccountSubPage(): void
    {
        \Jankx\Extensions\MyAccount\MyAccountExtension::registerSubPage('credits', [
            'label' => 'Credits',
            'icon' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8"/><path d="M12 18V6"/></svg>',
            'priority' => 30,
            'extension' => 'user-credits',
            'show_in_nav' => true,
        ]);
    }
}
