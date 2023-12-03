<?php

namespace Inilim\IPDO\Exception;

use \Exception;

class IPDOException extends Exception
{
   protected array $errors;

   public function __construct(array|string $any)
   {
      $this->errors = is_array($any) ? $any : [$any];
   }

   public function getErrors(): array
   {
      return $this->errors;
   }
}
