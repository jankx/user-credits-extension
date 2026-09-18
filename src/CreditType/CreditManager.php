<?php

namespace Jankx\Extensions\UserCredits\CreditType;

use Jankx\Extensions\UserCredits\Credit\CreditAccount;
use Jankx\Extensions\UserCredits\Credit\PostTypeCreditTransactionRepository;
use Jankx\Extensions\UserCredits\Credit\UserMetaCreditBalanceStore;

final class CreditManager
{
    public const REGISTER_ACTION = 'jankx/user-credits/register_credit_types';

    private static ?self $instance = null;

    private CreditTypeRegistryInterface $registry;

    private CreditAccount $account;

    private bool $booted = false;

    private function __construct(?CreditTypeRegistryInterface $registry = null)
    {
        $this->registry = $registry ?? new CreditTypeRegistry();
        $this->account = new CreditAccount(
            new UserMetaCreditBalanceStore(),
            new PostTypeCreditTransactionRepository(),
            $this->registry
        );

        $this->registerBuiltinTypes();
    }

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public static function withRegistry(CreditTypeRegistryInterface $registry): self
    {
        self::$instance = new self($registry);

        return self::$instance;
    }

    public static function registerType(CreditType $type): void
    {
        self::instance()->registry()->register($type);
    }

    public function registry(): CreditTypeRegistryInterface
    {
        return $this->registry;
    }

    public function account(): CreditAccount
    {
        return $this->account;
    }

    public function defaultType(): CreditType
    {
        return $this->registry->getDefault();
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        $this->booted = true;

        if ($this->registry->has(CreditType::DEFAULT_ID)) {
            $this->registry->setDefault(CreditType::DEFAULT_ID);
        }

        do_action(self::REGISTER_ACTION, $this->registry);
    }

    private function registerBuiltinTypes(): void
    {
        if ($this->registry->has(CreditType::DEFAULT_ID)) {
            return;
        }

        $symbol = (string) get_option('jankx_credit_currency_symbol', 'coin');

        $this->registry->register(new CreditType(
            CreditType::DEFAULT_ID,
            __('Coin', 'jankx'),
            $symbol !== '' ? $symbol : 'coin',
            CreditType::LEGACY_META_KEY,
            0,
            0
        ));
    }
}
