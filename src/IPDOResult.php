<?php

declare(strict_types=1);

namespace Inilim\IPDO;

/**
 * @psalm-readonly
 */
final class IPDOResult
{
   protected \PDOStatement $statement;
   protected int $countTouch;
   protected int $lastInsertID;

   function __construct(
      \PDOStatement $statement,
      int $countTouch,
      int $lastInsertID
   ) {
      $this->statement    = $statement;
      $this->countTouch   = $countTouch;
      $this->lastInsertID = $lastInsertID;
   }

   function getStatement(): \PDOStatement
   {
      return $this->statement;
   }

   function getCountTouch(): int
   {
      return $this->countTouch;
   }

   function getLastInsertID(): int
   {
      return $this->lastInsertID;
   }
}
