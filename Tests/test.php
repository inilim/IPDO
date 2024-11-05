<?php

\ini_set('error_reporting', E_ALL);

require_once '../vendor/autoload.php';

use Inilim\Dump\Dump;
use Inilim\IPDO\IPDOMySQL;
use Inilim\IPDO\DTO\ByteParamDTO;
use Inilim\IPDO\DTO\QueryParamDTO;

Dump::init();

$a = new IPDOMySQL('remfy_local', 'root', '', 'MySQL-8.2');

$sql = 'SELECT 1
            FROM designer_orders
            WHERE id IN ({id})
            HAVING COUNT(*) = {count}';

$res = $a->exec($sql, [
    'id'    => [1, 2, 3],
    'count' => 3,
], 2);


de($res);
