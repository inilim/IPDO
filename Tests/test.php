<?php

\ini_set('error_reporting', E_ALL);

require_once '../vendor/autoload.php';

use Inilim\Dump\Dump;
use Inilim\IPDO\IPDOMySQL;
use Inilim\IPDO\DTO\ByteParamDTO;
use Inilim\IPDO\DTO\QueryParamDTO;

Dump::init();

$db_host = "MySQL-8.2";
$db_name = "remfy_local";
$username = "root";
$password = "";
$connect = new IPDOMySQL($db_name, $username, $password, $db_host);

// $query1 = 'SELECT * FROM table';

// $query = 'INSERT INTO tasks (class, method, params, execute_after, created_at)
// VALUES ({class}, {method}, {params}, {execute_after}, {execute_after}, ({created_at}))';

$query = 'SELECT * FROM crm_leads_info {class} WHERE {method} {method} info_uid in {info_uid} OR info_uid in {info_uid} LIMIT 10';
$query = '{item1}{item2}{item3}{item4}{item5}{item6}';

$values = [
    'info_uid' => [1, 22, 33, 44, 55],
    'method' => 'method_kadwld',
    'class' => 'class_kadwld',
    'not_found1' => '[1, 22, 33, 44, 55]',
    'not_found2' => '[1, 22, 33, 44, 55]',
    'not_found3' => '[1, 22, 33, 44, 55]',
];

$values = [
    'item1' => 1,
    'item2' => '2',
    'item3' => 3.0,
    'item4' => new ByteParamDTO('byte'),
    'item5' => true,
    'item6' => [1, 2, 3, 4, 5],
];

$res = $connect->exec($query, $values, 2);

de($res);
