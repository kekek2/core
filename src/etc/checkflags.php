#!/usr/local/bin/php
<?php

require_once('script/load_phalcon.php');

use \OPNsense\Core\Notices;

$Notices = (new Notices())->getNotices();

if (!$Notices) {
    echo "Configuration and executable are authentic\n";
    return;
}

foreach ($Notices as $value) {
    echo date("m-d-y H:i:s", $value['datetime']) . " [ " . $value['message'] . " ]\n";
}

?>
