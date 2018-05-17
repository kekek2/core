<?php

function configd_syslog()
{
    $logfacilities = array();

    $logfacilities['configd'] = array(
        'facility' => array('configd.py'),
    );

    return $logfacilities;
}

