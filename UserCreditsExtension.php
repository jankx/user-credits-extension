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

        if (is_admin()) {
            $settingsPage = new SettingsPage();
            $settingsPage->register();
        }
    }
}
