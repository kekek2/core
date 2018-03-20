<?php

namespace SmartSoft\License\Api;

use OPNsense\Base\ApiControllerBase;
use SmartSoft\Core\Tools;

const LICENSE_API_VER = 1;
const LICENSE_API_URL = 'https://bar.smart-soft.ru/api/v' . LICENSE_API_VER;

class SettingsController extends ApiControllerBase
{
    private $ting_crt_dir = '/usr/local/etc/ssl';

    private $installed_key_path;
    private $installed_crt_path;
    private $installed_crt_modules_path;

    public function initialize()
    {
        parent::initialize();
        $this->installed_key_path = "{$this->ting_crt_dir}/ting-client.key";
        $this->installed_crt_path = "{$this->ting_crt_dir}/ting-client.crt";
        $this->installed_crt_modules_path = "{$this->ting_crt_dir}/ting-client.module.*.crt";
    }

    public function searchAction()
    {
        $this->sessionClose();

        $installed_crt_info = [];
        $key_modulus = false;
        if (file_exists($this->installed_key_path)) {
            $key_file = file_get_contents($this->installed_key_path);
            if (($priv_key = openssl_pkey_get_private($key_file))) {
                if (($keyData = openssl_pkey_get_details($priv_key)) && $keyData["type"] == OPENSSL_KEYTYPE_RSA) {
                    $key_modulus = $keyData["rsa"]["n"];
                }
            }

            if (file_exists($this->installed_crt_path)) {
                $cert_file = file_get_contents($this->installed_crt_path);
                $core_modulus = false;
                if (($pub_key = openssl_pkey_get_public($cert_file))) {
                    if (($keyData = openssl_pkey_get_details($pub_key)) && $keyData["type"] == OPENSSL_KEYTYPE_RSA) {
                        $core_modulus = $keyData["rsa"]["n"];
                    }
                }
                $installed_crt_info[] = [
                    "cert" => openssl_x509_parse($cert_file),
                    "modulus" => $core_modulus
                ];
            }

            foreach (glob($this->installed_crt_modules_path) as $module_crt_path) {
                $cert_module_file = file_get_contents($module_crt_path);
                $module_modulus = false;
                if (($pub_key = openssl_pkey_get_public($cert_module_file))) {
                    if (($keyData = openssl_pkey_get_details($pub_key)) && $keyData["type"] == OPENSSL_KEYTYPE_RSA) {
                        $module_modulus = $keyData["rsa"]["n"];
                    }
                }
                $installed_crt_info[] = [
                    "cert" => openssl_x509_parse($cert_module_file),
                    "modulus" => $module_modulus
                ];
            }
        }


        $rows = [];
        foreach ($installed_crt_info as $cert) {
            if (isset($cert["cert"]["subject"]["UNDEF"][2])) {
                $module = ($cert["cert"]["subject"]["UNDEF"][2] !== "") ? $cert["cert"]["subject"]["UNDEF"][2] : "CORE";
            } elseif (isset($cert["cert"]["subject"]["tingModule"])) {
                $module = ($cert["cert"]["subject"]["tingModule"] !== "") ? $cert["cert"]["subject"]["tingModule"] : "CORE";
            } else {
                $module = "";
            }

            if (isset($cert["cert"]["subject"]["UNDEF"][0])) {
                $cert_mac = $cert["cert"]["subject"]["UNDEF"][0];
            } elseif (isset($cert["cert"]["subject"]["tingAddress"])) {
                $cert_mac = $cert["cert"]["subject"]["tingAddress"];
            } else {
                $cert_mac = "";
            }

            if (isset($cert["cert"]["subject"]["UNDEF"][1])) {
                $license = $cert["cert"]["subject"]["UNDEF"][1];
            } elseif (isset($cert["cert"]["subject"]["tingAddress"])) {
                $license = $cert["cert"]["subject"]["tingLicense"];
            } else {
                $license = "";
            }

            $note = "";
            if ($cert_mac != Tools::getCurrentMacAddress()) {
                $note = gettext("License is not valid for this device");
            }

            if ($cert["modulus"] != $key_modulus) {
                $note = gettext("The certificate does not match the private key");
            }

            $rows[] = [
                "module" => $module,
                "expires" => strftime("%Y-%m-%d", $cert["cert"]["validTo_time_t"]),
                "organisation" => $cert["cert"]["subject"]["O"],
                "license" => $license,
                "note" => $note
            ];
        }

        $count = count($rows);
        return ["rows" => $rows, "rowCount" => $count, "total" => $count, "current" => 1];
    }

    public function getAction()
    {
        if (!$this->request->isPost()) {
            return ["result" => "failed"];
        }

        $post = $this->request->getPost("License");
        if (!isset($post["csrLicenseKey"])) {
            return ["result" => "failed"];
        }
        $licenseKey = $post["csrLicenseKey"];

        $this->sessionClose();

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, LICENSE_API_URL . "/{$licenseKey}");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code != '200' || !preg_match('/^\w{0,32}$/', $body)) {
            return ["result" => "failed", "message" => gettext('Could not get license.') . ' (Ex01)'];
        }
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
        if ($company == "") {
            $company = " ";
        }

        $pkey = openssl_get_privatekey("file://{$this->installed_key_path}");

        $csrData = [
            'C' => 'RU',
            'ST' => ' ',
            'O' => $company,
            'tingAddress' => Tools::getCurrentMacAddress(),
            'tingLicense' => $licenseKey,
            'tingModule' => $module,
        ];

        $csr_resource = openssl_csr_new($csrData, $pkey, ['config' => '/usr/local/etc/ssl/opnsense.cnf']);
        openssl_csr_export($csr_resource, $csr);

        if (!$csr) {
            return ["result" => "failed", "message" => gettext('Could not generate CSR.')];
        }
        $ch = curl_init(LICENSE_API_URL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, ['csr' => $csr]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code != '200') {
            $json = json_decode($body, true);
            if (isset($json['message'])) {
                return ["result" => "OK", "message" => $json['message']];
            }
            return ["result" => "failed", "message" => gettext('Could not get license.') . ' (Ex02)'];
        }

        $response = json_decode($body, true);

        if (!isset($response['crt']) || !isset($response['module'])) {
            return ["result" => "failed", "message" => gettext('Could not get license.') . ' (Ex03)'];
        }
        $crt = base64_decode($response['crt']);

        if (!openssl_x509_parse($crt)) {
            return ["result" => "failed", "message" => gettext('Could not parse CRT.')];
        }

        if ($module) {
            $cert_path = $this->ting_crt_dir . '/ting-client.module.' . strtolower($module) . '.crt';
            $form_success = sprintf(gettext("License for module %s added successful"),
                $module);
        } else {
            $cert_path = $this->installed_crt_path;
            $form_success = gettext("License for CORE added successful");
        }

        file_put_contents($cert_path, $crt);
        chmod($cert_path, 0644);
        return ["result" => "success", "message" => $form_success];
    }

    public function importAction()
    {
        $this->sessionClose(); // long running action, close session
        if (!$this->request->hasFiles()) {
            return ["status" => "failure", "message" => gettext("No files attached")];
        }
        foreach ($this->request->getUploadedFiles() as $file)
        {
            if ($file->getName() == "")
                return ["status" => "failure", "message" => gettext("Not specified the file name")];
            $key_file = file_get_contents($file->getTempName());
            if (!strstr($key_file, "BEGIN RSA PRIVATE KEY") || !strstr($key_file, "END RSA PRIVATE KEY")) {
                return ["status" => "failure", "message" => gettext("This file not contain license.")];
            }
            $file->moveTo($this->installed_key_path);
            return ['status' => "OK", "message" => gettext("License imported successful")];
        }
        return ["status" => "failure", "message" => gettext("No license attached")];
    }

    public function exportAction()
    {
        $this->view->disable();
        $this->response->setFileToSend($this->installed_key_path, "ting-client.key");
        $this->response->setContentType("text/plain","charset=ascii");
        $this->response->send();
        die();
    }
}
