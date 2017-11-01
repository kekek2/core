
<script type="text/javascript">
    $( document ).ready(function() {

        /*************************************************************************************************************
         * link general actions
         *************************************************************************************************************/

        function fillRows(data) {
            while($("#grid-logview")[0].rows.length > 1)
            {
                $("#grid-logview")[0].deleteRow(1);
            }

            if(!data || !data.data || !data.data.length)
                return;

            var dateTimeFound = 0;
            for(i = 0; i < data.data.length; i++)
            {
                if(data.data[i].time)
                {
                    dateTimeFound = 1;
                }

                var row = $("#grid-logview")[0].insertRow();
                var td = row.insertCell(0);
                td.innerHTML = data.data[i].time;
                td.className = "listlr";
                td = row.insertCell(1);
                td.innerHTML = data.data[i].message;
                td.className = "listr";
            }

            if(!dateTimeFound)
            {
                $("#dateTimeHeader").hide();
                $(".listlr").each(function(){$(this).hide();});
            }
            else
            {
                $("#dateTimeHeader").show();
            }
        };

        ajaxCall(url="/api/syslog/service/getlog", sendData={'logname': "{{logname}}"},callback=function(data,status) {
            fillRows(data);
        });

        $("#filtertext").keyup(function(e){
            if(e.keyCode == 13)
            {
                ajaxCall(url="/api/syslog/service/getlog", sendData={'logname': "{{logname}}", 'filter': $("#filtertext").val() },callback=function(data,status) {
                    fillRows(data);
                });
            }
        });

        $("#clearAction").click(function(){
            ajaxCall(url="/api/syslog/service/clearLog", sendData={'logname': "{{logname}}"},callback=function(data,status) {

                ajaxCall(url="/api/syslog/service/getlog", sendData={'logname': "{{logname}}"},callback=function(data,status) {
                    fillRows(data);
                });
            });
        });

        $("#downloadLogFileAction").click(function(){
            window.location = "/api/syslog/service/download?logname={{logname}}";
        });

        /*************************************************************************************************************
         * link grid actions
         *************************************************************************************************************/
    });
</script>

<section class="col-xs-12">
    <p>
            <div class="input-group">
                <div class="input-group-addon"><i class="fa fa-search"></i></div>
                <input type="text" class="form-control" id="filtertext" name="filtertext" placeholder="{{lang._('Search for a specific message...')}}" value="{{filtertext}}"/>
                <input type="submit" id="downloadLogFileAction" class="btn btn-primary pull-right" value="{{lang._('Download log file')}}" />
            </div>
    </p>
    <div class="content-box">
        <div id="logview" class="content-box-main">
            <table id="grid-logview" class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th class="col-xs-2" id="dateTimeHeader">{{lang._('Time')}}</th>
                        <th>{{lang._('Message')}}</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
    <p>
        <div class="input-group">
            <input id="clearAction" type="submit" class="btn btn-primary" value="{{lang._('Clear log')}}"/>
        </div>
    </p>
</section>

