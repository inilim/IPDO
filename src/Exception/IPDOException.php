<?php

declare(strict_types=1);

namespace Inilim\IPDO\Exception;

class IPDOException extends \Exception
{
    /**
     * @var array<string|int,mixed>
     */
    protected array $errorInfo = [];

    /**
     * @param mixed $errorInfo
     */
    function __construct($errorInfo, int $code = 0, ?\Throwable $previous = null)
    {
        $this->errorInfo = (\is_array($errorInfo) ? $errorInfo : [$errorInfo]) + ['message' => ''];
        $message = $this->errorInfo['message'] ?? '';
        if (!\is_string($message)) {
            $message = (\is_scalar($message) || null === $message) ? \strval($message) : '';
        }
        parent::__construct($message, $code, $previous);
    }

    /**
     * @return array<string|int,mixed>
     */
    public function getError(): array
    {
        return $this->errorInfo;
    }
}
