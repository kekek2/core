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

        $("#importAct").click(function () {
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
                $('#responseMsg').attr('style', '').html("{{ lang._('License imported: ') }}" + data.status)
            });
            return false;
        });

        $("#exportAct").click(function () {
            window.location = "/api/license/settings/export";
        });
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
            <button id="getAct" type="button" class="btn btn-primary">{{ lang._('Get license') }} <i id="getActProgress"
                                                                                                     class=""></i>
            </button>
        </td>
        <td style="width: auto; margin: 5px; padding: 5px">
            <button id="exportAct" type="button" class="btn btn-primary">{{ lang._('Export license') }}</button>
        </td>
        <td style="width: auto; margin: 5px; padding: 5px">
            <button id="importAct" type="button" class="btn btn-primary">{{ lang._('Import license') }}</button>
        </td>
        <td style="width: auto; margin: 5px; padding: 5px">
            <form method="post" enctype="multipart/form-data" id="importform">
                <input name="importfile" type="file" id="importfile"/>
            </form>
        </td>
    </tr>
</table>
