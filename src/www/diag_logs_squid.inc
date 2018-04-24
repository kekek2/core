<?php

/*
    Copyright (C) 2015-2017 Franco Fichtner <franco@opnsense.org>
    Copyright (C) 2014 Deciso B.V.
    Copyright (C) 2004 Scott Ullrich <sullrich@gmail.com>
    Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>
    All rights reserved.

    Redistribution and use in source and binary forms, with or without
    modification, are permitted provided that the following conditions are met:

    1. Redistributions of source code must retain the above copyright notice,
       this list of conditions and the following disclaimer.

    2. Redistributions in binary form must reproduce the above copyright
       notice, this list of conditions and the following disclaimer in the
       documentation and/or other materials provided with the distribution.

    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
    POSSIBILITY OF SUCH DAMAGE.
*/

use http\Url;

if (!isset($logsplit)) {
    $logsplit = 3;
}

function print_dump($logarr)
{
    global $config, $logsplit;

    if (!is_array($logarr)) {
        echo "<tr>\n";
        echo "<td colspan=\"2\">" . htmlspecialchars($logarr) . "</td>\n";
        echo "</tr>\n";
        return;
    }

    foreach ($logarr as $logent) {
        $logent = preg_split('/\s+/', $logent);
        if (count($logent) < $logsplit + 1) {
            continue;
        }
        echo "<tr>\n";
        $entry_date_time = html_safe(join(' ', array_slice($logent, 0, $logsplit)));
        $entry_text = '';
        $hostname = $logent[$logsplit];
        if ($hostname != $config['system']['hostname']) {
            $entry_text = html_safe($hostname) . ' ';
        }
        $entry_text .= html_safe(join(' ', array_slice($logent, $logsplit + 1)));
        echo "<td>{$entry_date_time}</td>\n";
        echo "<td>{$entry_text}</td>\n";
        echo "</tr>\n";
    }
}

function dump_log($logfile, $tail, $grepfor = '')
{
    global $config;

    $reverse = isset($config['OPNsense']['Syslog']['Reverse']) && $config['OPNsense']['Syslog']['Reverse'];
    $logarr = array();

    $grepfor = preg_split('/\s+/', trim($grepfor));

    if (is_dir($logfile)) {
        $logarr = sprintf(gettext('File %s is a directory.'), $logfile);
    } elseif (!file_exists($logfile)) {
        $logarr = sprintf(gettext('File %s doesn\'t exist.'), $logfile);
    } else {
        $handle = fopen($logfile, "r");
        if ($reverse)
            fseek($handle, 0, SEEK_END);
        $count = $tail;
        do {
            if (($rez = read_string($handle, $reverse)) === false) {
                break;
            }
            if ($logfile == "/var/log/squid/access.log") {
                $split = preg_split('/\s+/', trim($rez));
                if (isset($split[6])) {
                    $parse = parse_url($split[6]);
                    $parse["host"] = idn_to_utf8($parse["host"]);

                    if (isset($parse["scheme"])) {
                        $split[6] = (new Url($parse))->toString();
                    } else {
                        $split[6] = $parse["host"] . ":" . $parse["port"];
                    }
                    $rez = implode(" ", $split);
                }
            }
            $find = true;
            foreach ($grepfor as $pattern) {
                $find = $find && preg_match("/$pattern/", $rez) == 1;
            }
            if (!$find) {
                continue;
            }
            $count--;
            $logarr[] = $rez;
        } while ($count > 0);
        fclose($handle);
    }

    if (is_array($logarr) && !count($logarr)) {
        $logarr = sprintf(gettext('File %s yielded no results.'), $logfile);
    }

    print_dump($logarr);
}

const BUFFER_SIZE = 4096;

function read_string($handle, $reverse)
{
    if (!$reverse) {
        return fgets($handle);
    }
    static $beginning = false;
    static $buffer = "";
    static $buf_pos = -1;

    if ($beginning) {
        return false;
    }

    $read_str = "";
    while (true)
    {
        if ($buf_pos < 0)
        {
            $pos = ftell($handle);
            if ($pos == 0) {
                $beginning = true;
                break;
            }
            $pos -= BUFFER_SIZE;
            if ($pos < 0)
                $pos = 0;
            fseek($handle, $pos, SEEK_SET);
            $buffer = fread($handle, BUFFER_SIZE);
            fseek($handle, $pos, SEEK_SET);
            $buf_pos = strlen($buffer) - 1;
        }
        if ($buffer[$buf_pos] == "\n" && $read_str != "") {
            break;
        }
        $read_str = $buffer[$buf_pos--] . $read_str;
    }
    return $read_str;
}