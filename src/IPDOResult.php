<?php

namespace Inilim\IPDO;

final readonly class IPDOResult
{
   public function __construct(
      protected \PDOStatement $statement,
      protected int $count_touch,
      protected int $last_insert_id,
   ) {}

   public function getStatement(): \PDOStatement
   {
      return $this->statement;
   }

   public function getCountTouch(): int
   {
      return $this->count_touch;
   }

   public function getLastInsertID(): int
   {
      return $this->last_insert_id;
   }
}
