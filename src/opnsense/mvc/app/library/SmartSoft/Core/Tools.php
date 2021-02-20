<?php

namespace SmartSoft\Core;

use OPNsense\Core\Config;
use OPNsense\Base\ViewTranslator;

class Tools
{
    const ting_crt_dir = '/usr/local/etc/ssl';
    const installed_key_path = self::ting_crt_dir . "/ting-client.key";
    const installed_crt_path = self::ting_crt_dir . "/ting-client.crt";
    const installed_crt_modules_path = self::ting_crt_dir . "/ting-client.module.*.crt";

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

    public static function get_installed_certificates()
    {
        if (!file_exists(self::installed_key_path)) {
            return [];
        }
        $key_modulus = false;
        $key_file = file_get_contents(self::installed_key_path);
        if (($priv_key = openssl_pkey_get_private($key_file))) {
            if (($keyData = openssl_pkey_get_details($priv_key)) && $keyData["type"] == OPENSSL_KEYTYPE_RSA) {
                $key_modulus = $keyData["rsa"]["n"];
            }
        }

        $installed_crt_info = [];
        $cert_array = glob(self::installed_crt_modules_path);
        array_unshift($cert_array, self::installed_crt_path);
        foreach ($cert_array as $crt_path) {
            if (!file_exists($crt_path)) {
                continue;
            }
            $cert_module_file = file_get_contents($crt_path);
            $modulus = false;
            if (($pub_key = openssl_pkey_get_public($cert_module_file))) {
                if (($keyData = openssl_pkey_get_details($pub_key)) && $keyData["type"] == OPENSSL_KEYTYPE_RSA) {
                    $modulus = $keyData["rsa"]["n"];
                }
            }

            $cert = openssl_x509_parse($cert_module_file);

            if (isset($cert["subject"]["UNDEF"][2])) {
                $module = ($cert["subject"]["UNDEF"][2] !== "") ? $cert["subject"]["UNDEF"][2] : "CORE";
            } elseif (isset($cert["subject"]["tingModule"])) {
                $module = ($cert["subject"]["tingModule"] !== "") ? $cert["subject"]["tingModule"] : "CORE";
            } else {
                $module = "";
            }

            if (isset($cert["subject"]["UNDEF"][0])) {
                $cert_mac = $cert["subject"]["UNDEF"][0];
            } elseif (isset($cert["subject"]["tingAddress"])) {
                $cert_mac = $cert["subject"]["tingAddress"];
            } else {
                $cert_mac = "";
            }

            if (isset($cert["subject"]["UNDEF"][1])) {
                $license = $cert["subject"]["UNDEF"][1];
            } elseif (isset($cert["subject"]["tingLicense"])) {
                $license = $cert["subject"]["tingLicense"];
            } else {
                $license = "";
            }

            if (isset($cert["subject"]["UNDEF"][3])) {
                $usersCount = $cert["subject"]["UNDEF"][3];
            } elseif (isset($cert["subject"]["tingUsers"])) {
                $usersCount = $cert["subject"]["tingUsers"];
            } else {
                $usersCount = "";
            }

            if ($cert_mac != Tools::getCurrentMacAddress()) {
                $license = gettext("License is not valid for this device");
            }

            if ($modulus != $key_modulus) {
                $license = gettext("The certificate does not match the private key");
            }

            $installed_crt_info[] = [
                "module" => $module,
                "validTo_time_t" => $cert["validTo_time_t"],
                "expires" => ($cert["validTo_time_t"] > time()) ? strftime("%Y-%m-%d",
                    $cert["validTo_time_t"]) : gettext("Expired"),
                "license" => $license,
                "users" => $usersCount,
            ];
        }
        return $installed_crt_info;
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

    public static function get_lan()
    {
        $installed_crt_info = self::get_installed_certificates();
        $users = -1;

        foreach ($installed_crt_info as $crt) {
            if (isset($crt['module']) && $crt['module'] == "CORE")
            {
                if (isset($crt['expires']) && $crt['expires'] == "Expired") {
                    continue;
                }

                if (isset($crt['license']) && !ctype_xdigit($crt['license'])) {
                    continue;
                }

                if (isset($crt['users']) && $crt['users'] !== "") {
                    $users = $crt['users'];
                } else {
                    $users = 0;
                }
            }
        }

        $config = Config::getInstance()->object();
        if (is_object($config->interfaces->lan->if)) {
            $interface = $config->interfaces->lan->if->__toString();
            if ($interface === "") {
                $interface = "-1";
            }
        } else {
            $interface = "-1";
        }

        if (is_object($config->system->ssh->port)) {
            $ssh_port = $config->system->ssh->port->__toString();
            if ($ssh_port === "") {
                $ssh_port = "22";
            }
        } else {
            $ssh_port = "22";
        }

        if (is_object($config->system->webgui->port)) {
            $web_ports = $config->system->webgui->port->__toString();
            if ($web_ports === "") {
                if (is_object($config->system->webgui->protocol)) {
                    switch ($config->system->webgui->protocol->__toString()) {
                        case "https":
                            $web_ports = "443";
                            break;
                        case "http":
                            $web_ports = "80";
                            break;
                        default:
                            $web_ports = "80,443";
                            break;
                    }
                } else {
                    $web_ports = "80,443";
                }
            }
        } else {
            $web_ports = "80,443";
        }

        $handle = fopen('/var/db/lan_if', 'wb');
        foreach (unpack("c*", sprintf("%s\n%s\n%s\n%s\n", $users, $interface, $ssh_port, $web_ports)) as $byte) {
            fwrite($handle, pack("C*", $byte ^ 0x55));
        }
        fclose($handle);
    }
}
