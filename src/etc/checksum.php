#!/usr/local/bin/php
<?php
require_once("config.inc");
require_once("notices.inc");

$descriptorspec = array(
    0 => array("pipe", "r"),
    1 => array("pipe", "w"),
    2 => array("pipe", "w")
);

$commands = [
    "kernel" => "/usr/local/bin/cfv -VV --progress yes -f boot/kernel.sum",
    "base" => "/usr/local/bin/cfv -VV --progress yes -f boot/base.sum",
    "packages" => "/usr/sbin/pkg check -s"
];

chdir("/");

foreach ($commands as $what => $command)
{
    echo "Checking " . $what;
    $process = proc_open($command, $descriptorspec, $pipes);
    $read = array($pipes[1], $pipes[2]);
    $write = NULL;
    $except = NULL;

    if ($what != "packages")
        fgets($pipes[2], 1024);
    while (true)
    {
        $read = array($pipes[1], $pipes[2]);
        if (stream_select($read, $write, $except, null) === false)
            break;
        if (feof($pipes[1]) && feof($pipes[2]))
            break;
        foreach ($read as $key => $pipe)
        {
            if ($key == 0)
            {
                $str = fread($pipe, 1024);
                echo ".";
            }
            else
            {
                $str = fgets($pipe, 1024);
                if ($str !== false)
                    file_notice($what, $str, $priority = 1);
            }
        }
    }
    proc_close($process);
    echo "done\n";
}

