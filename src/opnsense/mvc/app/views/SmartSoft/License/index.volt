<link rel="stylesheet" href="/ui/css/bootstrap-fileupload.css"/>
<script type="text/javascript">
    $(document).ready(function () {
        $("#grid-certlist").UIBootgrid(
            {
                search: "/api/license/settings/search",
                options: {
                    rowCount: -1
                }
            }
        );

        $("#getAct").click(function () {
            $("#responseMsg").addClass("hidden");
            $("#getActProgress").addClass("fa fa-spinner fa-pulse");
            $("#getAct").addClass("disabled");
            ajaxCall(url = "/api/license/settings/get", sendData = getFormData('frm_get'), callback = function (data, status) {
                $("#grid-certlist").bootgrid("reload");
                $("#responseMsg").removeClass("hidden").html(data.message);
                $("#getActProgress").removeClass("fa fa-spinner fa-pulse");
                $("#getAct").removeClass("disabled");
            });
        });

        $("#exportAct").click(function () {
            window.location = "/api/license/settings/export";
        });

        $(':file').on('fileselect', function(event, numFiles, label) {
            var formData = new FormData($("#importform")[0]);
            $.ajax({
                url: "/api/license/settings/import",
                type: "POST",
                data: formData,
                processData: false,  // tell jQuery not to process the data
                contentType: false   // tell jQuery not to set contentType
            }).done(function (data) {
                if (data.status != "failure")
                    $('#responseMsg').attr('class', 'alert alert-info');
                else
                    $('#responseMsg').attr('class', 'alert alert-danger');
                $('#responseMsg').attr('style', '').html("{{ lang._('License imported: ') }}" + data.message)
            });
            return false;
        });
    });

    $(document).on('change', ':file', function() {
        var input = $(this),
            numFiles = input.get(0).files ? input.get(0).files.length : 1,
            label = input.val().replace(/\\/g, '/').replace(/.*\//, '');
        input.trigger('fileselect', [numFiles, label]);
    });
</script>

<div class="alert alert-info hidden" role="alert" id="responseMsg">
</div>
<div class="alert alert-warning hidden" id="message_warning"></div>

<div id="certlist">
    <table id="grid-certlist" class="table table-condensed table-hover table-striped table-responsive">
        <thead>
        <tr>
            <th data-column-id="module" data-type="string" data-width="10%">{{ lang._('Module') }}</th>
            <th data-column-id="expires" data-type="string" data-width="10%">{{ lang._('Expires at') }}</th>
            <th data-column-id="organisation" data-type="string" data-width="15%">{{ lang._('Organisation') }}</th>
            <th data-column-id="license" data-type="string">{{ lang._('License') }}</th>
            <th data-column-id="note" data-type="string">{{ lang._('Note') }}</th>
        </tr>
        </thead>
        <tbody>
        </tbody>
        <tfoot>
        </tfoot>
    </table>
</div>

{{ partial("layout_partials/base_form",['fields':formGet,'id':'frm_get']) }}
<table style="width: auto">
    <tr>
        <td style="width: auto; margin: 5px; padding: 5px">
            <button id="getAct" type="button" class="btn btn-primary">{{ lang._('Activate license') }} <i id="getActProgress"
                                                                                                     class=""></i>
            </button>
        </td>
        <td style="width: auto; margin: 5px; padding: 5px">
            <button id="exportAct" type="button" class="btn btn-primary">{{ lang._('Export license') }}</button>
        </td>
        <td style="width: auto; margin: 5px; padding: 5px">
            <form method="post" enctype="multipart/form-data" id="importform">
                <span class="btn btn-primary btn-file">
                    {{ lang._('Import license') }}
                    <input name="importfile" type="file" id="importfile"/>
                </span>
            </form>
        </td>
    </tr>
</table>
