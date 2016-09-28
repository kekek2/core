<?php
/**
 *    Copyright (C) 2015 J. Schellevis - Deciso B.V.
 *
 *    All rights reserved.
 *
 *    Redistribution and use in source and binary forms, with or without
 *    modification, are permitted provided that the following conditions are met:
 *
 *    1. Redistributions of source code must retain the above copyright notice,
 *       this list of conditions and the following disclaimer.
 *
 *    2. Redistributions in binary form must reproduce the above copyright
 *       notice, this list of conditions and the following disclaimer in the
 *       documentation and/or other materials provided with the distribution.
 *
 *    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 *    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 *    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 *    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 *    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 *    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 *    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 *    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 *    POSSIBILITY OF SUCH DAMAGE.
 *
 */
namespace OPNsense\Proxy\Api;

use \OPNsense\Base\ApiMutableModelControllerBase;
use \OPNsense\Cron\Cron;
use \OPNsense\Core\Config;
use \OPNsense\Base\UIModelGrid;

// For configure root crontab
require_once("util.inc");
require_once("services.inc");
include_once('/usr/local/opnsense/contrib/simplepie/idn/idna_convert.class.php');

/**
 * Class SettingsController
 * @package OPNsense\Proxy
 */
class SettingsController extends ApiMutableModelControllerBase
{
    static protected $internalModelName = 'proxy';
    static protected $internalModelClass = '\OPNsense\Proxy\Proxy';

    protected function setActionHook()
    {
        // ugly hack
        global $config;
        $config = Config::getInstance()->toArray();
        configure_cron();
    }

    /**
     *
     * search remote blacklists
     * @return array
     */
    public function searchRemoteBlacklistsAction()
    {
        $this->sessionClose();
        $mdlProxy = $this->getModel();
        $grid = new UIModelGrid($mdlProxy->forward->acl->remoteACLs->blacklists->blacklist);
        return $grid->fetchBindRequest(
            $this->request,
            array("enabled", "filename", "url", "description"),
            "description"
        );
    }

    /**
     * retrieve remote blacklist settings or return defaults
     * @param $uuid item unique id
     * @return array
     */
    public function getRemoteBlacklistAction($uuid = null)
    {
        $mdlProxy = $this->getModel();
        if ($uuid != null) {
            $node = $mdlProxy->getNodeByReference('forward.acl.remoteACLs.blacklists.blacklist.' . $uuid);
            if ($node != null) {
                // return node
                return array("blacklist" => $node->getNodes());
            }
        } else {
            // generate new node, but don't save to disc
            $node = $mdlProxy->forward->acl->remoteACLs->blacklists->blacklist->add();
            return array("blacklist" => $node->getNodes());
        }
        return array();
    }


    /**
     * update remote blacklist item
     * @param string $uuid
     * @return array result status
     * @throws \Phalcon\Validation\Exception
     */
    public function setRemoteBlacklistAction($uuid)
    {
        if ($this->request->isPost() && $this->request->hasPost("blacklist")) {
            $mdlProxy = $this->getModel();
            if ($uuid != null) {
                $node = $mdlProxy->getNodeByReference('forward.acl.remoteACLs.blacklists.blacklist.' . $uuid);
                if ($node != null) {
                    $result = array("result" => "failed", "validations" => array());
                    $blacklistInfo = $this->request->getPost("blacklist");

                    $node->setNodes($blacklistInfo);
                    $valMsgs = $mdlProxy->performValidation();
                    foreach ($valMsgs as $field => $msg) {
                        $fieldnm = str_replace($node->__reference, "blacklist", $msg->getField());
                        $result["validations"][$fieldnm] = $msg->getMessage();
                    }

                    if (count($result['validations']) == 0) {
                        // save config if validated correctly
                        $mdlProxy->serializeToConfig();
                        Config::getInstance()->save();
                        $result = array("result" => "saved");
                    }
                    return $result;
                }
            }
        }
        return array("result" => "failed");
    }

    /**
     * add new blacklist and set with attributes from post
     * @return array
     */
    public function addRemoteBlacklistAction()
    {
        $result = array("result" => "failed");
        if ($this->request->isPost() && $this->request->hasPost("blacklist")) {
            $result = array("result" => "failed", "validations" => array());
            $mdlProxy = $this->getModel();
            $node = $mdlProxy->forward->acl->remoteACLs->blacklists->blacklist->Add();
            $node->setNodes($this->request->getPost("blacklist"));
            $valMsgs = $mdlProxy->performValidation();

            foreach ($valMsgs as $field => $msg) {
                $fieldnm = str_replace($node->__reference, "blacklist", $msg->getField());
                $result["validations"][$fieldnm] = $msg->getMessage();
            }

            if (count($result['validations']) == 0) {
                // save config if validated correctly
                $mdlProxy->serializeToConfig();
                Config::getInstance()->save();
                $result = array("result" => "saved");
            }
            return $result;
        }
        return $result;
    }

    /**
     * delete blacklist by uuid
     * @param $uuid item unique id
     * @return array status
     */
    public function delRemoteBlacklistAction($uuid)
    {

        $result = array("result" => "failed");

        if ($this->request->isPost()) {
            $mdlProxy = $this->getModel();
            if ($uuid != null) {
                if ($mdlProxy->forward->acl->remoteACLs->blacklists->blacklist->del($uuid)) {
                    // if item is removed, serialize to config and save
                    $mdlProxy->serializeToConfig();
                    Config::getInstance()->save();
                    $result['result'] = 'deleted';
                } else {
                    $result['result'] = 'not found';
                }
            }
        }
        return $result;
    }

    /**
     * toggle blacklist by uuid (enable/disable)
     * @param $uuid item unique id
     * @return array status
     */
    public function toggleRemoteBlacklistAction($uuid)
    {

        $result = array("result" => "failed");

        if ($this->request->isPost()) {
            $mdlProxy = $this->getModel();
            if ($uuid != null) {
                $node = $mdlProxy->getNodeByReference('forward.acl.remoteACLs.blacklists.blacklist.' . $uuid);
                if ($node != null) {
                    if ($node->enabled->__toString() == "1") {
                        $result['result'] = "Disabled";
                        $node->enabled = "0";
                    } else {
                        $result['result'] = "Enabled";
                        $node->enabled = "1";
                    }
                    // if item has toggled, serialize to config and save
                    $mdlProxy->serializeToConfig();
                    Config::getInstance()->save();
                }
            }
        }
        return $result;
    }

    /**
     * create new cron item for remote acl or return already available one
     * @return array status action
     */
    public function fetchRBCronAction()
    {
        $result = array("result" => "failed");

        if ($this->request->isPost()) {
            $mdlProxy = $this->getModel();
            if ((string)$mdlProxy->forward->acl->remoteACLs->UpdateCron == "") {
                $mdlCron = new Cron();
                // update cron relation (if this doesn't break consistency)
                $uuid = $mdlCron->newDailyJob("Proxy", "proxy fetchacls", "fetch proxy acls", "1");
                $mdlProxy->forward->acl->remoteACLs->UpdateCron = $uuid;

                if ($mdlCron->performValidation()->count() == 0) {
                    $mdlCron->serializeToConfig();
                    // save data to config, do not validate because the current in memory model doesn't know about the
                    // cron item just created.
                    $mdlProxy->serializeToConfig($validateFullModel = false, $disable_validation = true);
                    Config::getInstance()->save();
                    $result['result'] = "new";
                    $result['uuid'] = $uuid;
                } else {
                    $result['result'] = "unable to add cron";
                }
            } else {
                $result['result'] = "existing";
                $result['uuid'] = (string)$mdlProxy->forward->acl->remoteACLs->UpdateCron;
            }
        }

        return $result;
    }

    /**
     *
     * search group ACLs
     * @return array
     */
    public function searchGroupACLAction()
    {
        $this->sessionClose();
        $mdlProxy = $this->getModel();
        $grid = new UIModelGrid($mdlProxy->forward->acl->groupACLs->groupACL);
        $data = $grid->fetchBindRequest(
            $this->request,
            array("groupName", "groupWhiteList", "groupBlackList", "uuid")
        );
        // expand lists
        foreach ($data['rows'] as $key => $value) {
            $uuid = $data['rows'][$key]['uuid'];
            if($uuid != null) {
                $group = $mdlProxy->getNodeByReference('forward.acl.groupACLs.groupACL.' . $uuid);
                if($group != null) {
                    $items = $group->getNodes();
                    $wls = array();
                    foreach ($items['groupWhiteList'] as $item_key => $item_val)
                        $wls[] = $item_val['value'];
                    $bls = array();
                    foreach ($items['groupBlackList'] as $item_key => $item_val)
                        $bls[] = $item_val['value'];

                    $data['rows'][$key]['groupWhiteList'] = implode(', ', $wls);
                    $data['rows'][$key]['groupBlackList'] = implode(', ', $bls);
                }
            }
        }
        return $data;
    }

    /**
     * add new group ACL and set with attributes from post
     * @return array
     */
    public function addGroupACLAction()
    {
        $result = array("result" => "failed");
        if ($this->request->isPost() && $this->request->hasPost("groupACL")) {
            $result = array("result" => "failed", "validations" => array());
            $mdlProxy = $this->getModel();
            $node = $mdlProxy->forward->acl->groupACLs->groupACL->Add();
            $node->setNodes($this->request->getPost("groupACL"));
            $valMsgs = $mdlProxy->performValidation();

            foreach ($valMsgs as $field => $msg) {
                $fieldnm = str_replace($node->__reference, "groupACL", $msg->getField());
                $result["validations"][$fieldnm] = $msg->getMessage();
            }

            if (count($result['validations']) == 0) {
                // save config if validated correctly
                $mdlProxy->serializeToConfig();
                Config::getInstance()->save();
                $result = array("result" => "saved");
            }
            return $result;
        }
        return $result;
    }

    /**
     * retrieve groupACL settings or return defaults
     * @param $uuid item unique id
     * @return array
     */
    public function getGroupACLAction($uuid = null)
    {
        $mdlProxy = $this->getModel();
        if ($uuid != null) {
            $node = $mdlProxy->getNodeByReference('forward.acl.groupACLs.groupACL.' . $uuid);
            if ($node != null) {
                // return node
                return array("groupACL" => $node->getNodes());
            }
        } else {
            // generate new node, but don't save to disc
            $node = $mdlProxy->forward->acl->groupACLs->groupACL->add();
            return array("groupACL" => $node->getNodes());
        }
        return array();
    }


    /**
     * update groupACL item
     * @param string $uuid
     * @return array result status
     * @throws \Phalcon\Validation\Exception
     */
    public function setGroupACLAction($uuid)
    {
        if ($this->request->isPost() && $this->request->hasPost("groupACL")) {
            $mdlProxy = $this->getModel();
            if ($uuid != null) {
                $node = $mdlProxy->getNodeByReference('forward.acl.groupACLs.groupACL.' . $uuid);
                if ($node != null) {
                    $result = array("result" => "failed", "validations" => array());
                    $groupACLInfo = $this->request->getPost("groupACL");

                    $node->setNodes($groupACLInfo);
                    $valMsgs = $mdlProxy->performValidation();
                    foreach ($valMsgs as $field => $msg) {
                        $fieldnm = str_replace($node->__reference, "groupACL", $msg->getField());
                        $result["validations"][$fieldnm] = $msg->getMessage();
                    }

                    if (count($result['validations']) == 0) {
                        // save config if validated correctly
                        $mdlProxy->serializeToConfig();
                        Config::getInstance()->save();
                        $result = array("result" => "saved");
                    }
                    return $result;
                }
            }
        }
        return array("result" => "failed");
    }

    /**
     * delete group ACL by uuid
     * @param $uuid item unique id
     * @return array status
     */
    public function delGroupACLAction($uuid)
    {

        $result = array("result" => "failed");

        if ($this->request->isPost()) {
            $mdlProxy = $this->getModel();
            if ($uuid != null) {
                if ($mdlProxy->forward->acl->groupACLs->groupACL->del($uuid)) {
                    // if item is removed, serialize to config and save
                    $mdlProxy->serializeToConfig();
                    Config::getInstance()->save();
                    $result['result'] = 'deleted';
                } else {
                    $result['result'] = 'not found';
                }
            }
        }
        return $result;
    }

    public function encode($node, $list)
    {
        if (!isset($node['forward']['acl']))
            return $node;
        $IDN = new \idna_convert();
        $new_list = [];
        foreach (explode(",", $node['forward']['acl'][$list]) as $site)
            $new_list[] = $IDN->encode($site);
        $node['forward']['acl'][$list] = implode(",", $new_list);
        return $node;
    }
    
    public function decode($node, $list)
    {
        $IDN = new \idna_convert();
        foreach ($node['forward']['acl'][$list] as $site => $selected)
        {
            unset($node['forward']['acl'][$list][$site]);
            $cyr = $IDN->decode($site);
            $node['forward']['acl'][$list][$cyr] = ['value' => $cyr, 'selected' => $selected['selected']];
        }
        return $node;
    }

    public function getAction()
    {
        // define list of configurable settings
        $result = array();
        if ($this->request->isGet()) {
            $result[static::$internalModelName] = $this->decode($this->decode($this->getModelNodes(), 'whiteList'), 'blackList');
        }
        return $result;
    }
    

    public function setAction()
    {
        $result = array("result"=>"failed");
        if ($this->request->isPost()) {
            // load model and update with provided data
            $mdl = $this->getModel();
            $mdl->setNodes($this->encode($this->encode($this->request->getPost(static::$internalModelName), 'whiteList'), 'blackList'));
            $result = $this->validate();
            if (empty($result['result'])) {
                $errorMessage = $this->setActionHook();
                if (!empty($errorMessage)) {
                    $result['error'] = $errorMessage;
                } else {
                    return $this->save();
                }
            }
        }
        return $result;
    }
}
