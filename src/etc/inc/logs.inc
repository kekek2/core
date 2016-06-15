<?php

function firewall_syslog($rule_action, $id, $needle = NULL)
{
    if (!empty($_SESSION['Username'])) {
        $username = $_SESSION['Username'];
    } else {
        $username = '(' . trim(shell_exec('/usr/bin/whoami')) . ')';
    }

    openlog($username, LOG_ODELAY, LOG_USER);
    if (is_array($id) && $needle != NULL) {
        $id = array_search($needle, $id);
        if ($id === FALSE)
            syslog(LOG_ERR, "Firewall/Rules error inserting rule");
    }
    syslog(LOG_NOTICE, $rule_action . ": " . $id);
    openlog('opnsense', LOG_ODELAY, LOG_USER);
}
