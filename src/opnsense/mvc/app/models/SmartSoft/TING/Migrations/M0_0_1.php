<?php

namespace SmartSoft\TING\Migrations;

use OPNsense\Base\BaseModelMigration;
use OPNsense\Core\Config;


class M0_0_1 extends BaseModelMigration
{
    public function run($model)
    {
        parent::run($model);

	$cfg = Config::getInstance();
	$cfgArray = $cfg->toArray();
	$cfgArray['theme'] = 'ting';
	$cfg->fromArray($cfgArray);
        $cfg->save();
    }
}
