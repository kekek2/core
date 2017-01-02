#!/usr/local/bin/php
<?php
require_once("config.inc");
require_once("notices.inc");

if (!are_notices_pending())
    return;

$notices = get_notices();
if (!is_array($notices))
    return;

foreach ($notices as $key => $value)
    echo preg_replace("/(\"|\'|\n|<.?\w+>)/i", "", ($value['notice'] != "" ? $value['notice'] : $value['id'])) . "\n";


?>

