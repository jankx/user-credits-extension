<?php

namespace Jankx\Extensions\UserCredits\CreditType;

interface CreditTypeRegistryInterface
{
    public function register(CreditType $type): void;

    public function registerMany(iterable $types): void;

    public function has(string $id): bool;

    public function get(string $id): CreditType;

    public function getDefault(): CreditType;

    public function setDefault(string $id): void;

    /**
     * @return CreditType[]
     */
    public function all(): array;

    /**
     * @return string[]
     */
    public function ids(): array;
}
