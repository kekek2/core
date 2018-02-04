<?php

/*
 * Copyright (C) 2015 Deciso B.V.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 * INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 * AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 * OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */

namespace OPNsense\Proxy\Api;

use \OPNsense\Base\ApiMutableServiceControllerBase;
use \OPNsense\Core\Backend;
use \OPNsense\Proxy\Proxy;
use \OPNsense\Core\Config;

/**
 * Class ServiceController
 * @package OPNsense\Proxy
 */
class ServiceController extends ApiMutableServiceControllerBase
{
    static protected $internalServiceClass = '\OPNsense\Proxy\Proxy';
    static protected $internalServiceEnabled = 'general.enabled';
    static protected $internalServiceTemplate = 'OPNsense/Proxy';
    static protected $internalServiceName = 'proxy';

    /**
     * reconfigure hook
     */
    protected function reconfigureForceRestart()
    {
        function disableRule($port)
        {
            $nat_delete = false;
            $config = Config::getInstance()->toArray();
            if (isset($config["nat"]["rule"]))
                foreach ($config["nat"]["rule"] as $nat_key => $nat)
                    if (!isset($nat["disabled"]) && isset($nat["target"]) && isset($nat["local-port"]) && $nat["protocol"] = "tcp" && in_array($nat["target"], ["127.0.0.1", "localhost"]) && $nat["local-port"] == $port)
                    {
                        Config::getInstance()->object()->nat->rule[$nat_key]->disabled = "1";
                        if (isset($nat["associated-rule-id"]))
                            foreach (Config::getInstance()->toArray()["filter"]["rule"] as $filter_key => $filter)
                                if (!isset($filter["disabled"]) && isset($filter["associated-rule-id"]) && $filter["associated-rule-id"] == $nat["associated-rule-id"])
                                    Config::getInstance()->object()->filter->rule[$filter_key]->disabled = "1";
                        $nat_delete = true;
                    }
            return $nat_delete;
        }

        $mdlProxy = new Proxy();

        $http_delete = (string) $mdlProxy->forward->transparentMode == "0" && disableRule($mdlProxy->forward->port);
        $https_delete = (string) $mdlProxy->forward->sslbump == "0" && disableRule($mdlProxy->forward->sslbumpport);

        if ($http_delete || $https_delete)
        {
            Config::getInstance()->save();
            $backend = new Backend();
            $backend->configdRun("filter reload");
        }

        // some operations can not be performed by a squid -k reconfigure,
        // try to determine if we need a stop/start here
        $prev_sslbump_cert = trim(@file_get_contents('/var/squid/ssl_crtd.id'));
        $prev_cache_active = !empty(trim(@file_get_contents('/var/squid/cache/active')));

        return (((string)$mdlProxy->forward->sslcertificate) != $prev_sslbump_cert) ||
            (!empty((string)$mdlProxy->general->cache->local->enabled) != $prev_cache_active);
    }

    /**
     * fetch acls (download + install)
     * @return array
     */
    public function fetchaclsAction()
    {
        if ($this->request->isPost()) {
            // close session for long running action
            $this->sessionClose();

            $backend = new Backend();
            // generate template
            $backend->configdRun('template reload OPNsense/Proxy');

            // fetch files
            $response = $backend->configdRun("proxy fetchacls");
            return array("response" => $response,"status" => "ok");
        } else {
            return array("response" => array());
        }
    }

    /**
     * download (only) acls
     * @return array
     */
    public function downloadaclsAction()
    {
        if ($this->request->isPost()) {
            // close session for long running action
            $this->sessionClose();

            $backend = new Backend();
            // generate template
            $backend->configdRun('template reload OPNsense/Proxy');

            // download files
            $response = $backend->configdRun("proxy downloadacls");
            return array("response" => $response,"status" => "ok");
        } else {
            return array("response" => array());
        }
    }
}
