<?php

namespace Inilim\IPDO\Exception;

use Inilim\IPDO\Exception\IPDOException;

class FailedExecuteException extends IPDOException
{
   public function __construct(string $query, \Throwable $e, array $values)
   {
      $this->setError([
         'query'            => $query,
         'exception_object' => $e,
         'values'           => $values,
      ]);
      parent::__construct($e->getMessage());
   }
}
