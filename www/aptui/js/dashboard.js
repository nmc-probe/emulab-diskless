require(window.APT_OPTIONS.configObject,
	['underscore', 'js/quickvm_sup', 'moment',
	 'js/lib/text!template/dashboard.html'],
function (_, sup, moment, dashboardString)
{
    'use strict';
    var isadmin           = 0;
    var dashboardTemplate = _.template(dashboardString);

    function initialize()
    {
	window.APT_OPTIONS.initialize(sup);
	isadmin = window.ISADMIN;

	DashboardLoop();
	setInterval(UpdateTimes,1000);
    }

    function DashboardLoop()
    {
	var callback = function(json) {
	    console.log(json);
	    
	    var dashboard_html = dashboardTemplate({"dashboard": json.value,
						    "isadmin": isadmin});
	    $('#page-body').html(dashboard_html);

	    $('#last-refresh').data("time",new Date());
	    
	    // Format dates with moment before display.
	    $('.format-date').each(function() {
		var date = $.trim($(this).html());
		if (date != "") {
		    $(this).html(moment($(this).html())
				 .format("ddd h:mm A"));
		}
	    });
	    UpdateTimes();

	    setTimeout(function f() { DashboardLoop() }, 5000);
	}
	var xmlthing = sup.CallServerMethod(null, "dashboard",
					    "GetStats", null);
	xmlthing.done(callback);
    }

    function UpdateTimes()
    {
        $('.format-date-relative').each(function() {
            var date = $(this).data("time");
            if (date != "") {
                $(this).html(moment(date).fromNow());
            }
        });

    }

    $(document).ready(initialize);
});
