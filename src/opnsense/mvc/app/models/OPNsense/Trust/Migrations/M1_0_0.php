<?php
/**
 *    Copyright (C) 2017 Smart-Soft
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

namespace OPNsense\Trust\Migrations;

use OPNsense\Base\BaseModelMigration;
use OPNsense\Core\Config;

class M1_0_0 extends BaseModelMigration
{
	public function run($model)
	{
		parent::run($model);

		// import settings from legacy config section
		$config = Config::getInstance()->toArray(array_flip(['ca', 'cert', 'crl']));

		foreach ($config["ca"] as $ca) {
            $new_ca = $model->cas->ca->add();
            $new_ca->refid = $ca["refid"];
            $new_ca->descr = $ca["descr"];
            $new_ca->crt = $ca["crt"];
            $new_ca->prv = $ca["prv"];
            $new_ca->serial = $ca["serial"];
        }

        foreach ($config["cert"] as $cert) {
            $new_cert = $model->certs->cert->add();
            $new_cert->refid = $cert["refid"];
            if (isset($cert["caref"])) {
                $new_cert->caref = $cert["caref"];
            }
            $new_cert->descr = $cert["descr"];
            $new_cert->crt = $cert["crt"];
            $new_cert->prv = $cert["prv"];
        }

        foreach ($config["crl"] as $crl) {
            $new_crl = $model->crls->crl->add();
            $new_crl->refid = $crl["refid"];
            $new_crl->caref = $crl["caref"];
            $new_crl->descr = $crl["descr"];
            $new_crl->crlmethod = $crl["crlmethod"];
            $new_crl->serial = $crl["serial"];
            $new_crl->lifitime = $crl["lifitime"];

            foreach ($crl["cert"] as $cert) {
                $new_cert = $new_crl->certs->cert->add();
                $new_cert->refid = $crl["cert"]["refid"];
                $new_cert->caref = $crl["cert"]["caref"];
                $new_cert->descr = $crl["cert"]["descr"];
                $new_cert->crt = $crl["cert"]["crt"];
                $new_cert->prv = $crl["cert"]["prv"];
                $new_cert->reason = $crl["cert"]["reason"];
                $new_cert->revoke_time = $crl["cert"]["revoke_time"];
            }
        }

		// save and restart
		$model->serializeToConfig();
		Config::getInstance()->save();
	}
}
