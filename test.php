<?php
namespace Swolley\Database;

//require_once 'vendor/autoload.php';
require_once 'DBFactory.php';

$params = [
	'driver' => 'oci8',
	'host' => '192.168.167.97',
	'port' => 1524,
	'user' => 'ADVUS',
	'password' => 'ADVUS',
	'sid' => 'WB12'
];

$pippo = (new DBFactory)($params);