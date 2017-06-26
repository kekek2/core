#!/usr/local/bin/php
<?php

$config_error = false;
$filename = "/conf/config.xml";
if (str_replace(["\r", "\n"], '', file_get_contents($filename . ".sum")) != sha1(file_get_contents($filename)))
    $config_error = true;

require_once("config.inc");
require_once("notices.inc");

use OPNsense\Core\Config;
use OPNsense\Base\ControllerRoot;

ControllerRoot::setLocale(ControllerRoot::getLangEncode(), 'OPNsense');

$descriptorspec = array(
    0 => array("pipe", "r"),
    1 => array("pipe", "w"),
    2 => array("pipe", "w")
);

$messages = [
    "crc does not match" => gettext("crc does not match"),
    "No such file or directory" => gettext("No such file or directory"),
    "checksum mismatch for" => gettext("checksum mismatch for"),
    "missing file" => gettext("missing file")
];

$commands = [
    "kernel" => "/usr/local/bin/cfv -VV --progress yes -f boot/kernel.sum",
    "base" => "/usr/local/bin/cfv -VV --progress yes -f boot/base.sum",
    "packages" => "/usr/sbin/pkg check -s"
];

$error = false;

chdir("/");

if ($config_error)
{
    if (Config::check_sha1($filename, file_get_contents($filename)) && are_notices_pending())
    {
        $notices = get_notices("config");
        if (is_array($notices) && isset(end($notices)["notice"][0]))
            echo end($notices)["notice"][0] . "\n";
        else
        {
            echo "No valid config.xml found\n";
            $error = true;
        }
    }
    else
    {
        echo "No valid config.xml found\n";
        $error = true;
    }
}

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
                {
                    $str = $what . ": " . $str;
                    $message = $str;
                    foreach ($messages as $en => $ru)
                    {
                        if (strpos($str, $en))
                        {
                            $message = [$str, str_replace($en, $ru, $str)];
                            break;
                        }
                    }
                    file_notice($what, $message, $priority = 1);
                    echo "\n" . $str;
                    $error = true;
                    usleep(2000);
                }
            }
        }
    }
    proc_close($process);
    echo "done\n";
    if (!$error)
        echo "Configuration and executable are authentic\n";
}

