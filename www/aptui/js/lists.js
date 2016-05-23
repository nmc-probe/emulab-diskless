require(window.APT_OPTIONS.configObject,
	['underscore', 'js/quickvm_sup',
	 'js/lib/text!template/lists.html'
	],
function (_, sup, mainString)
{
    'use strict';
    var mainTemplate    = _.template(mainString);
    
    function initialize()
    {
	window.APT_OPTIONS.initialize(sup);
	var userlist = decodejson('#users-json');
	var projlist = decodejson('#projects-json');
	
	// Generate the main template.
	var html = mainTemplate({
	    "users"     : userlist,
	    "projects"  : projlist,
	});
	$('#main-body').html(html);
	InitTable("users");
	InitTable("projects");

	// Start out as empty tables.
	$('#search_users_table')
	    .tablesorter({
		theme : 'green',
		// initialize zebra
		widgets: ["zebra"],
	    });
	$('#search_projects_table')
	    .tablesorter({
		theme : 'green',
		// initialize zebra
		widgets: ["zebra"],
	    });

        // Javascript to enable link to tab
        var hash = document.location.hash;
        if (hash) {
            $('.nav-tabs a[href='+hash+']').tab('show');
        }
        // Change hash for page-reload
        $('a[data-toggle="tab"]').on('show.bs.tab', function (e) {
            window.location.hash = e.target.hash;
        });

	var search_users_timeout = null;
	$("#search_users_search").on("keyup", function (event) {
	    var userInput = $("#search_users_search").val();
	    userInput = userInput.toLowerCase();
	    window.clearTimeout(search_users_timeout);

	    search_users_timeout =
		window.setTimeout(function() {
		    if (userInput.length < 3) {
			return;
		    }
		    UpdateUserSearch(userInput);
		}, 500);
	});

	var search_projects_timeout = null;
	$("#search_projects_search").on("keyup", function (event) {
	    var userInput = $("#search_projects_search").val();
	    userInput = userInput.toLowerCase();
	    window.clearTimeout(search_users_timeout);

	    search_users_timeout =
		window.setTimeout(function() {
		    if (userInput.length < 3) {
			return;
		    }
		    UpdateProjectSearch(userInput);
		}, 500);
	});
    }
    
    function InitTable(name)
    {
	var tablename  = "#" + name + "_table";
	var searchname = "#" + name + "_search";
	
	var table = $(tablename)
		.tablesorter({
		    theme : 'green',
		    
		    // initialize zebra and filter widgets
		    widgets: ["zebra", "filter"],

		    widgetOptions: {
			// include child row content while filtering, if true
			filter_childRows  : true,
			// include all columns in the search.
			filter_anyMatch   : true,
			// class name applied to filter row and each input
			filter_cssFilter  : 'form-control',
			// search from beginning
			filter_startsWith : false,
			// Set this option to false for case sensitive search
			filter_ignoreCase : true,
			// Only one search box.
			filter_columnFilters : false,
		    }
		});

	// Target the $('.search') input using built in functioning
	// this binds to the search using "search" and "keyup"
	// Allows using filter_liveSearch or delayed search &
	// pressing escape to cancel the search
	$.tablesorter.filter.bindSearch(table, $(searchname));
    }

    function UpdateUserSearch(text)
    {
	var callback = function(json) {
	    console.info(json);

	    if (json.code) {
		console.info(json.value);
		return;
	    }
	    var html = "";
	    for (var i in json.value) {
		var user = json.value[i];
		html = html +
		    "<tr>" +
		    "<td><a href='user-dashboard.php?user=" + user.usr_uid + "'>" +
		    user.usr_uid + "</a></td>" +
		    "<td>" + user.usr_name + "</td>" +
		    "<td>" + user.usr_affil + "</td></tr>";
	    }
	    $('#search_users_table tbody').html(html);
	    $('#search_users_table').trigger("update", [false]);
	};
	var xmlthing = sup.CallServerMethod(null,
					    "lists", "SearchUsers",
					    {"text" : text});
	xmlthing.done(callback);
    }

    function UpdateProjectSearch(text)
    {
	var callback = function(json) {
	    console.info(json);

	    if (json.code) {
		console.info(json.value);
		return;
	    }
	    var html = "";
	    for (var i in json.value) {
		var project = json.value[i];
		html = html +
		    "<tr>" +
		    "<td><a href='show-project.php?project=" + project.pid + "'>" +
		    project.pid + "</a></td>" +
		    "<td><a href='user-dashboard.php?user=" + project.usr_uid + "'>" +
		    project.usr_name + "</a></td>" +
		    "<td>" + project.usr_affil + "</td></tr>";
	    }
	    $('#search_projects_table tbody').html(html);
	    $('#search_projects_table').trigger("update", [false]);
	};
	var xmlthing = sup.CallServerMethod(null,
					    "lists", "SearchProjects",
					    {"text" : text});
	xmlthing.done(callback);
    }

    // Helper.
    function decodejson(id) {
	return JSON.parse(_.unescape($(id)[0].textContent));
    }
    $(document).ready(initialize);
});


