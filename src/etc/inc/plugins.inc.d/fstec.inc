<?php

function fstec_cron()
{
    return [['autocron' => ['/usr/local/bin/php /usr/local/etc/checksum.php > /dev/null', '6']]];
}

function fstec_syslog()
{
    $logfacilities = array();

    $logfacilities['administrative'] = array('facility' => array('administrative'));

    return $logfacilities;
}
