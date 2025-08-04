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
   protected ?string $rawLastInsertID;

   function __construct(
      \PDOStatement $statement,
      int $countTouch,
      int $lastInsertID,
      ?string $rawLastInsertID
   ) {
      $this->statement       = $statement;
      $this->countTouch      = $countTouch;
      $this->lastInsertID    = $lastInsertID;
      $this->rawLastInsertID = $rawLastInsertID;
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

   function getRawLastInsertID(): ?string
   {
      return $this->rawLastInsertID;
   }
}
