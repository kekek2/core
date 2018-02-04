<?php

/*
    Copyright (c) 2015 Franco Fichtner <franco@opnsense.org>
    Copyright (c) 2008 Tellnet AG
    All rights reserved.

    Redistribution and use in source and binary forms, with or without
    modification, are permitted provided that the following conditions are met:

    1. Redistributions of source code must retain the above copyright notice,
       this list of conditions and the following disclaimer.

    2. Redistributions in binary form must reproduce the above copyright
       notice, this list of conditions and the following disclaimer in the
       documentation and/or other materials provided with the distribution.

    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
    POSSIBILITY OF SUCH DAMAGE.
*/

require_once("guiconfig.inc");
require_once("system.inc");
require_once("services.inc");
require_once("interfaces.inc");

config_read_array('opendns');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $pconfig['enable'] = isset($config['opendns']['enable']);
    $pconfig['username'] = !empty($config['opendns']['username']) ? $config['opendns']['username'] : null;
    $pconfig['password'] = !empty($config['opendns']['password']) ? $config['opendns']['password'] : null;
    $pconfig['host'] = !empty($config['opendns']['host']) ? $config['opendns']['host'] : null;
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input_errors = array();
    $pconfig = $_POST;

    /* input validation */
    $reqdfields = array();
    $reqdfieldsn = array();
    if (!empty($pconfig['enable'])) {
        $reqdfields = array_merge($reqdfields, explode(" ", "host username password"));
        $reqdfieldsn = array_merge($reqdfieldsn, explode(",", "Network,Username,Password"));
    }
    do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

    if (!empty($pconfig['host']) && !is_domain($pconfig['host'])) {
        $input_errors[] = 'The host name contains invalid characters.';
    }
    if (empty($pconfig['username'])) {
        $input_errors[] = 'The username cannot be empty.';
    }

    if (!empty($pconfig['test'])) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, sprintf( 'https://updates.opendns.com/nic/update?hostname=%s', $pconfig['host']));
        curl_setopt($ch, CURLOPT_USERPWD, sprintf('%s:%s', $pconfig['username'], $pconfig['password']));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $output = curl_exec($ch);
        curl_close($ch);
        $test_results = explode("\r\n", $output);
    } elseif (count($input_errors) == 0) {
        $config['opendns']['enable'] = !empty($pconfig['enable']);
        $config['opendns']['username'] = $pconfig['username'];
        $config['opendns']['password'] = $pconfig['password'];
        $config['opendns']['host'] = $pconfig['host'];
        if ($config['opendns']['enable']) {
            $config['system']['dnsserver'] = array();
            $v4_server = array('208.67.222.222', '208.67.220.220');
            $v6_server = array('2620:0:ccc::2', '2620:0:ccd::2');
            if (isset($config['system']['prefer_ipv4'])) {
                $config['system']['dnsserver'][] = $v4_server[0];
                $config['system']['dnsserver'][] = $v4_server[1];
                if (isset($config['system']['ipv6allow'])) {
                    $config['system']['dnsserver'][] = $v6_server[0];
                    $config['system']['dnsserver'][] = $v6_server[1];
                }
            } else {
                if (isset($config['system']['ipv6allow'])) {
                    $config['system']['dnsserver'][] = $v6_server[0];
                    $config['system']['dnsserver'][] = $v6_server[1];
                }
                $config['system']['dnsserver'][] = $v4_server[0];
                $config['system']['dnsserver'][] = $v4_server[1];
            }
            $config['system']['dnsallowoverride'] = false;
        } else {
            $config['system']['dnsserver'] = array();
            $config['system']['dnsserver'][] = '';
            $config['system']['dnsallowoverride'] = true;
        }
        write_config('OpenDNS filter configuration change');
        system_resolvconf_generate();
        services_dhcpd_configure();
        $savemsg = get_std_save_message();
    }
}

legacy_html_escape_form_data($pconfig);

include 'head.inc';

?>
<body>

<?php include 'fbegin.inc'; ?>
<section class="page-content-main">
  <div class="container-fluid">
    <div class="row">
<?php
      if (isset($input_errors) && count($input_errors) > 0) {
        print_input_errors($input_errors);
      }
      if (isset($savemsg)) {
        print_info_box($savemsg);
      }?>
      <section class="col-xs-12">
        <div class="content-box table-responsive">
          <form method="post">
            <table class="table table-striped opnsense_standard_table_form">
              <thead>
                <tr>
                  <td style="width:22%"><strong><?=gettext('OpenDNS Setup'); ?></strong></td>
                  <td style="width:78%; text-align:right">
                    <small><?=gettext("full help"); ?> </small>
                    <i class="fa fa-toggle-off text-danger"  style="cursor: pointer;" id="show_all_help_page"></i>
                    &nbsp;
                  </td>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td><a id="help_for_enable" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext('Enable'); ?></td>
                  <td>
                    <input name="enable" type="checkbox" id="enable" value="yes" <?=!empty($pconfig['enable']) ? 'checked="checked"' : "";?> />
                    <strong><?=gettext('Filter DNS requests using OpenDNS'); ?></strong>
                    <output class="hidden" for="help_for_enable">
                      <?= sprintf(gettext(
                        'Enabling the OpenDNS service will overwrite DNS servers configured ' .
                        'via the General Setup page as well as ignore any DNS servers learned ' .
                        'by DHCP/PPP on WAN and use the DNS servers from %s instead.'),
                        '<a href="http://www.opendns.com" target="_blank">OpenDNS.com</a>'
                      ) ?>
                    </output>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_username" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext('Username'); ?></td>
                  <td>
                    <input name="username" type="text" id="username" size="20" value="<?=$pconfig['username'];?>" />
                    <output class="hidden" for="help_for_username">
                      <?=gettext(
                        'Signon Username to log into your OpenDNS dashboard. ' .
                        'It is used to automatically update the IP address of ' .
                        'the registered network.'
                      ); ?>
                    </output>
                  </td>
                </tr>
                <tr>
                  <td><i class="fa fa-info-circle text-muted"></i> <?=gettext('Password'); ?></td>
                  <td>
                    <input name="password" type="password" id="password" size="20" value="<?=$pconfig['password'];?>" />
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_host" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext('Network'); ?></td>
                  <td>
                    <input name="host" type="text" id="host" size="30" value="<?=$pconfig['host'];?>" />
                    <output class="hidden" for="help_for_host">
                      <?= sprintf(gettext(
                        'Enter the network name configured on the %sNetworks ' .
                        'Dashboard of OpenDNS%s under \'Manage your networks\'. ' .
                        'Used to update the node\'s IP address whenever the ' .
                        'WAN interface changes its IP address.'),
                        '<a href="https://www.opendns.com/dashboard/networks/" target="_blank">', '</a>'
                      ) ?>
                    </output>
                  </td>
                </tr>
<?php
                if (isset($test_results) && is_array($test_results)): ?>
                <tr>
                  <td><i class="fa fa-info-circle text-muted"></i> <?=gettext('Test result');?></td>
                  <td>
<?php
                    foreach ($test_results as $result) {
                      if (!strlen($result)) {
                        continue;
                      }

                      echo sprintf(
                        '<span class="glyphicon glyphicon-%s"></span> %s<br />',
                        strpos($result, 'good') === 0 ? 'ok text-success' : 'remove text-danger',
                        $result
                      );
                    }?>
                  </td>
                </tr>
<?php
                endif; ?>
                <tr>
                  <td>&nbsp;</td>
                  <td>
                    <input name="submit" type="submit" class="btn btn-primary" value="<?=gettext('Save');?>" />
                    <input name="test" type="submit" class="btn btn-primary" value="<?=gettext('Test/Update');?>" />
                  </td>
                </tr>
              </tbody>
            </table>
          </form>
        </div>
      </section>
    </div>
  </div>
</section>

<?php include 'foot.inc'; ?>
