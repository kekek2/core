<?php
/**
 *    Copyright (C) 2016 Deciso B.V.
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
 *      plugin_syslog return data format:
 *
 *      array(
 *          'log_name' => array(                                    # mapped to log target; real log file name will be /var/log/<log_name>.log
 *              'facility'  => array('program_1', 'program_2',...), # mapped to log program
 *              'remote'    => 'remote_log_group_name',             # mapped to category; remote logging group name
 *              'local'     => '/path/to/local/log-socket',         # local log-socket path; added to listening
 *
 *
 *              # parameters for external logfiles, managed by logview controller, but not managed by syslog
 *              # if is set, then syslog ignores this target
 *
 *              'external'  => array(
 *                  'filename'          => '/path/to/logfile',      # abcolute path to log file
 *                  'timestamp_pattern' => '/^([^\,]+),/',          # tamestamp pattern to extract timestamp from log entry, default is Syslog::$TIMESTAMP_PATTERN
 *               ),
 *          )
 *      );
 *
 *
 */
namespace OPNsense\Syslog;

require_once("plugins.inc"); // can be removed, see checkPredefinedTargets()

use OPNsense\Core\Config;
use OPNsense\Core\Backend;
use OPNsense\Base\BaseModel;
use OPNsense\Base\ModelException;


// TODO: bind_address select in UI
// TODO: sanitize socket path, see setLocalSocket()

/**
 * Class Syslog
 * @package OPNsense\Syslog
 */
class Syslog extends BaseModel
{
    private static $LOGS_DIRECTORY = "/var/log";
    private static $TIMESTAMP_PATTERN = '/^([\S]+\s+[\S]+\s+[\S]+)\s/';

    private $Modified = false;
    private $BatchMode = false;

    private $AllTargets = array();
    private $AllCategories = array();

    public $Enabled = 1;

    private function getPredefinedTargets()
    {
        return array(
        array('program' => 'filterlog',                                           'filter' => '*.*',  'type' => 'file', 'target' => self::$LOGS_DIRECTORY.'/filter.log',  'category' => 'filter'),
        array('program' => 'dpinger',                                     'filter' => '*.*',  'type' => 'file', 'target' => self::$LOGS_DIRECTORY.'/gateways.log','category' => 'gateways'),
        array('program' => 'captiveportal',                                       'filter' => '*.*',  'type' => 'file', 'target' => self::$LOGS_DIRECTORY.'/portalauth.log','category' => 'portalauth'),
        array('program' => 'ppp',                                                 'filter' => '*.*',  'type' => 'file', 'target' => self::$LOGS_DIRECTORY.'/ppps.log',    'category' => null),
        array('program' => 'relayd',                                              'filter' => '*.*',  'type' => 'file', 'target' => self::$LOGS_DIRECTORY.'/relayd.log',  'category' => 'relayd'),
        array('program' => 'filterdns,unbound',                                   'filter' => '*.*',  'type' => 'file', 'target' => self::$LOGS_DIRECTORY.'/resolver.log','category' => 'dns'),
        array('program' => 'radvd,routed,rtsold,olsrd,zebra,ospfd,bgpd,miniupnpd','filter' => '*.*',  'type' => 'file', 'target' => self::$LOGS_DIRECTORY.'/routing.log', 'category' => null),
        array('program' => 'hostapd',                                             'filter' => '*.*',  'type' => 'file', 'target' => self::$LOGS_DIRECTORY.'/wireless.log','category' => 'wireless'),
        array('program' => 'pkg,pkg-static',                                      'filter' => '*.*',  'type' => 'file', 'target' => self::$LOGS_DIRECTORY.'/pkg.log',     'category' => null),

        array('program' => null,  'filter' => 'local3.*',                             'type' => 'file',   'target' => self::$LOGS_DIRECTORY.'/vpn.log',   'category' => 'vpn'),
        array('program' => null,  'filter' => 'local4.*',                             'type' => 'file',   'target' => self::$LOGS_DIRECTORY.'/portalauth.log', 'category' => 'portalauth'),
        array('program' => null,  'filter' => 'local7.*',                             'type' => 'file',   'target' => self::$LOGS_DIRECTORY.'/dhcpd.log', 'category' => 'dhcp'),
        array('program' => null,  'filter' => '*.notice;kern.debug;lpr.info;mail.crit;daemon.none;news.err;local0.none;local3.none;local4.none;local7.none;security.*;auth.info;authpriv.info;daemon.info',
                                                                                      'type' => 'file',   'target' => self::$LOGS_DIRECTORY.'/system.log','category' => 'system'),
        array('program' => null,  'filter' => '*.emerg',                              'type' => 'all',    'target' => '*',                                'category' => 'system'),
        );
    }

    // WARNING: in legacy code was selector "local7.*" for remote logging only , mapped to portalauth category ???

    /*************************************************************************************************************
     * Public API
     *************************************************************************************************************/

    /**
     * Set Syslog target.
     * @param $source program name, null if no program name
     * @param $filter comma-separated list of selectors facility.level (without spaces)
     * @param $type type of action (file, pipe, remote, all)
     * @param $target action target
     * @param $category log category mapping, null if no category
     * @throws \ModelException
     */
    public function setTarget($source, $filter, $type, $target, $category, $pid = '', $signum = '')
    {
        $source = str_replace(' ', '', $source);
        $filter = str_replace(' ', '', $filter);
        $type = trim($type);
        $target = trim($target);
        $category = trim($category);
        $pid = trim($pid);
        $signum = trim($signum);

        $this->setSource($source);

        // we would not add category if it not exists

        foreach($this->LogTargets->Target->__items as $uuid => $item)
        {
            if($item->Source->__toString() == $source
            && $item->Filter->__toString() == $filter
            && $item->ActionType->__toString() == $type
            && $item->Target->__toString() == $target
            && $item->Category->__toString() == $category
            && $item->PID->__toString() == $pid
            && $item->SigNum->__toString() == $signum) {
                $this->AllTargets[] = $uuid;
                return;
            }
        }

        $item = $this->LogTargets->Target->add();
        $item->Source = $source;
        $item->Filter = $filter;
        $item->ActionType = $type;
        $item->Target = $target;
        $item->Category = $category;
        $item->PID = $pid;
        $item->SigNum = $signum;

        $this->AllTargets[] = $item->getAttributes()["uuid"];

        $this->Modified = true;
        $this->saveIfModified();
    }

    /**
     * Remove Syslog target.
     * @param $source program name, null if no program name
     * @param $filter comma-separated list of selectors facility.level (without spaces)
     * @param $type type of action (file, pipe, remote, all)
     * @param $target action target
     * @throws \ModelException
     */
    public function delTarget($source, $filter, $type, $target)
    {
        $source = str_replace(' ', '', $source);
        $filter = str_replace(' ', '', $filter);
        $type = trim($type);
        $target = trim($target);

        foreach($this->LogTargets->Target->__items as $uuid => $item)
        {
            if($item->Source->__toString() == $source
            && $item->Filter->__toString() == $filter
            && $item->ActionType->__toString() == $type
            && $item->Target->__toString() == $target)
            {
                $this->LogTargets->Target->del($uuid);
                $this->Modified = true;
                $this->saveIfModified();
            }
        }
    }

    /**
     * Add or update Syslog category. For mapping in GUI.
     * @param $name category name
     * @param $description category description
     * @throws \ModelException
     */
    public function setCategory($name, $description)
    {
        $name = trim($name);
        $description = trim($description);

        foreach($this->LogCategories->Category->__items as $uuid => $category)
        {
            if($category->Name->__toString() == $name)
            {
                $this->AllCategories[] = $uuid;

                if($category->Description->__toString() == $description)
                    return;

                $category->Description = $description;
                $this->Modified = true;
                $this->saveIfModified();
                return;
            }
        }

        $category = $this->LogCategories->Category->add();
        $category->Name = $name;
        $category->Description = $description;
        $this->AllCategories[] = $category->getAttributes()["uuid"];
        $this->Modified = true;
        $this->saveIfModified();
    }

    /**
     * Add or update Syslog category. For mapping in GUI.
     * @param $name category name
     * @param $description category description
     * @throws \ModelException
     */
    public function setLocalSocket($path)
    {
        $path = trim($path);

        foreach($this->LocalSockets->Socket->__items as $uuid => $socket)
        {
            if($socket->Path->__toString() == $path)
                return;
        }

        $socketdir = dirname($path);
        if (!is_dir($socketdir)) {
            mkdir($socketdir, 0777, true);
        }

        $socket = $this->LocalSockets->Socket->add();
        $socket->Path = $path;
        $this->Modified = true;
        $this->saveIfModified();
    }

    /**
     * get full logfile path
     * @param $logname name of log
     */
    public function getLogFileName($logname)
    {
        // scan defined targets
        foreach($this->LogTargets->Target->__items as $uuid => $target)
            if(basename($target->Target->__toString(), '.log') == $logname)
                return $target->Target->__toString();

        // scan plugins for external logfiles
        $plugins_data = plugins_syslog();
        foreach($plugins_data as $name => $params)
        {
            if($name == $logname && isset($params['external'])) {
                return $params['external']['filename'];
            }
        }

        return '';
    }

    /**
     * get full logfile datetime pattern
     * @param $logname name of log
     */
    public function getDateTimePattern($logname)
    {
        // scan defined targets
        foreach($this->LogTargets->Target->__items as $uuid => $target)
            if(basename($target->Target->__toString(), '.log') == $logname)
                return self::$TIMESTAMP_PATTERN;

        // scan plugins for external logfiles
        $plugins_data = plugins_syslog();
        foreach($plugins_data as $name => $params)
        {
            if($name == $logname && isset($params['external'])) {
                if(isset($params['external']['timestamp_pattern']))
                    return $params['external']['timestamp_pattern'];
            }
        }

        return self::$TIMESTAMP_PATTERN;;
    }

    /**
     * cna clear log?
     * @param $logname name of log
     */
    public function canClearLog($logname)
    {
        // scan defined targets
        foreach($this->LogTargets->Target->__items as $uuid => $target)
            if(basename($target->Target->__toString(), '.log') == $logname)
                return true;

        return false;
    }

    /*************************************************************************************************************
     * Protected Area
     *************************************************************************************************************/

    protected function init()
    {
        $this->BatchMode = true;
        $this->checkPredefinedCategories();
        $this->checkPredefinedTargets();

        // cleanup old targets
        foreach($this->LogTargets->Target->__items as $uuid => $item)
        {
            if(!in_array($uuid, $this->AllTargets)) {
                $this->LogTargets->Target->del($uuid);
                $this->Modified = true;
            }
        }
        // cleanup old categories
        foreach($this->LogCategories->Category->__items as $uuid => $item)
        {
            if(!in_array($uuid, $this->AllCategories)) {
                $this->LogCategories->Category->del($uuid);
                $this->Modified = true;
            }
        }
        $this->BatchMode = false;

        $this->saveIfModified();
    }

    private function checkPredefinedCategories()
    {
        // do not write localized categories descriptions to config.xml
        $this->setCategory('system',    'System events');
        $this->setCategory('dhcp',      'DHCP service events');
        $this->setCategory('filter',    'Firewall events');
        $this->setCategory('gateways',  'Gateway Monitor events');
        $this->setCategory('portalauth','Portal Auth events');
        $this->setCategory('relayd',    'Server Load Balancer events');
        $this->setCategory('resolver',  'Domain name resolver events');
        $this->setCategory('wireless',  'Wireless events');
        $this->setCategory('vpn',       'VPN (PPTP, IPsec, OpenVPN) events');

        // hook to include category names to gettext files
        $translate = [
            gettext('System events'),
            gettext('DHCP service events'),
            gettext('Firewall events'),
            gettext('Gateway Monitor events'),
            gettext('Portal Auth events'),
            gettext('Server Load Balancer events'),
            gettext('Domain name resolver events'),
            gettext('Wireless events'),
            gettext('VPN (PPTP, IPsec, OpenVPN) events')
        ];
    }

    private function checkPredefinedTargets()
    {
        foreach($this->getPredefinedTargets() as $target)
        {
            $this->setTarget($target['program'], $target['filter'], $target['type'], $target['target'], $target['category']);
        }

        // NOTE: in more convivient way, plugins can set targets in setup script by Syslog::setTarget() call

        // scan plugins
        $plugins_data = plugins_syslog();
        foreach($plugins_data as $name => $params)
        {
            if(isset($params['external'])) {
                continue;
            }
            $program = join(",", $params['facility']);
            $target =  self::$LOGS_DIRECTORY."/".$name.".log";
            $category = isset($params['remote']) ? $params['remote'] : null;
            $pid = isset($params['pid']) ? $params['pid'] : null;
            $signum = isset($params['pid']) && isset($params['signum']) ? $params['signum'] : null;
            $this->setTarget($program, '*.*', 'file', $target, $category, $pid, $signum);
            if(isset($params['local']))
            {
                $this->setLocalSocket($params['local']);
            }
        }
    }

    /**
    * Set Syslog source.
    * @param $program category name
    * @throws \ModelException
    */
    public function setSource($program)
    {
        $program = str_replace(' ', '', $program);

        if($program == '')
            return;

        foreach($this->LogSources->Source->__items as $uuid => $source)
        {
            if($source->Program->__toString() == $program)
                return;
        }

        $source = $this->LogSources->Source->add();
        $source->Program = $program;
        $this->Modified = true;
        $this->saveIfModified();
        return;
    }

    /**
    * Save Syslog config if modified.
    * @throws \Phalcon\Validation\Exception
    */
    private function saveIfModified()
    {

        if($this->BatchMode === true)
            return;

        if($this->Modified === false)
            return;

        $this->serializeToConfig();
        Config::getInstance()->save();
        $this->Modified = false;
    }

}
