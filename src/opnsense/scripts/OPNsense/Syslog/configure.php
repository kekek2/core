#!/usr/local/bin/php
<?php

require_once("config.inc");

$syslog = new OPNsense\Syslog\Syslog;
configd_run("template reload OPNsense.Syslog");
