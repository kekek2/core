<?php

function lighttpd_syslog()
{
    $logfacilities = array();

    $logfacilities['lighttpd'] = array(
        'facility' => array('lighttpd'),
    );

    return $logfacilities;
}

