<?php

namespace SmartSoft\Core;

use OPNsense\Core\Config;
use OPNsense\Core\Backend;
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
        $backend = new Backend();

        $interfaces = explode(" ", $backend->configdRun("system interfaces_list"));
        asort($interfaces);
        foreach ($interfaces as $iface) {
            $ifname = preg_replace("/(\w+)(\d+)/", "$1.$2", trim($iface));
            if ($mac = trim($backend->configdRun("system get_sysctl dev.{$ifname}.orig_mac_addr"))) {
                return strtoupper($mac);
            }
        }
        return false;
    }
}