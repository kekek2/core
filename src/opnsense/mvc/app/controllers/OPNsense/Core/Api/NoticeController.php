<?php

namespace OPNsense\Core\Api;

use \OPNsense\Base\ApiControllerBase;
use \OPNsense\Core\Notices;

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
}
