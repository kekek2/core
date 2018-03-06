<?php

const LICENSE_API_VER = 1;
const LICENSE_API_URL = 'https://bar.smart-soft.ru/api/v' . LICENSE_API_VER;

require_once("guiconfig.inc");

include("head.inc");
use SmartSoft\Core\Tools;

$openssl_config_path = '/usr/local/etc/ssl/opnsense.cnf';
$openssl_config_args = [
    'config' => $openssl_config_path,
];

$ting_crt_dir = '/usr/local/etc/ssl';

$installed_key_path = "{$ting_crt_dir}/ting-client.key";
$installed_crt_path = "{$ting_crt_dir}/ting-client.crt";
$temporary_crt_path = '/tmp/ting-client.crt';
$installed_crt_info = false;
$temporary_crt_info = false;

$installed_crt_modules_path = "{$ting_crt_dir}/ting-client.module.*.crt";
$installed_crt_modules_info = [];

function getCrtInfo($crt_info, $key_modulus, $cert_modulus)
{
    $ret = "";
    if (isset($crt_info['subject']['UNDEF'])) {
        if (isset($crt_info['subject']['UNDEF'][2]) && $crt_info['subject']['UNDEF'][2] != "" )
            if ($crt_info['subject']['UNDEF'][2] != "") {
                $ret .= "<td>" . $crt_info['subject']['UNDEF'][2] . "</td>\n";
            }

        if ($key_modulus !== $cert_modulus)
        {
            $ret .= "<td>" . gettext("The certificate does not match the private key") . "</td>";
        } elseif (!isset($crt_info['subject']['UNDEF'][0]))
            $ret .= "<td>" . gettext("Can not validate certificate") . "</td>";
        elseif ($crt_info['subject']['UNDEF'][0] != Tools::getCurrentMacAddress())
            $ret .= "<td>" . gettext("License is not valid for this device") . "</td>";
        else
            $ret .= "<td></td>";

    } elseif (isset($crt_info['subject']['tingModule'])) {
        if ($crt_info['subject']['tingModule'] != "") {
            $ret .= "<td>" . $crt_info['subject']['tingModule'] . "</td>";
        }

        if ($key_modulus !== $cert_modulus)
        {
            $ret .= "<td>" . gettext("The certificate does not match the private key") . "</td>";
        } elseif ($crt_info['subject']['tingAddress'] != Tools::getCurrentMacAddress())
            $ret .= "<td>" . gettext("License is not valid for this device") . "</td>";
        else
            $ret .= "<td></td>";
    }
    return $ret . "\n";
}

$core_modulus = false;
$key_modulus = false;
if (file_exists($installed_crt_path) && file_exists($installed_key_path)) {
    $cert_file = file_get_contents($installed_crt_path);
    $installed_crt_info = openssl_x509_parse($cert_file);
    if (($pub_key = openssl_pkey_get_public($cert_file))) {
        if (($keyData = openssl_pkey_get_details($pub_key)) && $keyData["type"] == OPENSSL_KEYTYPE_RSA) {
            $core_modulus = $keyData["rsa"]["n"];
        }
    }
    $key_file = file_get_contents($installed_key_path);
    if (($priv_key = openssl_pkey_get_private($key_file))) {
        if (($keyData = openssl_pkey_get_details($priv_key)) && $keyData["type"] == OPENSSL_KEYTYPE_RSA) {
            $key_modulus = $keyData["rsa"]["n"];
        }
    }
}

$form_errors = [];
$form_fields = ['csr_license_key' => ''];
$form_success = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csr_license_key'])) {
        $form_errors['csr_license_key'] = 'License key is required.';

    } else {
        $form_fields['csr_license_key'] = $_POST['csr_license_key'];

        $licenseKey = $_POST['csr_license_key'];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, LICENSE_API_URL . "/{$licenseKey}");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code == '200' && preg_match('/^\w{0,32}$/', $body)) {
            $module = $body;

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, LICENSE_API_URL . "/{$licenseKey}/comp");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            $body = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $company = ($code == '200' && preg_match('/^[\w\s.-]{0,48}$/', $body)) ? $body : " ";
            $company = preg_replace('/[^\w\d- ]/', '', $company);

            $pkey = openssl_get_privatekey("file://{$installed_key_path}");

            $csrData = [
                'C'  => 'RU',
                'ST' => ' ',
                'O'  => $company,
                'tingAddress' => Tools::getCurrentMacAddress(),
                'tingLicense' => $licenseKey,
                'tingModule'  => $module,
            ];

              $csr_resource = openssl_csr_new($csrData, $pkey, $openssl_config_args);
              openssl_csr_export($csr_resource, $csr);

            if ($csr) {
                $ch = curl_init(LICENSE_API_URL);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, ['csr' => $csr]);
                $body = curl_exec($ch);
                $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($code == '200') {

                    $response = json_decode($body, true);

                    if (isset($response['crt']) && isset($response['module'])) {
                        $crt = base64_decode($response['crt']);

                        // $crtData = openssl_x509_parse($crt, false);

                        if (openssl_x509_parse($crt)) {
                            if ($module) {
                                $cert_path = $ting_crt_dir . '/ting-client.module.' . strtolower($module) . '.crt';
                                $form_success[] = sprintf(gettext("License certificate for module %s added successful"), $module);
                            } else {
                                $cert_path = $installed_crt_path;
                                $form_success[] = gettext("License certificate for CORE added successful");
                            }

                            file_put_contents($cert_path, $crt);
                            chmod($cert_path, 0644);
                            $installed_crt_info = openssl_x509_parse(file_get_contents($installed_crt_path));

                        } else {
                            $form_errors[] = gettext('Could not parse CRT.');
                        }
                    } else {
                        $form_errors[] = gettext('Could not get license certificate.') . ' (Ex01)';
                    }
                } else {
                    $json = json_decode($body, true);

                    if (isset($json['message'])) {
                        $form_errors[] = $json['message'];
                    } else {
                        $form_errors[] = gettext('Could not get license certificate.') . ' (Ex02)';
                    }
                }
            } else {
                $form_errors[] = gettext('Could not generate CSR.');
            }
        } else {
          $form_errors[] = gettext('Could not get license certificate.') . ' (Ex03)';
        }
    }
}

if ($installed_crt_info) {
    foreach (glob($installed_crt_modules_path) as $module_crt_path) {
        $cert_module_file = file_get_contents($module_crt_path);
        $module_modulus = false;
        if (($pub_key = openssl_pkey_get_public($cert_module_file))) {
            if (($keyData = openssl_pkey_get_details($pub_key)) && $keyData["type"] == OPENSSL_KEYTYPE_RSA) {
                $module_modulus = $keyData["rsa"]["n"];
            }
        }
        $installed_crt_modules_info[] = ["cert" => openssl_x509_parse($cert_module_file), "modulus" => $module_modulus];
    }
}

?>

<body>

<?php include("fbegin.inc"); ?>

<section class="page-content-main">
  <div class="container-fluid ">
    <div class="row">
      <section class="col-xs-12">
        <div class="content-box tab-content">
            <?php if ($form_success) { ?>
                <div class="alert alert-info" role="alert" id="responseMsg">
                    <?php foreach ($form_success as $success) { ?>
                        <?php echo $success; ?>
                    <?php } ?>
                </div>
            <?php } ?>
            <table class="table table-clean-form opnsense_standard_table_form ">
            <thead>
              <tr style="background-color: rgb(251, 251, 251);">
                <td><strong><?=gettext('Installed certificates')?></strong></td>
                <td colspan="3"></td>
              </tr>
            </thead>
            <tbody>
              <?php if (!$installed_crt_info) { ?>
                <tr>
                  <td width="22%"><p><?=gettext('No certificate installed.')?></p></td>
                  <td colspan="3"></td>
                </tr>
              <?php } else { ?>
                <tr>
                  <td width="22%"><?=gettext('Expires at')?></td>
                  <td><?php echo strftime("%Y-%m-%d", $installed_crt_info['validTo_time_t']); ?></td>
                  <td>CORE</td>
                  <?php echo getCrtInfo($installed_crt_info, $key_modulus, $core_modulus);?>
                </tr>
                <?php foreach ($installed_crt_modules_info as $module_info) { ?>
                  <tr>
                    <td></td>
                    <td><?php echo strftime("%Y-%m-%d", $module_info["cert"]['validTo_time_t']); ?></td>
                    <?php echo getCrtInfo($module_info["cert"], $key_modulus, $module_info["modulus"]); ?>
                  </tr>
                <?php } ?>
              <?php } ?>
            </tbody>
          </table>

          <table class="table table-clean-form opnsense_standard_table_form ">
            <thead>
              <tr style="background-color: rgb(251, 251, 251);">
                <td><strong><?=gettext('Get new certificate')?></strong></td>
                <td></td>
              </tr>
            </thead>
            <tbody>
              <form action="/ting_cert.php" method="post">
                <?php if ($form_errors) { ?>
                  <tr>
                    <td width="22%"></td>
                    <td>
                      <?php foreach ($form_errors as $error) { ?>
                        <p style="color: red;"><?php echo $error; ?></p>
                      <?php } ?>
                    </td>
                  </tr>
                <?php } ?>
                <tr>
                  <td width="22%"><?=gettext('License key')?></td>
                  <td><input name="csr_license_key" type="text" required value="<?php echo $form_fields['csr_license_key']; ?>"/></td>
                </tr>
                <tr>
                  <td width="22%"></td>
                  <td><input type="submit" value="<?=gettext('Get new certificate')?>" class="btn btn-primary"/></td>
                </tr>
              </form>
            </tbody>
          </table>

        </div>
      </section>
    </div>
  </div>
</section>

<?php include("foot.inc");
