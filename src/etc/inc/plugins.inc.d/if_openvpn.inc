<?php

/*
    Copyright (C) 2016 Deciso B.V.
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

function if_openvpn_syslog()
{
    $logfacilities = array();

    $logfacilities['openvpn'] = array(
        'facility' => array('openvpn'),
        'remote' => 'vpn',
    );

    return $logfacilities;
}

function if_openvpn_services()
{
    global $config;

    $services = array();

    foreach (array('server', 'client') as $mode) {
        if (isset($config['openvpn']["openvpn-{$mode}"])) {
            foreach ($config['openvpn']["openvpn-{$mode}"] as $setting) {
                if (empty($setting['disable'])) {
                    $pconfig = array();
                    $pconfig['description'] = "OpenVPN {$mode}: " . htmlspecialchars($setting['description']);
                    $pconfig['pidfile'] = "/var/run/openvpn_{$mode}{$setting['vpnid']}.pid";
                    $pconfig['php']['restart'] = array('openvpn_restart_by_id');
                    $pconfig['php']['start'] = array('openvpn_restart_by_id');
                    $pconfig['php']['args'] = array('mode', 'id');
                    $pconfig['id'] = $setting['vpnid'];
                    $pconfig['name'] = 'openvpn';
                    $pconfig['mode'] = $mode;
                    $services[] = $pconfig;
                }
            }
        }
    }

    return $services;
}

function if_openvpn_interfaces()
{
    global $config;

    $interfaces = array();

    foreach (array('server', 'client') as $mode) {
        if (isset($config['openvpn']["openvpn-{$mode}"])) {
            foreach ($config['openvpn']["openvpn-{$mode}"] as &$settings) {
                if (empty($settings['disable'])) {
                    $oic = array('enable' => true);
                    $oic['if'] = 'openvpn';
                    $oic['descr'] = 'OpenVPN';
                    $oic['type'] = 'group';
                    $oic['virtual'] = true;
                    $oic['networks'] = array();

                    if (!empty($settings['tunnel_network'])) {
                        list($ip, $mask) = explode('/', $settings['tunnel_network']);
                        $oic['subnet'] = $mask;
                        $mask = __gen_subnet_mask($mask);
                        list($ip1, $ip2) = __get_interface_ip($ip, $mask);
                        $oic['ipaddr'] = $ip1;
                    }
                    $interfaces['openvpn'] = $oic;
                    break 2;
                }
            }
        }
    }

    return $interfaces;
}

function if_openvpn_xmlrpc_sync()
{
    $result = array();

    $result[] = array(
        'description' => gettext('OpenVPN'),
        'section' => 'openvpn',
        'id' => 'openvpn',
    );

    return $result;
}

function __gen_subnet_mask($bits)
{
    return long2ip(__gen_subnet_mask_long($bits));
}

function __gen_subnet_mask_long($bits)
{
    $sm = 0;
    for ($i = 0; $i < $bits; $i++) {
        $sm >>= 1;
        $sm |= 0x80000000;
    }
    return $sm;
}

function __get_interface_ip($ip, $mask)
{
    $baselong = __ip2long32($ip) & ip2long($mask);
    $ip1 = __long2ip32($baselong + 1);
    $ip2 = __long2ip32($baselong + 2);
    return array($ip1, $ip2);
}

function __ip2long32($ip)
{
    return ( ip2long($ip) & 0xFFFFFFFF );
}

function __long2ip32($ip)
{
    return long2ip($ip & 0xFFFFFFFF);
}

