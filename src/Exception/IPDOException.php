<?php

namespace Inilim\IPDO\Exception;

use \Exception;

class IPDOException extends Exception
{
   /**
    * @var array
    */
   protected $errors;

   /**
    * @param mixed $any
    */
   public function setError($any)
   {
      $this->errors = \is_array($any) ? $any : [$any];
   }

   /**
    * @return array
    */
   public function getError()
   {
      return $this->errors;
   }
}
