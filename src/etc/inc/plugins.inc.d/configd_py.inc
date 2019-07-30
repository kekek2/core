<?php

function configd_py_syslog()
{
    $logfacilities = array();

    $logfacilities['configd'] = array(
        'facility' => array('configd.py'),
    );

    return $logfacilities;
}

