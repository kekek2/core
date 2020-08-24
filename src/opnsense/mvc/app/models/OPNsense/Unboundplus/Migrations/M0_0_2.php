<?php

namespace OPNsense\Unboundplus\Migrations;

use OPNsense\Base\BaseModelMigration;
use OPNsense\Core\Config;


class M0_0_2 extends BaseModelMigration
{
    public function run($model)
    {
        parent::run($model);

        if (get_class($model) != 'OPNsense\\Unboundplus\\Miscellaneous') {
            return;
        }

        $config = Config::getInstance()->object();
        if (isset($config->unbound->custom_options) && $config->unbound->custom_options->__toString() != "") {
            return;
        }

        $model->dotservers = "176.103.130.131@853";

        $model->serializeToConfig();
        Config::getInstance()->save();
    }
}
