<?php

namespace Jankx\Extensions\UserCredits\MyAccount;

use Jankx\Extensions\MyAccount\SubPage\AbstractSubPage;

class CreditsSubPage extends AbstractSubPage
{
    public function getSlug(): string
    {
        return 'credits';
    }

    public function getLabel(): string
    {
        return __('Credits', 'jankx');
    }

    public function getIcon(): string
    {
        return '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8"/><path d="M12 18V6"/></svg>';
    }

    public function getPriority(): int
    {
        return 30;
    }

    public function getExtension(): ?string
    {
        return 'user-credits';
    }

    public function getContent(): string
    {
        return '<!-- wp:jankx/account-tab-credits /-->';
    }
}