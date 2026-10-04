<?php
namespace Jankx\Extensions\UserCredits;

use Jankx\Extensions\AbstractExtension;
use Jankx\Extensions\UserCredits\Admin\SettingsPage;
use Jankx\Extensions\UserCredits\Admin\ThemeOptionsIntegration;
use Jankx\Extensions\UserCredits\CreditType\CreditManager;
use Jankx\Extensions\UserCredits\Integration\CheckoutIntegration;
use Jankx\Extensions\UserCredits\Meta\UserCreditMetaBoxes;
use Jankx\Extensions\UserCredits\PostTypes\CreditTransactionPostType;
use Jankx\Extensions\UserCredits\Rest\CreditApiController;
use Jankx\Extensions\UserCredits\Reward\OrderRewardIntegration;

class UserCreditsExtension extends AbstractExtension
{
    protected static $instance;

    /**
     * Text domain owned by this extension. Every extension ships its own
     * translations - only the theme's built-in blocks share the `jankx` domain.
     */
    public const TEXT_DOMAIN = 'jankx_user_credit';

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
        // Load this extension's translations (.mo for PHP, .json for block JS).
        $this->load_textdomain();

        $manager = CreditManager::instance();

        $postTypes = new CreditTransactionPostType($manager->registry());
        $postTypes->register();

        $meta = new UserCreditMetaBoxes($manager->account(), $manager->registry());
        $meta->register();

        $rest = new CreditApiController($manager->account());
        $rest->init();

        // Allow paying for base-ecommerce orders with credits.
        CheckoutIntegration::get_instance($manager->account())->register();

        // Automatically reward credits when an order is recorded via checkout.
        OrderRewardIntegration::get_instance($manager->account())->register();

        // Inject the credits page into the Jankx theme options.
        (new ThemeOptionsIntegration())->register();

        // Let third-party extensions register their own credit types.
        if (did_action('init')) {
            $manager->boot();
        } else {
            add_action('init', [$manager, 'boot'], 99);
        }

        // Register sub-page with My Account
        add_action('jankx/my_account/register_sub_pages', [$this, 'registerAccountSubPage']);

        // Show the current balance next to the "Xu của bạn" account menu item.
        add_filter('jankx/my-account/menu-item/info', [$this, 'renderAccountMenuInfo'], 10, 3);

        // Frontend assets for the cart & checkout credit payment UI.
        add_action('wp_enqueue_scripts', [$this, 'enqueuePaymentAssets']);

        // Always register blocks so ServerSideRender works in editor.
        // Defer to init because register_block_type_from_metadata() calls
        // wp_script_is() which must not run before the init hook.
        add_action('init', [$this, 'registerBlocks']);

        if (is_admin()) {
            $settingsPage = new SettingsPage($manager->registry());
            $settingsPage->register();
        } else {
            add_action('template_redirect', [$this, 'maybeRegisterFrontendBlocks']);
        }
    }

    /**
     * Load the extension's own translations and register the languages
     * directory so block editor scripts can resolve their Jed JSON files.
     */
    protected function load_textdomain(): void
    {
        /** @var \WP_Textdomain_Registry $wp_textdomain_registry */
        global $wp_textdomain_registry;

        $locale = determine_locale();
        $dir    = __DIR__ . '/languages';

        if ($wp_textdomain_registry instanceof \WP_Textdomain_Registry) {
            $wp_textdomain_registry->set_custom_path(self::TEXT_DOMAIN, $dir);
        }

        $mo = $dir . '/' . self::TEXT_DOMAIN . '-' . $locale . '.mo';
        if (!is_readable($mo)) {
            return;
        }

        $loader = static function () use ($mo) {
            load_textdomain(self::TEXT_DOMAIN, $mo);
        };

        if (did_action('after_setup_theme')) {
            $loader();
        } else {
            add_action('after_setup_theme', $loader, 5);
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
            'restUrl' => esc_url_raw(rest_url(CreditApiController::NAMESPACE . '/')),
            'nonce'   => wp_create_nonce('wp_rest'),
            'i18n'    => [
                'error' => __('Đã xảy ra lỗi, vui lòng thử lại.', 'jankx_user_credit'),
                'requestFailed' => __('Yêu cầu thất bại. Vui lòng thử lại.', 'jankx_user_credit'),
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
        if (file_exists($blockPath . '/block.json')) {
            $block = new \Jankx\Extensions\UserCredits\Blocks\AccountTabCreditsBlock($blockPath);
            $block->setBlockPath($blockPath);
            $block->boot();
            $block->register();
        }

        $childBlocks = [
            'credits-balance' => \Jankx\Extensions\UserCredits\Blocks\CreditsBalanceBlock::class,
            'credits-history' => \Jankx\Extensions\UserCredits\Blocks\CreditsHistoryBlock::class,
        ];

        foreach ($childBlocks as $dirName => $blockClass) {
            $childPath = $blocksDir . '/' . $dirName;
            if (!file_exists($childPath . '/block.json')) {
                continue;
            }
            $block = new $blockClass($childPath);
            $block->setBlockPath($childPath);
            $block->boot();
            $block->register();
        }
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
        \Jankx\Extensions\MyAccount\MyAccountExtension::registerSubPageClass(new \Jankx\Extensions\UserCredits\MyAccount\CreditsSubPage());
    }

    /**
     * Show the current credits balance on the right side of the credits
     * account menu item (e.g. "10.000 XU").
     *
     * @param string $info
     * @param string $slug
     * @param array  $attributes
     */
    public function renderAccountMenuInfo(string $info, string $slug, array $attributes): string
    {
        if ($slug !== 'credits' || !is_user_logged_in()) {
            return $info;
        }

        $account = CreditManager::instance()->account();
        $type = $account->resolveType();
        $balance = $account->getBalance(get_current_user_id());

        return esc_html($type->format($balance));
    }
}
