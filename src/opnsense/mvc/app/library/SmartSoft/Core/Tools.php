<?php

namespace SmartSoft\Core;

use OPNsense\Core\Config;
use OPNsense\Base\ViewTranslator;

class Tools
{
    public static function getTranslator()
    {
        $lang = 'en_US';

        // Set locale
        foreach (Config::getInstance()->object()->system->children() as $key => $node) {
            if ($key == 'language') {
                $lang = $node->__toString();
                break;
            }
        }

        $locale = $lang . '.UTF-8';
        bind_textdomain_codeset('OPNsense', $locale);
        return new ViewTranslator(array(
            'directory' => '/usr/local/share/locale',
            'defaultDomain' => 'OPNsense',
            'locale' => $locale,
        ));
    }

    public static function getCurrentMacAddress()
    {
        $interfaces = [];
        $output = [];
        if (!exec("/sbin/ifconfig -l", $output)) {
            return false;
        }
        foreach ($output as $line) {
            $interfaces = array_merge($interfaces, explode(" ", $line));
        }
        asort($interfaces);
        foreach ($interfaces as $iface) {
            $ifname = preg_replace("/(\w+)(\d+)/", "$1.$2", trim($iface));
            $output = [];
            if (!exec("/sbin/sysctl -n dev.{$ifname}.orig_mac_addr", $output)) {
                continue;
            }
            foreach ($output as $line) {
                return strtoupper($line);
            }
        }
        return false;
    }
}