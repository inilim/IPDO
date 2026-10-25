<?php

declare(strict_types=1);

namespace Inilim\IPDO;

use PDO;
use PDOException;
use Inilim\IPDO\IPDO;
use Inilim\IPDO\Exception\IPDOException;

class IPDOMySQL extends IPDO
{
   protected string $host;
   protected string $login;
   /**
    * @var string|\SensitiveParameterValue
    */
   protected $password;

   /**
    * @param array<int|string,mixed> $options
    */
   function __construct(
      string $nameDB,
      string $login,
      #[\SensitiveParameter]
      string $password,
      string $host = 'localhost',
      array $options = []
   ) {
      $this->nameDB   = $nameDB;
      $this->login    = $login;
      $this->password = \PHP_VERSION_ID >= 80200 ? new \SensitiveParameterValue($password) : $password;
      $this->host     = $host;
      $this->options  = $options;
   }

   function __debugInfo(): array
   {
      $props = \get_object_vars($this);
      if (\PHP_VERSION_ID < 80200) {
         unset($props['password']);
      }
      return $props;
   }

   protected function getPassword(): string
   {
      return \PHP_VERSION_ID >= 80200 ? $this->password->getValue() : $this->password;
   }

   /**
    * В момент создания PDO может выбросить исключение PDOException
    * @throws PDOException
    * @phpstan-assert !null $this->connect
    */
   protected function connectDB(): void
   {
      if (null !== $this->connect) {
         return;
      }

      if (!static::extensionLoaded()) {
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
         $this->getPassword(),
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
