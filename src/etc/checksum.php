#!/usr/local/bin/php
<?php

$config_error = false;
$filename = "/conf/config.xml";
if (str_replace(["\r", "\n"], '', file_get_contents($filename . ".sum")) != sha1(file_get_contents($filename))) {
    $config_error = true;
}

require_once("config.inc");
require_once("notices.inc");

use OPNsense\Core\Config;
use SmartSoft\Core\Tools;

Tools::getTranslator();

$descriptorspec = [
    0 => [],
    1 => ["pipe", "w"],
    2 => ["pipe", "w"]
];

$messages = [
    "crc does not match" => gettext("crc does not match"),
    "No such file or directory" => gettext("No such file or directory"),
    "checksum mismatch for" => gettext("checksum mismatch for"),
    "missing file" => gettext("missing file")
];

$tmpfile = "/tmp/pkg_check.exclude";

$commands = [
    "kernel" => ["command" => "mtree -e -p /", "pipe" => ["file", "/usr/local/opnsense/version/kernel.mtree", "r"]],
    "base" => [
        "command" => "mtree -e -p / -X $tmpfile",
        "pipe" => ["file", "/usr/local/opnsense/version/base.mtree", "r"]
    ],
    "packages" => ["command" => "/usr/sbin/pkg check -sa", "pipe" => ["pipe", "r"]]
];

$mtree_patterns = [
    "./etc/group",
    "./etc/hosts",
    "./etc/master.passwd",
    "./etc/motd",
    "./etc/newsyslog.conf",
    "./etc/pam.d/sshd",
    "./etc/pam.d/system",
    "./etc/passwd",
    "./etc/pwd.db",
    "./etc/rc",
    "./etc/rc.shutdown",
    "./etc/shells",
    "./etc/spwd.db",
    "./etc/ttys"
];

$error = false;

ini_set('implicit_flush', true);
chdir("/");

if ($config_error) {
    if (Config::check_sha1($filename, file_get_contents($filename)) && are_notices_pending()) {
        $notices = get_notices("config");
        if (is_array($notices) && isset(end($notices)["notice"][0])) {
            echo end($notices)["notice"][0] . "\n";
        } else {
            echo "No valid config.xml found\n";
            $error = true;
        }
    } else {
        echo "No valid config.xml found\n";
        $error = true;
    }
}

file_put_contents($tmpfile, implode("\n", $mtree_patterns));

foreach ($commands as $what => $command) {
    echo "Checking " . $what;

    $descriptorspec[0] = $command["pipe"];
    $process = proc_open($command["command"], $descriptorspec, $pipes);
    $read = [$pipes[1], $pipes[2]];
    $write = null;
    $except = null;

    $message = "";
    while (true) {
        $read = [$pipes[1], $pipes[2]];
        if (stream_select($read, $write, $except, null) === false) {
            break;
        }
        if (feof($pipes[1]) && feof($pipes[2])) {
            break;
        }
        foreach ($read as $key => $pipe) {
            $str = fgets($pipe, 1024);
            if ($str === false) {
                continue;
            }
            if ($what == "packages" && $key == 0) {
                continue;
            }
            foreach ($mtree_patterns as $mtree_pattern) {
                if ($mtree_pattern . " missing" == trim($str)) {
                    continue 2;
                }
            }
            if ($str[0] == "\t") {
                $message .= " " . trim($str);
            } else {
                $message = $what . ": " . trim($str);
            }
            if (strpos($str, ": \n" ) !== false || strpos($str, "size") !== false) {
                continue;
            }
            $notice = $message;
            foreach ($messages as $en => $ru) {
                if (strpos($message, $en)) {
                    $notice = [$message, str_replace($en, $ru, $message)];
                    break;
                }
            }
            file_notice($what, $notice, $priority = 1);
            echo "\n" . $message;
            $error = true;
            usleep(2000);
        }
    }
    proc_close($process);
    echo "\ndone\n";
}
unlink($tmpfile);
if (!$error) {
    echo "Configuration and executable are authentic\n";
}

