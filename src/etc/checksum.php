#!/usr/local/bin/php
<?php

$filename = "/conf/config.xml";
if (str_replace(["\r", "\n"], '', file_get_contents($filename . ".sum")) != sha1(file_get_contents($filename))) {
    $error = true;
}

require_once('script/load_phalcon.php');

use \OPNsense\Core\Notices;

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
            if (strpos($str, ": \n") !== false || strpos($str, "size") !== false) {
                continue;
            }
            foreach ($messages as $en => $ru) {
                if (strpos($message, $en)) {
                    $message = str_replace($en, $ru, $message);
                    break;
                }
            }
            (new Notices())->addNotice($message);
            echo "\n" . $message;
            $error = true;
        }
    }
    proc_close($process);
    echo "\ndone\n";
}
unlink($tmpfile);
if (!$error) {
    echo "Configuration and executable are authentic\n";
}

