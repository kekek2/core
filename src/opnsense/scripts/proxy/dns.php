#!/usr/local/bin/php
<?php

while (!feof(STDIN))
{
    $domain = fgets(STDIN);
    if ($domain === false)
        continue;
    syslog(LOG_ERR, substr($domain, 0, -1));
    echo "ERR\n";
}
