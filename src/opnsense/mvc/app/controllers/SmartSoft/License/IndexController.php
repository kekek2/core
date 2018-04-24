<?php

namespace SmartSoft\License;

class IndexController extends \OPNsense\Base\IndexController
{
	public function indexAction()
	{
		// set page title, used by the standard template in layouts/default.volt.
		// pick the template to serve to our users.
		$this->view->pick('SmartSoft/License/index');
        $this->view->formGet = $this->getForm("get_license");
	}
}
