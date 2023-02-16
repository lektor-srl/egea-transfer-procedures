<?php
namespace Entities;
require __DIR__.'/../vendor/autoload.php';

$args = getopt("", ["mode:", "from:", "to:", "limit:"]);

if(!$args || $args == ''){
    echo "Arguments missing";
}
// Se non vengono passati parametri nelle date, prende la data corrente
$params = [
    'limit' => $args['limit'] ?? null,                     // provide a limit to the attachment downloaded
    'dateFrom' => $args['from'] ?? date('Y-m-d'),   // must be in this format: YYYY-mm-dd
    'dateTo' => $args['to'] ?? date('Y-m-d'),       // must be in this format: YYYY-mm-dd
];

$program = new AttachmentsMain($params);
