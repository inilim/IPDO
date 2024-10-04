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

   public function __construct(
      \PDOStatement $statement,
      int $countTouch,
      int $lastInsertID
   ) {
      $this->statement    = $statement;
      $this->countTouch   = $countTouch;
      $this->lastInsertID = $lastInsertID;
   }

   public function getStatement(): \PDOStatement
   {
      return $this->statement;
   }

   public function getCountTouch(): int
   {
      return $this->countTouch;
   }

   public function getLastInsertID(): int
   {
      return $this->lastInsertID;
   }
}
