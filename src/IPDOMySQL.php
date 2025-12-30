<?php

declare(strict_types=1);

namespace Inilim\IPDO;

use PDO;
use PDOException;
use Inilim\IPDO\IPDO;
use Inilim\IPDO\Exception\IPDOException;

class IPDOMySQL extends IPDO
{
   /**
    * @param array<int|string,mixed> $options
    */
   function __construct(
      string $nameDB,
      string $login,
      string $password,
      string $host = 'localhost',
      array $options = []
   ) {
      $this->nameDB   = $nameDB;
      $this->login    = $login;
      $this->password = $password;
      $this->host     = $host;
      $this->options  = $options;
   }

   /**
    * В момент создания PDO может выбросить исключение PDOException
    * @throws PDOException
    */
   protected function connectDB(): void
   {
      if ($this->connect !== null) {
         return;
      }

      if (!self::extensionLoaded()) {
         throw new IPDOException([
            'message' => 'IPDO: Extension not loaded "pdo_mysql"',
         ]);
      }

      $this->countConnect++;
      $this->connect = new PDO(
         \sprintf(
            'mysql:dbname=%s;host=%s',
            $this->nameDB,
            $this->host
         ),
         $this->login,
         $this->password,
         $this->options + [
            PDO::MYSQL_ATTR_FOUND_ROWS => true,
            // PDO::ATTR_EMULATE_PREPARES => false,
         ]
      );
      $this->connect->exec('SET NAMES utf8mb4');
   }

   // ---------------------------------------------
   // 
   // ---------------------------------------------

   static function extensionLoaded(): bool
   {
      return \extension_loaded('pdo_mysql');
   }
}
