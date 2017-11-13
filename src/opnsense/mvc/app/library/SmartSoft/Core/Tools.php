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
}