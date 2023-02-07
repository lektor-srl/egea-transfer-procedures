<?php
namespace Entities;
require __DIR__.'/../vendor/autoload.php';
$params = [
    'limit' => null,
    'dateFrom' => '2022-07-01 00:00:00',
];

$program = new AttachmentsMain($params);
