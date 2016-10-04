#!/usr/local/bin/php
<?php
require_once("config.inc");
require_once 'Net/DNS2.php';

$ns_server = 'smartsoft.cdns.cair.ru';
$ip_ns_server = gethostbyname($ns_server);
if ($ip_ns_server == $ns_server)
{
	syslog(LOG_ERR, "Error resolving name server");
	exit(1);
}

$resolver = new Net_DNS2_Resolver(array('nameservers' => array($ip_ns_server)));

while (!feof(STDIN))
{
    $domain = fgets(STDIN);
    if ($domain === false)
        continue;
    try
    {
		$resp = $resolver->query($domain . ".", 'TXT');
    }
    catch (Exception $e)
    {
		syslog(LOG_ERR, "Exception " . $e->getMessage());
		echo "OK\n";
		continue;
    }
    //echo $resp->answer[0]->text[0] . "\n";
    //syslog(LOG_ERR, substr($domain, 0, -1));
    echo "ERR\n";
}
