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
            $proc = proc_open("/sbin/sysctl -n dev.{$ifname}.orig_mac_addr",[
                    1 => ['pipe','w'],
                    2 => ['pipe','w'],
                ],$pipes);
            if ($proc) {
                $stdout = trim(stream_get_contents($pipes[1]));
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($proc);
                if(!empty($stdout)) {
                    return strtoupper($stdout);
                }
            }
        }
        return false;
    }

    public static function check_certificate($Module)
    {
        $cert_path = sprintf("/usr/local/etc/ssl/ting-client.module.%s.crt", strtolower($Module));

        if (!is_file($cert_path)) {
            throw new \Exception(sprintf(gettext('License not found for %s plugin'), $Module));
        }

        $cert = openssl_x509_parse(file_get_contents($cert_path));
        if (isset($cert['subject']['UNDEF'][2])) {
            if ($cert['subject']['UNDEF'][2] != strtoupper($Module)) {
                throw new \Exception(sprintf(gettext("License not found for %s plugin"), $Module));
            }

            if (strftime("%s", $cert["validTo_time_t"]) < time()) {
                throw new \Exception(sprintf(gettext("Certificate for %s plugin expired"), $Module));
            }

            if (!isset($cert['subject']['UNDEF'][0])) {
                throw new \Exception(gettext("Can not validate certificate"));
            }

            $mac = self::getCurrentMacAddress();
            if ($cert['subject']['UNDEF'][0] != $mac) {
                throw new \Exception(gettext("License is not valid for this device"));
            }
        } elseif (isset($cert['subject']['tingModule'])) {
            if ($cert['subject']['tingModule'] != strtoupper($Module)) {
                throw new \Exception(sprintf(gettext("License not found for %s plugin"), $Module));
            }

            if (strftime("%s", $cert["validTo_time_t"]) < time()) {
                throw new \Exception(sprintf(gettext("Certificate for %s plugin expired"), $Module));
            }

            if (!isset($cert['subject']['tingAddress'])) {
                throw new \Exception(gettext("Can not validate certificate"));
            }

            $mac = self::getCurrentMacAddress();
            if ($cert['subject']['tingAddress'] != $mac) {
                throw new \Exception(gettext("License is not valid for this device"));
            }
        } else {
            throw new \Exception(sprintf(gettext("License not found for %s plugin"), $Module));
        }

        return;
    }
}