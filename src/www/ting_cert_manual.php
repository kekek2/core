<?php

require_once("guiconfig.inc");

include("head.inc");

$installed_crt_path = '/usr/local/etc/ssl/ting-client.crt';
$installed_key_path = '/usr/local/etc/ssl/ting-client.key';
$temporary_crt_path = '/tmp/ting-client.crt';
$temporary_key_path = '/tmp/ting-client.key';

$installed_crt_info = false;
$temporary_crt_info = false;

$upload_errors = [];

if (file_exists($installed_crt_path) && file_exists($installed_key_path)) {
    $installed_crt_info = openssl_x509_parse(file_get_contents($installed_crt_path));
}

if (file_exists($temporary_crt_path) && file_exists($temporary_key_path)) {
    $temporary_crt_info = openssl_x509_parse(file_get_contents($temporary_crt_path));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($_FILES['crtfile'] && $_FILES['keyfile']) {
        $uploaded_crt = file_get_contents($_FILES['crtfile']['tmp_name']);
        $uploaded_key = file_get_contents($_FILES['keyfile']['tmp_name']);
        if (!openssl_x509_check_private_key($uploaded_crt, $uploaded_key)) {
            $upload_errors[] = 'The uploaded key does not correspond to the uploaded certificate.';
        }
        if (!$upload_errors) {
            move_uploaded_file($_FILES['crtfile']['tmp_name'], $temporary_crt_path);
            move_uploaded_file($_FILES['keyfile']['tmp_name'], $temporary_key_path);
            $temporary_crt_info = openssl_x509_parse(file_get_contents($temporary_crt_path));
        }
    }
    if ($_POST['install'] == 1 && $temporary_crt_info) {
        try {
            rename($temporary_crt_path, $installed_crt_path);
            rename($temporary_key_path, $installed_key_path);
            $installed_crt_info = $temporary_crt_info;
            $temporary_crt_info = [];
        } catch(Exception $e) {}
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
                    <table class="table opnsense_standard_table_form ">
                        <thead>
                            <tr style="background-color: rgb(251, 251, 251);">
                                <td><strong>Installed certificate</strong></td>
                                <td></td>
                            </tr>
                        </thead>
                        <tbody>
<?php if (!$installed_crt_info) { ?>
                            <tr>
                                <td width="22%"><p>No certificate installed.</p></td>
                                <td></td>
                            </tr>
<?php } else { ?>
                            <tr>
                                <td width="22%">Name</td>
                                <td><?php echo $installed_crt_info['subject']['CN']; ?></td>
                            </tr>
                            <tr>
                                <td width="22%">Valid from</td>
                                <td><?php echo strftime("%Y-%m-%d %H:%M:%S", $installed_crt_info['validFrom_time_t']); ?></td>
                            </tr>
                            <tr>
                                <td width="22%">Valid to</td>
                                <td><?php echo strftime("%Y-%m-%d %H:%M:%S", $installed_crt_info['validTo_time_t']); ?></td>
                            </tr>
<?php } ?>
                        </tbody>
                    </table>

                    <br/>

<?php if ($temporary_crt_info && !$upload_errors) { ?>
                    <table class="table opnsense_standard_table_form ">
                        <thead>
                            <tr style="background-color: rgb(251, 251, 251);">
                                <td><strong>Uploaded certificate (not installed)</strong></td>
                                <td></td>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td width="22%">Name</td>
                                <td><?php echo $temporary_crt_info['subject']['CN']; ?></td>
                            </tr>
                            <tr>
                                <td width="22%">Valid from</td>
                                <td><?php echo strftime("%Y-%m-%d %H:%M:%S", $temporary_crt_info['validFrom_time_t']); ?></td>
                            </tr>
                            <tr>
                                <td width="22%">Valid to</td>
                                <td><?php echo strftime("%Y-%m-%d %H:%M:%S", $temporary_crt_info['validTo_time_t']); ?></td>
                            </tr>
                            <tr>
                                <td width="22%"></td>
                                <td>
                                    <form action="/ting_cert_manual.php" method="post">
                                        <input type="hidden" name="install" value="1"/>
                                        <input type="submit" value="Install" class="btn btn-primary"/>
                                    </form>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <br/>
<?php } ?>

                    <form action="/ting_cert_manual.php" method="post" enctype="multipart/form-data">
                        <table class="table opnsense_standard_table_form ">
                            <thead>
                            <tr style="background-color: rgb(251, 251, 251);">
                                <td>
                                    <strong>Upload new certificate and key files</strong>
                                </td>
                                <td></td>
                            </tr>
                            </thead>
                            <tbody>
                            <?php if ($upload_errors) { ?>
                                <tr>
                                    <td width="22%"></td>
                                    <td>
                                        <?php foreach ($upload_errors as $error) { ?>
                                            <p style="color: red;"><?php echo $error; ?></p>
                                        <?php } ?>
                                    </td>
                                </tr>
                            <?php } ?>
                            <tr>
                                <td width="22%">Certificate file</td>
                                <td><input name="crtfile" type="file" required/></td>
                            </tr>
                            <tr>
                                <td width="22%">Private key file</td>
                                <td><input name="keyfile" type="file" required/></td>
                            </tr>
                            <tr>
                                <td width="22%"></td>
                                <td><input type="submit" value="Upload" class="btn btn-primary"/></td>
                            </tr>
                            </tbody>
                        </table>
                    </form>
                </div>
            </section>
        </div>
    </div>
</section>

<?php include("foot.inc");
