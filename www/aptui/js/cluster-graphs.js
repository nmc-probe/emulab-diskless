require(window.APT_OPTIONS.configObject,
	['js/quickvm_sup',
	 'js/lib/text!template/cluster-graphs.html',
	 'js/bilevel', 'js/liquidFillGauge'],
function (sup, clusterString)
{
    'use strict';

    function initialize()
    {
	window.APT_OPTIONS.initialize(sup);

	$('#cluster-graphs').html(clusterString);

	bilevelAsterGraph("/cloudlab-nofed.json",
			  "#status-nofed","auto","large");
	bilevelAsterGraph("/cloudlab-fedonly.json",
			  "#status-fedonly","auto","large");

	setTimeout(function f() { Refresh() }, 30000);
    }

    /*
     * Refresh the graphs.
     */
    function Refresh()
    {
	$('#status-fedonly').html("");
	$('#status-nofed').html("");
	$("div").remove(".d3-tip");
	
	bilevelAsterGraph("/cloudlab-nofed.json",
			  "#status-nofed","auto","large");
	bilevelAsterGraph("/cloudlab-fedonly.json",
			  "#status-fedonly","auto","large");

	setTimeout(function f() { Refresh() }, 30000);
    }
	
    $(document).ready(initialize);
});
