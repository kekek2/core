<?php

namespace SmartSoft\TING\Migrations;

use OPNsense\Base\BaseModelMigration;
use OPNsense\Core\Config;


class M0_0_2 extends BaseModelMigration
{
    public function run($model)
    {
        parent::run($model);

	$cfg = Config::getInstance();
	$cfgArray = $cfg->toArray(array_flip(["rule"]));
	foreach ($cfgArray['filter']['rule'] as $rule) {
	    $rule['descr'] = preg_replace("/\r|\n/", "", $rule['descr']);
    }
	$cfg->fromArray($cfgArray);
        $cfg->save();
    }
}
