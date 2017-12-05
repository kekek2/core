<?php
/**
 *    Copyright (C) 2016 E. Bevz & Deciso B.V.
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
namespace OPNsense\Syslog\Api;

use \OPNsense\Base\ApiControllerBase;
use \OPNsense\Syslog\Syslog;
use \OPNsense\Core\Config;
use \OPNsense\Core\Backend;     // for run firewall and web-server reconfigure actions
use \OPNsense\Base\UIModelGrid;

/**
 * Class SettingsController
 * @package OPNsense\Syslog
 */
class SettingsController extends ApiControllerBase
{
    /**
     * retrieve syslog settings
     * @return array
     */
    public function getAction()
    {
        $result = array();
        if ($this->request->isGet()) {
            $mdl = new Syslog();
            $result['syslog'] = $mdl->getNodes();
        }

        return $result;
    }


    /**
     * update syslog configuration fields
     * @return array
     * @throws \Phalcon\Validation\Exception
     */
    public function setAction()
    {
        $result = array("result"=>"failed");
        try{
            if ($this->request->hasPost("syslog")) {
                $this->sessionClose();

                // load model and update with provided data
                $mdl = new Syslog();
                $formData = $this->request->getPost("syslog");
                $mdl->setNodes($formData);

                $firewallChanged =  $mdl->Firewall->LogDefaultBlock->isFieldChanged() ||
                                    $mdl->Firewall->LogDefaultPass->isFieldChanged() ||
                                    $mdl->Firewall->LogBogons->isFieldChanged() ||
                                    $mdl->Firewall->LogPrivateNets->isFieldChanged();

                $webConfigChanged = $mdl->LogWebServer->isFieldChanged();

                // for bad dependencies
                $backend = new Backend();

                // hook to calculate bind Source IP
                $bindip = "";
                if(isset($formData["Remote"]["SourceIP"]) && $formData["Remote"]["SourceIP"] != "")
                {
                    $sourceip = $formData["Remote"]["SourceIP"];
                    $proto = $formData["Remote"]["Proto"];
                    $bindip = chop($backend->configdRun("syslog get_bind_address {$sourceip} {$proto}")); 
                    
                    // additional sanity check
                    if($bindip != "" && inet_pton($bindip) === false)
                        $bindip = "";
                }
                if($bindip != $mdl->Remote->BindAddress->__toString())
                    $mdl->Remote->BindAddress = $bindip;

                // perform validation
                $valMsgs = $mdl->performValidation();
                foreach ($valMsgs as $field => $msg) {
                    $result["validations"] = array();
                    $result["validations"]["syslog.".$msg->getField()] = $msg->getMessage();
                }

                // serialize model to config and save
                if ($valMsgs->count() == 0) {
                    $mdl->serializeToConfig();
                    $cnf = Config::getInstance();
                    $cnf->save();
                    $result["result"] = "ok";

                    if ($firewallChanged)
                        $result["reload-firewall"] = $backend->configdRun("filter reload", true);

                    if ($webConfigChanged)
                        $result["reload-web"] = $backend->configdRun("syslog restart_web", true);

                    $result["reload-pflog"] = $backend->configdRun("filter pflog start", true);
                }
            }
        }
        catch(Exception $e){
            $result["validations"] = $e->getMessage();
        }
        return $result;
    }

    /**
     * search sources
     * @return array
     */
    public function searchCategoriesAction()
    {
        $this->sessionClose();
        $mdl = new Syslog();
        $grid = new UIModelGrid($mdl->LogCategories->Category);
        $result = $grid->fetchBindRequest(
            $this->request,
            array("Description", "LogRemote", "Name"),
            "Description"
        );

        // let's translate
        for ($i = 0; $i < count($result["rows"]); $i++) {
            $result["rows"][$i]["Description"] = gettext($result["rows"][$i]["Description"]);
        }
        return $result;
    }

    /**
     * toggle source remote logging property
     * @return array
     */
    public function toggleCategoryRemoteAction($uuid, $enabled = null)
    {
        $result = array("result" => "failed", "validations" => array());
        if ($this->request->isPost()) {
            $mdl = new Syslog();
            if ($uuid != null) {
                $node = $mdl->getNodeByReference('LogCategories.Category.' . $uuid);
                if ($node != null) {
                    if ($enabled == "0" || $enabled == "1") {
                        $node->LogRemote = (string)$enabled;
                    } elseif ($node->LogRemote->__toString() == "1") {
                        $node->LogRemote = "0";
                    } else {
                        $node->LogRemote = "1";
                    }
                    $result['result'] = "success";
                    // if item has toggled, serialize to config and save
                    $valMsgs = $mdl->performValidation();
                    foreach ($valMsgs as $field => $msg) {
                        // replace absolute path to attribute for relative one at uuid.
                        $result["validations"][$msg->getField()] = $msg->getMessage();
                    }
                    if($valMsgs->count() == 0)
                    {
                        $mdl->serializeToConfig();
                        Config::getInstance()->save();
                    }
                }
            }
        }
        return $result;
    }
}
