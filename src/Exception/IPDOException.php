<?php

declare(strict_types=1);

namespace Inilim\IPDO\Exception;

class IPDOException extends \Exception
{
    /**
     * @var mixed[]
     */
    protected $errorInfo = [];

    function __construct($errorInfo, int $code = 0, ?\Throwable $previous = null)
    {
        $this->errorInfo = (\is_array($errorInfo) ? $errorInfo : [$errorInfo]) + ['message' => ''];
        parent::__construct(\strval($this->errorInfo['message']), $code, $previous);
    }

    /**
     * @return mixed[]
     */
    public function getError()
    {
        return $this->errorInfo;
    }
}
