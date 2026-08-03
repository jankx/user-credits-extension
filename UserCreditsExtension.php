<?php
namespace Jankx\Extensions\UserCredits;

use Jankx\Extensions\AbstractExtension;
use Jankx\Extensions\UserCredits\PostTypes\CreditTransactionPostType;
use Jankx\Extensions\UserCredits\Meta\UserCreditMetaBoxes;
use Jankx\Extensions\UserCredits\Rest\CreditApiController;
use Jankx\Extensions\UserCredits\Admin\SettingsPage;

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

        // Register sub-page with My Account
        add_action('jankx/my_account/register_sub_pages', [$this, 'registerAccountSubPage']);

        if (is_admin()) {
            $settingsPage = new SettingsPage();
            $settingsPage->register();

            // Register Gutenberg blocks
            add_action('init', [$this, 'registerBlocks']);
        } else {
            // Frontend: only register blocks on My Account page
            add_action('template_redirect', [$this, 'maybeRegisterFrontendBlocks']);
        }
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

        $blockPath = $blocksDir . '/account-tab-credits';
        if (!is_dir($blockPath)) {
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
