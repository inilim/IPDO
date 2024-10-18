<?php

declare(strict_types=1);

namespace Inilim\IPDO\DTO;

/**
 * @psalm-readonly
 */
final class ByteParamDTO
{
    protected string $value;

    function __construct(string $value)
    {
        $this->value = $value;
    }

    function getValue(): string
    {
        return $this->value;
    }
}
