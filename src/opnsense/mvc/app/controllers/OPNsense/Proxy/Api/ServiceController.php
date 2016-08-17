<?php
/**
 *    Copyright (C) 2015 Deciso B.V.
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

use \OPNsense\Base\ApiControllerBase;
use \OPNsense\Core\Backend;
use \OPNsense\Proxy\Proxy;
use \OPNsense\Core\Config;

/**
 * Class ServiceController
 * @package OPNsense\Proxy
 */
class ServiceController extends ApiControllerBase
{
    /**
     * start proxy service (in background)
     * @return array
     */
    public function startAction()
    {
        if ($this->request->isPost()) {
            $backend = new Backend();
            $response = $backend->configdRun("proxy start", true);
            return array("response" => $response);
        } else {
            return array("response" => array());
        }
    }

    /**
     * stop proxy service
     * @return array
     */
    public function stopAction()
    {
        if ($this->request->isPost()) {
            $backend = new Backend();
            $response = $backend->configdRun("proxy stop");
            return array("response" => $response);
        } else {
            return array("response" => array());
        }
    }

    /**
     * restart proxy service
     * @return array
     */
    public function restartAction()
    {
        if ($this->request->isPost()) {
            $backend = new Backend();
            $response = $backend->configdRun("proxy restart");
            return array("response" => $response);
        } else {
            return array("response" => array());
        }
    }

    /**
     * retrieve status of squid proxy
     * @return array
     * @throws \Exception
     */
    public function statusAction()
    {
        $backend = new Backend();
        $mdlProxy = new Proxy();
        $response = $backend->configdRun("proxy status");

        if (strpos($response, "not running") > 0) {
            if ($mdlProxy->general->enabled->__toString() == 1) {
                $status = "stopped";
            } else {
                $status = "disabled";
            }
        } elseif (strpos($response, "is running") > 0) {
            $status = "running";
        } elseif ($mdlProxy->general->enabled->__toString() == 0) {
            $status = "disabled";
        } else {
            $status = "unkown";
        }


        return array("status" => $status);
    }

    /**
     * reconfigure squid, generate config and reload
     */
    public function reconfigureAction()
    {
        if ($this->request->isPost()) {
            $force_restart = false;
            // close session for long running action
            $this->sessionClose();

            $mdlProxy = new Proxy();
            $backend = new Backend();

            $runStatus = $this->statusAction();

            // some operations can not be performed by a squid -k reconfigure,
            // try to determine if we need a stop/start here
            if (is_file('/var/squid/ssl_crtd.id')) {
                $prev_sslbump_cert = trim(file_get_contents('/var/squid/ssl_crtd.id'));
            } else {
                $prev_sslbump_cert = "";
            }
            if (((string)$mdlProxy->forward->sslcertificate) != $prev_sslbump_cert) {
                $force_restart = true;
            }

            // stop squid when disabled
            if ($runStatus['status'] == "running" &&
               ($mdlProxy->general->enabled->__toString() == 0 || $force_restart)) {
                $this->stopAction();
            }

            // generate template
            $backend->configdRun('template reload OPNsense/Proxy');

            // (res)start daemon
            if ($mdlProxy->general->enabled->__toString() == 1) {
                if ($runStatus['status'] == "running" && !$force_restart) {
                    $backend->configdRun("proxy reconfigure");
                } else {
                    $this->startAction();
                }
            }

            return array("status" => "ok");
        } else {
            return array("status" => "failed");
        }
    }


    /**
     * fetch acls (download + install)
     * @return array
     */
    public function fetchaclsAction()
    {
        if ($this->request->isPost()) {
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

    /**
     * show Kerberos keytab for Proxy
     * @return array
     */
    public function showkeytabAction()
    {
        $backend = new Backend();

        $response = $backend->configdRun("proxy showkeytab");
        return array("response" => $response,"status" => "ok");
    }

    /**
     * delete Kerberos keytab for Proxy
     * @return array
     */
    public function deletekeytabAction()
    {
        $backend = new Backend();

        $response = $backend->configdRun("proxy deletekeytab");
        return array("response" => $response,"status" => "ok");
    }

    /**
     * create Kerberos keytab for Proxy
     * @return array
     */
    public function createkeytabAction()
    {
        if ($this->request->isPost()) {
            $backend = new Backend();
            $cnf = Config::getInstance()->toArray();
            $hostname = 'HTTP/' . $cnf['system']['hostname'];
            $domain = $cnf['system']['domain'];
            $kerbname = substr(strtoupper($cnf['system']['hostname']), 0, 13) . "-K";
            $winver = $cnf['OPNsense']['proxy']['forward']['authentication']['ADKerberosImplementation'] == 'W2008' ? '2008' : '2003';
            $username = escapeshellarg($this->request->get("admin_login"));
            $pass = escapeshellarg($this->request->get("admin_password"));

            $response = $backend->configdRun("proxy createkeytab {$hostname} {$domain} {$kerbname} {$winver} {$username} {$pass}");
            return array("response" => $response,"status" => "ok");
        }

        return array("response" => array());
    }

    /**
     * test Kerberos login
     * @return array
     */
    public function testkerbloginAction()
    {
        if ($this->request->isPost()) {
            $backend = new Backend();
            $cnf = Config::getInstance()->toArray();
            $fqdn = $cnf['system']['hostname'].'.'.$cnf['system']['domain'];
            $username = escapeshellarg($this->request->get("login"));
            $pass = escapeshellarg($this->request->get("password"));

            $response = $backend->configdRun("proxy testkerblogin {$username} {$pass} {$fqdn}");
            return array("response" => $response,"status" => "ok");
        }

        return array("response" => array());
    }
}
