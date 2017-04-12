#!/usr/local/bin/php

<?php

require_once('script/load_phalcon.php');

use \OPNsense\Proxy\Proxy;

$mdlProxy = new Proxy();

foreach ($mdlProxy->getNodeByReference('forward.acl.groupACLs.groupACL')->getNodes() as $acl)
    file_put_contents("/usr/local/etc/squid/groupACL_" . str_replace(" ", "_", $acl["groupName"]) . ".txt", $acl["groupName"]);

system("/usr/local/etc/rc.d/squid reload");
