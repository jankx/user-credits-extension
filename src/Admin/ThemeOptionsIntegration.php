<?php
namespace Jankx\Extensions\UserCredits\Admin;

use Jankx\Extensions\UserCredits\Integration\CheckoutIntegration;
use Jankx\Dashboard\Factories\FieldFactory;
use Jankx\Dashboard\Elements\Page;
use Jankx\Dashboard\Elements\Section;
use Jankx\Adapter\Options\Framework as OptionFramework;
use Jankx\Adapter\Options\Helper;

/**
 * Injects the credits settings page into the Jankx theme options
 * (OptionFramework / dashboard-framework), mirroring the pattern used by the
 * taxonomy-featured-image extension.
 */
class ThemeOptionsIntegration
{
    const PAGE_ID = 'user_credits';

    /**
     * @var bool
     */
    protected $injected = false;

    public function register(): void
    {
        add_action('admin_menu', [$this, 'injectPage'], 1);
    }

    public function injectPage(): void
    {
        if ($this->injected) {
            return;
        }
        $this->injected = true;

        $framework = $this->getFramework();
        if (!$framework) {
            return;
        }

        foreach ($framework->pages as $existing) {
            if (($existing->getId() ?? '') === self::PAGE_ID) {
                return;
            }
        }

        $enabled = Helper::getOption(CheckoutIntegration::OPTION_PAYMENT_ENABLED, 1);
        $label = Helper::getOption(CheckoutIntegration::OPTION_PAYMENT_LABEL, '');

        $page = new Page(__('User Credits', 'jankx'), [], 'dashicons-before dashicons-money-alt');
        $page->setId(self::PAGE_ID);
        $page->setDescription(__('Use user credits as a payment method for ecommerce orders.', 'jankx'));
        $page->setPriority(47);

        $section = new Section(__('Credit Payment', 'jankx'), []);
        $section->setId(self::PAGE_ID . '_payment');

        $section->addField(FieldFactory::create(
            CheckoutIntegration::OPTION_PAYMENT_ENABLED,
            __('Enable credit payments', 'jankx'),
            'switch',
            [
                'on' => __('On', 'jankx'),
                'off' => __('Off', 'jankx'),
                'value' => $enabled,
                'default' => 1,
                'description' => __('Allow logged-in customers to pay for orders with their credit balance (1 credit = 1 unit of the default currency).', 'jankx'),
            ]
        ));

        $section->addField(FieldFactory::create(
            CheckoutIntegration::OPTION_PAYMENT_LABEL,
            __('Payment label', 'jankx'),
            'text',
            [
                'value' => $label,
                'default' => '',
                'placeholder' => __('Dùng tín dụng để thanh toán', 'jankx'),
                'description' => __('Label shown to customers in the cart and checkout.', 'jankx'),
            ]
        ));

        $page->addSection($section);
        $framework->addPage($page);
    }

    protected function getFramework()
    {
        try {
            $adapter = OptionFramework::getActiveFramework();
            if ($adapter && method_exists($adapter, 'getFramework')) {
                return $adapter->getFramework();
            }
        } catch (\Exception $e) {
        }

        return null;
    }
}
