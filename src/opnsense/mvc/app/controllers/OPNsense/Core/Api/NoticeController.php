<?php

namespace OPNsense\Core\Api;

use \OPNsense\Base\ApiControllerBase;

require_once("notices.inc");

class NoticeController extends ApiControllerBase
{
    public function listAction()
    {
        if (!are_notices_pending())
            return [];

        $notices = get_notices();
        if (!is_array($notices))
            return [];

        $result = [];
        foreach ($notices as $key => $value)
            $result[] = ["key" => $key, "txt" => preg_replace("/(\"|\'|\n|<.?\w+>)/i", "", ($value['notice'] != "" ? $value['notice'] : $value['id']))];

        return $result;
    }

    public function closeAction()
    {
        if (!$this->request->isPost() || !$this->request->hasPost("closenotice"))
            return [];
        close_notice($this->request->getPost("closenotice"));
        return [];
    }
}
