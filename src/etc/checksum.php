#!/usr/local/bin/php
<?php
require_once("util.inc");
require_once("config.inc");

$descriptorspec = array(
   0 => array("pipe", "r"),
   1 => array("file", "php://stdout", "w"),
   2 => array("pipe", "w")
);

$commands = [
    "kernel" => "/usr/local/bin/cfv -VV -f boot/kernel.sum",
    "base" => "/usr/local/bin/cfv -VV -f boot/base.sum",
    "packages" => "/usr/sbin/pkg check -s"
    ];

chdir("/");

foreach ($commands as $what => $command)
{
    $process = proc_open($command, $descriptorspec, $pipes);
    if ($what != "packages")
        fread($pipes[2], 1024);
    $err = false;
    while (!feof($pipes[2]))
    {
        $str = substr(fread($pipes[2], 1024), 0, -1);
        if (strlen($str) > 0)
        {
            $err = true;
            syslog(LOG_ERR, $str);
        }
    }
    proc_close($process);
    if ($err)
        mark_subsystem_dirty($what);
}

