<?php

namespace Jankx\Extensions\UserCredits\CreditType;

use InvalidArgumentException;

final class CreditTypeRegistry implements CreditTypeRegistryInterface
{
    /**
     * @var array<string, CreditType>
     */
    private array $types = [];

    private ?string $defaultId = null;

    public function register(CreditType $type): void
    {
        $id = $type->getId();

        if (isset($this->types[$id])) {
            throw new CreditTypeAlreadyRegisteredException($id);
        }

        $this->types[$id] = $type;

        if ($this->defaultId === null && $id === CreditType::DEFAULT_ID) {
            $this->defaultId = $id;
        }
    }

    public function registerMany(iterable $types): void
    {
        foreach ($types as $type) {
            if (!$type instanceof CreditType) {
                throw new InvalidArgumentException('Every credit type must be an instance of CreditType.');
            }

            $this->register($type);
        }
    }

    public function has(string $id): bool
    {
        return isset($this->types[strtolower($id)]);
    }

    public function get(string $id): CreditType
    {
        $id = strtolower($id);

        if (!isset($this->types[$id])) {
            throw new CreditTypeNotFoundException($id);
        }

        return $this->types[$id];
    }

    public function getDefault(): CreditType
    {
        if ($this->defaultId !== null && isset($this->types[$this->defaultId])) {
            return $this->types[$this->defaultId];
        }

        if (isset($this->types[CreditType::DEFAULT_ID])) {
            return $this->types[CreditType::DEFAULT_ID];
        }

        foreach ($this->all() as $type) {
            return $type;
        }

        throw new CreditTypeNotFoundException('default');
    }

    public function setDefault(string $id): void
    {
        $id = strtolower($id);

        if (!isset($this->types[$id])) {
            throw new CreditTypeNotFoundException($id);
        }

        $this->defaultId = $id;
    }

    public function all(): array
    {
        $types = $this->types;

        uasort($types, function (CreditType $a, CreditType $b): int {
            if ($a->getPriority() === $b->getPriority()) {
                return strcmp($a->getId(), $b->getId());
            }

            return $a->getPriority() <=> $b->getPriority();
        });

        return array_values($types);
    }

    public function ids(): array
    {
        return array_map(function (CreditType $type): string {
            return $type->getId();
        }, $this->all());
    }
}
