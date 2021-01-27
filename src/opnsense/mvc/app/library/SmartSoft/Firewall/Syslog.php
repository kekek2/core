<?php

namespace SmartSoft\Firewall;

class Syslog
{
    public static function log($rule_action, $id = null, $needle = null)
    {
        if (!empty($_SESSION['Username'])) {
            $username = $_SESSION['Username'];
        } else {
            $username = '(' . trim(shell_exec('/usr/bin/whoami')) . ')';
        }

        openlog("administrative", LOG_ODELAY, LOG_USER);
        if ($id === null) {
            syslog(LOG_NOTICE, $username . ": " . $rule_action);
        } else {
            if (is_array($id) && $needle != null) {
                $id = array_search($needle, $id);
                if ($id === false) {
                    syslog(LOG_ERR, "Firewall/Rules error inserting rule");
                }
            }
            syslog(LOG_NOTICE, $username . ": " . $rule_action . ": " . $id);
        }
        openlog('opnsense', LOG_ODELAY, LOG_USER);
    }

}
