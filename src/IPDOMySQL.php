<?php

namespace Inilim\IPDO;

use Inilim\IPDO\IPDO;
use Inilim\Integer\Integer;
use PDO;
use PDOException;

class IPDOMySQL extends IPDO
{
   public function __construct(string $name_db, string $login, string $password, Integer $integer, string $host = 'localhost')
   {
      $this->name_db  = $name_db;
      $this->login    = $login;
      $this->password = $password;
      $this->integer  = $integer;
      $this->host     = $host;
   }

   /**
    * В момент создания PDO может выбросить исключение PDOException
    * @throws PDOException
    */
   protected function connectDB(): void
   {
      if (!is_null($this->connect)) return;

      $this->count_connect++;
      $this->connect = new PDO(
         'mysql:dbname=' . $this->name_db .
            ';host=' . $this->host,
         $this->login,
         $this->password,
         [
            PDO::MYSQL_ATTR_FOUND_ROWS => true,
            // PDO::ATTR_EMULATE_PREPARES => false,
         ]
      );
      $this->connect->exec('SET NAMES utf8mb4');
   }
}
