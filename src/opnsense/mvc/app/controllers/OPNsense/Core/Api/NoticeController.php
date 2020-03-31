<?php

namespace OPNsense\Core\Api;

use \OPNsense\Base\ApiControllerBase;
use \OPNsense\Core\Notices;
use \SmartSoft\Core\Tools;

class NoticeController extends ApiControllerBase
{
    public function listAction()
    {
        $Notices = new Notices();

        $result = [];
        foreach ($Notices->getNotices() as $value)
            $result[] = ["key" => $value['datetime'], "txt" => preg_replace("/(\"|\'|\n|<.?\w+>)/i", "", $value['message'])];

        return $result;
    }

    public function closeAction()
    {
        if (!$this->request->isPost() || !$this->request->hasPost("closenotice"))
            return [];
        $Notices = new Notices();
        $Notices->delNotice($this->request->getPost("closenotice"));
        return [];
    }

    public function bannerAction()
    {
        $banner = '';
        if (file_exists("/usr/local/opnsense/version/banner")) {
            $banner = file_get_contents("/usr/local/opnsense/version/banner");
        }

        return ['banner' => $banner];
    }

    public function expireAction()
    {
        foreach (Tools::get_installed_certificates() as $cert)
        {
            if ($cert["module"] != 'CORE')
            {
                continue;
            }
            if ($cert["validTo_time_t"] < time())
            {
                return ['expire' => gettext("Your license has been expired. No more updates.")];
            }
            if ($cert["validTo_time_t"]  - time() < 60 * 60 * 24 * 30)
            {
                return ['expire' => sprintf(gettext("WARNING! Your licence will expire after %d days."), ($cert["validTo_time_t"] - time()) / (60 * 60 * 24))];
            }
        }
        return ['expire' => ""];
    }
}
