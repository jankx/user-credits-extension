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
        }
    }

    /**
     * Register credits sub-page with My Account
     */
    public function registerAccountSubPage(): void
    {
        \Jankx\Extensions\MyAccount\MyAccountExtension::registerSubPage('credits', [
            'label' => 'Xu của bạn',
            'icon' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8"/><path d="M12 18V6"/></svg>',
            'priority' => 30,
            'extension' => 'user-credits',
            'show_in_nav' => true,
        ]);
    }
}
