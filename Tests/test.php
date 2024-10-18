<?php

\ini_set('error_reporting', E_ALL);

require_once '../vendor/autoload.php';

use Inilim\Dump\Dump;
use Inilim\IPDO\DTO\QueryParamDTO;

Dump::init();

// $query1 = 'SELECT * FROM table';

$query1 = 'INSERT INTO tasks (class, method, params, execute_after, created_at)
                VALUES (:class, :method, :params, :execute_after, :execute_after, :created_at)';

$query1 = 'INSERT INTO tasks (class, method, params, execute_after, created_at)
                VALUES (:class, :method, :params, :execute_after, :execute_after, :created_at)';

$param = new QueryParamDTO($query1, [
    'class' => 1,
    'method' => 1,
    'params' => 1,
    'execute_after' => 1,
    'key_not_found' => 'dawkkjawhdlajkhwdjhwdjkj',
    'created_at' => [1, 2, 3, 4, 5],
]);
de($param);
