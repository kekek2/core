<?php

namespace OPNsense\Proxy\Migrations;

use OPNsense\Base\BaseModelMigration;
use OPNsense\Core\Config;


class M1_0_2 extends BaseModelMigration
{
    public function run($model)
    {
        function decode($domains)
        {
            if ($domains == "") {
                return "";
            }
            $result = [];
            foreach (explode(",", $domains) as $domain) {
                $result[] = ($domain[0] == "." ? "." : "") . idn_to_utf8($domain);
            }
            return implode(",", $result);
        }

        parent::run($model);

        if (($whiteList = $model->forward->acl->whiteList) != null) {
            $model->forward->acl->whiteList = decode($whiteList->__toString());
        }
        if (($blackList = $model->forward->acl->blackList) != null) {
            $model->forward->acl->blackList = decode($blackList->__toString());
        }
        if (($exclude = $model->forward->icap->exclude) != null) {
            $model->forward->icap->exclude = decode($exclude->__toString());
        }

        $model->serializeToConfig();
        Config::getInstance()->save();
    }
}
