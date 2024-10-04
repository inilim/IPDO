<?php

declare(strict_types=1);

namespace Inilim\IPDO\Exception;

class IPDOException extends \Exception
{
   /**
    * @var mixed[]
    */
   protected $error = [];

   /**
    * @param mixed $error
    * @return self
    */
   public function setError($error)
   {
      $this->error = \is_array($error) ? $error : [$error];
      return $this;
   }

   /**
    * @return mixed[]
    */
   public function getError()
   {
      return $this->error;
   }
}
