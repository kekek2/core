<?php

/*
    Copyright (C) 2014-2015 Deciso B.V.

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

include("head.inc");

?>
<body>

<?php include("fbegin.inc"); ?>

<section class="page-content-main">
  <div class="container-fluid ">
    <div class="row">
      <section class="col-xs-11">
        <p><a href="http://www.smart-soft.ru/" target="_blank">TING&reg;</a></span> is Copyright &copy; <?=$g['product_copyright_years'];?> by Smart-Soft Ltd. All rights reserved.</p>
        <p>Redistribution and use in source and binary forms, with or without modification, are permitted provided that the following conditions are met:</p>
        <p><ol><li>Redistributions of source code must retain the above copyright notice, this list of conditions and the following disclaimer.</li>
        <li>Redistributions in binary form must reproduce the above copyright notice, this list of conditions and the following disclaimer in the documentation and/or other materials provided with the distribution.</li></ol></p>
        <p>THIS SOFTWARE IS PROVIDED "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES,
        INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
        AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL
        THE AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL,
        EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO,
        PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS;
        OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY,
        WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR
        OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF
        ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.</p>
        <p>TING is based on <a href="http://www.freebsd.org" target="_blank">FreeBSD</a> Copyright &copy; The FreeBSD Project. All rights reserved.</p>
        <p>TING is a fork of <a href="http://www.opnsense.org" target="_blank">OPNsense</a> Copyright &copy; Deciso B.V. All rights reserved.</p>
        <p>TING includes various freely available software packages and ports.
        The incorporated third party tools are listed <a href="/ui/core/firmware/">here</a>.</p>
      </section>
    </div>
  </div>
</section>

<?php include("foot.inc");
