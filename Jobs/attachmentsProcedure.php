<?php
namespace Entities;
require __DIR__.'/../vendor/autoload.php';
$params = [
    'limit' => null,
    'dateFrom' => '2023-01-01',
    'dateTo' => '2023-01-31',
];

$program = new AttachmentsMain($params);
