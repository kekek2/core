<?php

namespace SmartSoft\License\Api;

use OPNsense\Base\ApiControllerBase;
use SmartSoft\Core\Tools;

const LICENSE_API_VER = 1;
const LICENSE_API_URL = 'https://bar.smart-soft.ru/api/v' . LICENSE_API_VER;

class SettingsController extends ApiControllerBase
{
    public function searchAction()
    {
        $this->sessionClose();

        $installed_crt_info = Tools::get_installed_certificates();
        $count = count($installed_crt_info);
        return ["rows" => $installed_crt_info, "rowCount" => $count, "total" => $count, "current" => 1];
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
        $company = preg_replace('/[^\w\d-]/', '', $company);
        if ($company == "") {
            $company = " ";
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, LICENSE_API_URL . "/{$licenseKey}/users");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $usersCount = ($code == '200') ? $body : null;

        $pkey_path = tools::installed_key_path;
        $pkey = openssl_get_privatekey("file://{$pkey_path}");

        $csrData = [
            'C' => 'RU',
            'ST' => ' ',
            'O' => $company,
            'tingAddress' => Tools::getCurrentMacAddress(),
            'tingLicense' => $licenseKey,
            'tingModule' => $module,
            'tingUsers' => $usersCount,
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
            $cert_path = tools::ting_crt_dir . '/ting-client.module.' . strtolower($module) . '.crt';
            $form_success = sprintf(gettext("License for module %s added successful"),
                $module);
        } else {
            $cert_path = tools::installed_crt_path;
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
                return ["status" => "failure", "message" => gettext("The license is not imported.")];
            }
            $file->moveTo(tools::installed_key_path);
            return ['status' => "OK", "message" => gettext("License imported successful")];
        }
        return ["status" => "failure", "message" => gettext("No license attached")];
    }

    public function exportAction()
    {
        $this->view->disable();
        $this->response->setFileToSend(tools::installed_key_path, "ting-client.key");
        $this->response->setContentType("text/plain","charset=ascii");
        $this->response->send();
        die();
    }
}
