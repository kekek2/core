#!/usr/local/bin/php
<?php
require_once("util.inc");
require_once("config.inc");

$commands = [
    "kernel" => "/usr/local/bin/cfv -VV -f boot/kernel.sum",
    "base" => "/usr/local/bin/cfv -VV -f boot/base.sum",
    "packages" => "/usr/sbin/pkg check -qs"
    ];

chdir("/");

foreach ($commands as $what => $command)
{
    $fp = popen($command . " 2>&1", 'r');
    if ($what != "packages")
        fread($fp, 1024);
    $err = false;
    while (!feof($fp))
    {
        $str = substr(fread($fp, 1024), 0, -1);
        if (strlen($str) > 0)
        {
            $err = true;
            syslog(LOG_ERR, $str);
        }
    }
    pclose($fp);
    if ($err)
        mark_subsystem_dirty($what);
}

