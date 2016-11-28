<?php

const MAC_INTERFACE = 'em1';

const LICENSE_API_VER = 1;
const LICENSE_API_URL = 'https://bar.smart-soft.ru/api/v' . LICENSE_API_VER;

require_once("guiconfig.inc");

include("head.inc");

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

function getCurrentMacAddress($interface) {
    $ifconfig = shell_exec("ifconfig {$interface}");
    preg_match("/([0-9A-F]{2}[:-]){5}([0-9A-F]{2})/i", $ifconfig, $ifconfig);
    if (isset($ifconfig[0])) {
        return trim(strtoupper($ifconfig[0]));
    }
    return false;
}

if (file_exists($installed_crt_path) && file_exists($installed_key_path)) {
    $installed_crt_info = openssl_x509_parse(file_get_contents($installed_crt_path));
}

$form_errors = [];
$form_fields = ['csr_license_key' => ''];

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

            $pkey = openssl_get_privatekey("file://{$installed_key_path}");

            $csrData = [
                'tingAddress' => getCurrentMacAddress(MAC_INTERFACE),
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
                            } else {
                                $cert_path = $installed_crt_path;
                            }

                            file_put_contents($cert_path, $crt);
                            $installed_crt_info = openssl_x509_parse(file_get_contents($installed_crt_path));

                        } else {
                            $form_errors[] = 'Could not parse CRT.';
                        }
                    } else {
                        $form_errors[] = 'Could not get license certificate.';
                    }
                } else {
                    $json = json_decode($body, true);

                    if (isset($json['message'])) {
                        $form_errors[] = $json['message'];
                    } else {
                        $form_errors[] = 'Could not get license certificate.';
                    }
                }
            } else {
                $form_errors[] = 'Could not generate CSR.';
            }
        } else {
          $form_errors[] = 'Could not get license certificate.';
        }
    }
}

if ($installed_crt_info) {
    foreach (glob($installed_crt_modules_path) as $module_crt_path) {
        $installed_crt_modules_info[] = openssl_x509_parse(file_get_contents($module_crt_path));
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

          <table class="table table-clean-form opnsense_standard_table_form ">
            <thead>
              <tr style="background-color: rgb(251, 251, 251);">
                <td><strong>Installed certificates</strong></td>
                <td colspan="2"></td>
              </tr>
            </thead>
            <tbody>
              <?php if (!$installed_crt_info) { ?>
                <tr>
                  <td width="22%"><p>No certificate installed.</p></td>
                  <td colspan="2"></td>
                </tr>
              <?php } else { ?>
                <tr>
                  <td width="22%">Expires at</td>
                  <td><?php echo strftime("%Y-%m-%d", $installed_crt_info['validTo_time_t']); ?></td>
                  <td>CORE</td>
                </tr>
                <?php foreach ($installed_crt_modules_info as $module_info) { ?>
                  <tr>
                    <td></td>
                    <td><?php echo strftime("%Y-%m-%d", $module_info['validTo_time_t']); ?></td>
                    <td><?php echo isset($module_info['subject']['tingModule']) ? $module_info['subject']['tingModule'] : 'MODULE'; ?></td>
                  </tr>
                <?php } ?>
              <?php } ?>
            </tbody>
          </table>

          <table class="table table-clean-form opnsense_standard_table_form ">
            <thead>
              <tr style="background-color: rgb(251, 251, 251);">
                <td><strong>Get new certificate</strong></td>
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
                  <td width="22%">License key</td>
                  <td><input name="csr_license_key" type="text" required value="<?php echo $form_fields['csr_license_key']; ?>"/></td>
                </tr>
                <tr>
                  <td width="22%"></td>
                  <td><input type="submit" value="Get new certificate" class="btn btn-primary"/></td>
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
