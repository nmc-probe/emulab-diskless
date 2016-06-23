require(window.APT_OPTIONS.configObject,
	['underscore', 'constraints', 'js/quickvm_sup',
	 'js/ppwizardstart', 'js/JacksEditor', 'js/wizard-template',
	 'js/lib/text!template/instantiate-new.html',
	 'js/lib/text!template/aboutapt.html',
	 'js/lib/text!template/aboutcloudlab.html',     
	 'js/lib/text!template/aboutpnet.html',
	 'js/lib/text!template/waitwait-modal.html',
	 'js/lib/text!template/rspectextview-modal.html',
	 'formhelpers', 'filestyle', 'marked', 'jacks', 'jquery-steps'],
function (_, Constraints, sup, ppstart, JacksEditor, wt,
	  instantiateString, aboutaptString, aboutcloudString, aboutpnetString,
	  waitwaitString, rspecviewString)
{
    'use strict';

    var ajaxurl;
    var amlist        = null;
    var projlist      = null;
    var sysprojlist   = ['emulab-ops', 'emulab-ops-test'];
    var psysprojlist  = ['PhantomNet', 'testproject'];
    var profilelist   = null;
    var recentcount   = 5;
    var amdefault     = null;
    var selected_uuid = null;
    var selected_rspec   = null;
    var selected_version = null;
    var ispprofile    = 0;
    var isscript      = 0;
    var rerunscripts  = 0;
    var webonly       = 0;
    var isadmin       = 0;
    var multisite     = 0;
    var doconstraints = 0;
    var amValueToKey  = {};
    var showpicker    = 0;
    var portal        = null;
    var registered    = false;
    var JACKS_NS      = "http://www.protogeni.net/resources/rspec/ext/jacks/1";
    var jacks = {
      instance: null,
      input: null,
      output: null
    };
    var editor        = null;
    var loaded_uuid   = null;
    var ppchanged     = false;
    var monitor       = null;
    var types         = null;
    var mainTemplate  = _.template(instantiateString);

    function initialize()
    {
    // Get context for constraints
	var contextUrl = 'https://www.emulab.net/protogeni/jacks-context/cloudlab-utah.json';
	$.get(contextUrl).then(contextReady, contextFail);

	window.APT_OPTIONS.initialize(sup);
	window.APT_OPTIONS.initialize(ppstart);
	registered = window.REGISTERED;
	webonly    = window.WEBONLY;
	isadmin    = window.ISADMIN;
	multisite  = window.MULTISITE;
	portal     = window.PORTAL;
	ajaxurl    = window.AJAXURL;
	doconstraints = window.DOCONSTRAINTS;
	showpicker    = window.SHOWPICKER;

	if ($('#amlist-json').length) {
	    amlist = decodejson('#amlist-json');
	    _.each(_.keys(amlist), function (key) {
		amValueToKey[amlist[key]] = key;
	    });
	}
	if ($('#projects-json').length) {
	    projlist = decodejson('#projects-json');
	}
	profilelist = decodejson('#profiles-json');

	var profileToArray = _.pairs(profilelist);
	var recentlist = _.filter(profileToArray, function(value) {
	    return value[1]['usecount'] > 0;
	});

	var neverUsed = 0;
	if (recentlist.length == 0 || !registered) {
	    neverUsed = 1;
	    recentlist = profileToArray;
	}

	// Note that sortBy orders by ascending, so the most recent
	// are at the end of the array.
	recentlist = _.sortBy(recentlist, function(obj) {
	    return obj[1].lastused;
	});
	recentlist = _.last(recentlist, recentcount);

	_.each(recentlist, function(obj, key) {
	    if (window.ISPNET) {
		if (_.contains(psysprojlist, obj[1].project)) {
		    obj[1].project = "System";
		}
	    }
	    else {
		if (_.contains(sysprojlist, obj[1].project)) {
		    obj[1].project = "System";
		}
	    }
	});
	var projcategories = MakeProfileCategories(profilelist);	

	var html = mainTemplate({
	    formfields:         decodejson('#form-json'),
	    profiles:           profilelist,
	    projprofiles:       projcategories.inproj,
	    systemprofiles:        projcategories.sysproj,
	    otherprofiles:      projcategories.otherproj,
	    recent:             recentlist,
	    showpopular:        neverUsed,
	    favorites:          projcategories.favorite,
	    projects:           projlist,
	    amlist:             amlist,
	    registered:         registered,
	    profilename:        window.PROFILENAME,
	    profileuuid:        window.PROFILEUUID,     
	    profilevers:        window.PROFILEVERS,     
	    showpicker:         showpicker,
	    cancopy:            window.CANCOPY,
	    clustername:        window.PORTAL_NAME,
	    admin:		isadmin,
	});
	$('#main-body').html(html);


	// TEMPORARY BUTTON FOR CLASSIC PICKER
	// To be removed when the new picker becomes default
	
	// Quick and dirty
	var btntext = 'Use Classic Picker';
	var btnhtml = window.location.href;
	var whichchar = (btnhtml.indexOf('?') > -1) ? '&' : '?';

	if (window.CLASSIC === undefined || window.CLASSIC) {
	    btntext = 'Try the New Picker!';
	    btnhtml = btnhtml.replace('classic=true','');
	    btnhtml += whichchar + 'classic=false';
	}
	else {
	    btnhtml = btnhtml.replace('classic=false','');
	    btnhtml += whichchar + 'classic=true';
	}
	btnhtml = btnhtml.replace('&&','&').replace('?&','?');
	btnhtml.replace('#','');

	$('#quickvm_topomodal #showtopo_dialog .modal-header').append('<a '+
		' href="'+btnhtml+'"'+
		'>'+
		'<button'+
		' id="whichPicker"'+
		' class="btn btn-info btn-sm"'+
		' style="position: absolute;top:14px;right:40px"'+
		'>'+btntext+'</button>'+
		'</a>');

	// END TEMPORARY BUTTON


	// Check if the browser has cookies stating what they previoiusly had minimized.
	CookieCollapse('#profile_name > span', 'pp_collpased');

	var jqxhr =
	    $.get('https://ops.emulab.net/servicemon/?names=urn')
	    .done(function(data) {
		monitor = JSON.parse(data);
		CreateClusterStatus();
	    }).error(function(a) {
		console.log(a);
	    });

	$('#waitwait_div').html(waitwaitString);
	$('#rspecview_div').html(rspecviewString);
	$('#rspec_modal_download_button').addClass("hidden");
	
	// The about panel.
	if (window.SHOWABOUT) {
	    $('#about_div').html(window.ISCLOUD ? aboutcloudString :
				 (window.ISPNET ? aboutpnetString : 
				  aboutaptString));
	}
	$('#stepsContainer').steps({
	    headerTag: "h3",
	    bodyTag: "div",
	    transitionEffect: "slideLeft",
	    autoFocus: true,
	    onStepChanging: function(event, currentIndex, newIndex) {
		return StepChanging(this, event, currentIndex, newIndex);
	    },
	    onStepChanged: function(event, currentIndex, priorIndex) {
		return StepChanged(this, event, currentIndex, priorIndex);
	    },
	    onFinishing: function(event, currentIndex) {
		return Instantiate(this, event);
	    },
	});
	// This activates the popover subsystem. 
	$('[data-toggle="popover"]').popover({
	    trigger: 'hover',
	    placement: 'auto',
	    container: 'body',
	});

	// Format the step labels across the top to match the panel widths.
	$('#stepsContainer .steps').addClass('col-lg-8 col-lg-offset-2 col-md-8 col-md-offset-2 col-sm-10 col-sm-offset-1 col-xs-12 col-xs-offset-0');
	$('#stepsContainer .actions').addClass('col-lg-8 col-lg-offset-2 col-md-8 col-md-offset-2 col-sm-10 col-sm-offset-1 col-xs-12 col-xs-offset-0');

	// Set up jacks swap
	$('#stepsContainer #inline_overlay').click(function() {
		SwitchJacks('large');
	});

	$('#quickvm_topomodal').on('shown.bs.modal', function() {
	    ShowProfileSelection($('#profile_name .current'))
	});

	$('button#reset-form').click(function (event) {
	    event.preventDefault();
	    resetForm($('#quickvm_form'));
	});
	$('button#profile').click(function (event) {
	    event.preventDefault();
	    $('#quickvm_topomodal').modal('show');
	});
	$('li.profile-item').click(function (event) {
	    event.preventDefault();
	    ShowProfileSelection(event.target);
	});
	$('button#showtopo_select').click(function (event) {
	    event.preventDefault();
	    ChangeProfileSelection($('#quickvm_topomodal .selected'));
	    selected_uuid = $('#quickvm_topomodal .selected').attr('value');
	    console.log(selected_uuid);
	    $('#quickvm_topomodal').modal('hide');
	    $('.steps .error').removeClass('error');
	});
	/*
	 * Need to update image constraints when the project selector
	 * is changed.
	 */
	$('#profile_pid').change(function (event) {
	  console.log('profile-pid change');
	    UpdateImageConstraints();
	    return true;
	});
	$('#profile_copy_button').click(function (event) {
	    event.preventDefault();
	    if (!registered) {
		sup.SpitOops("oops", "You must be a registered user to copy " +
			     "a profile.");
		return;
	    }
	    var url = "manage_profile.php?action=copy&uuid=" + selected_uuid;
	    window.location.replace(url);
	    return false;
	});

	$('#profile_show_button').click(function (event) {
	    event.preventDefault();
	    if (!registered) {
		sup.SpitOops("oops", "You must be a registered user to view " +
			     "profile details.");
		return;
	    }
	    var url = "show-profile.php?uuid=" + selected_uuid;
	    window.location.replace(url);
	    return false;
	});

	$('#show_xml_modal_button').click(function (event) {
	    //
	    // Show the XML source in the modal. This is used when we
	    // have a script, and the XML was generated. We show the
	    // XML, but it is not intended to be edited.
	    //
	    $('#rspec_modal_editbuttons').addClass("hidden");
	    $('#rspec_modal_viewbuttons').removeClass("hidden");
	    $('#modal_profile_rspec_textarea').val(selected_rspec);
	    $('#modal_profile_rspec_textarea').prop("readonly", true);
	    $('#modal_profile_rspec_div').addClass("hidden");
	    $('#modal_profile_rspec_textarea').removeClass("hidden");
	    $('#rspec_modal').modal({'backdrop':'static','keyboard':false});
	    $('#rspec_modal').modal('show');
	});
	$('#close_rspec_modal_button').click(function (event) {
	    $('#rspec_modal').modal('hide');
	    $('#modal_profile_rspec_textarea').val("");
	});

	// Profile picker search box.
	var profile_picker_timeout = null;
	
	$("#profile_picker_search").on("keyup", function (event) {
	    var options   = $('#profile_name');
	    var userInput = $("#profile_picker_search").val();
	    userInput = userInput.toLowerCase();
	    window.clearTimeout(profile_picker_timeout);

	    profile_picker_timeout =
		window.setTimeout(function() {
		    var matches = 
			options.children("ul").children("li").filter(function() {
			    var text = $(this).text();
			    text = text.toLowerCase();

			    if (text.indexOf(userInput) > -1)
				return true;
			    return false;
			});
		    options.children("ul").children("li").hide();
		    matches.show();

		    if (userInput == '') {
			$('#title_recently_used').removeClass('hidden');
			$('#recently_used').removeClass('hidden');
			$('#title_favorites').removeClass('hidden');
			$('#favorites').removeClass('hidden');
		    }
		    else {
			$('#title_recently_used').addClass('hidden');
			$('#recently_used').addClass('hidden');
			$('#title_favorites').addClass('hidden');
			$('#favorites').addClass('hidden');
		    }
		}, 500);

	    // User types return while searching, if there was only one
	    // choice, then we select it. Convenience. 
	    if (event.keyCode == 13) {
		var matches = 
		    options.find("li").filter(function() {
			return (!$(this).parent().hasClass('hidden') && $(this).css('display') == 'block');
		    });
		if (matches && matches.length == 1) {
		    ShowProfileSelection(matches[0]);
		}
	    }
	});

	//
	// SSH file upload handler, to move the file contents into
	// the ssh text area. 
	//
	if (!registered) {
	    $('#input_keyfile').change(function() {
		var reader = new FileReader();
		reader.onload = function(event) {
		    /*
		     * Clear the file so that the change handler will
		     * run if the same file is selected again.
		     */
		    $("#input_sshkey").text(event.target.result);
		};
		reader.readAsText(this.files[0]);
	    });
	}
	    
	var startProfile = $('#profile_name li[value = ' + window.PROFILE + ']:first');
	ChangeProfileSelection(startProfile);
	_.delay(function () {$('.dropdown-toggle').dropdown();}, 500);

	// Set up the click function for expanding and collapsing profile groups
	$('#profile_name > span').click(function() {
	    var ul = '#'+($(this).attr('id').slice('title-'.length));
	    if ($(this).children('.category_collapsable').hasClass('expanded')) {
		$(ul).addClass('hidden');
		$(this).children('.category_collapsable').removeClass('expanded');
		$(this).children('.category_collapsable').addClass('collapsed');
	    }
	    else {
		$(ul).removeClass('hidden');
		$(this).children('.category_collapsable').addClass('expanded');
		$(this).children('.category_collapsable').removeClass('collapsed');
	    }

	    var collapsed = [];
	    $('#profile_name .category_collapsable.collapsed').each(function() {
		collapsed.push($(this).parent().attr('id'));
	    });

	    SetCookie('pp_collpased',JSON.stringify(collapsed),30);
	});
    }

    // Helper.
    function decodejson(id) {
	return JSON.parse(_.unescape($(id)[0].textContent));
    }

    // Handler to minimize span elements based on the cookie name
    function CookieCollapse(target, cookieName) {
	var cookie = GetCookie(cookieName);

	if (cookie != null) {  
	    var collapsed = JSON.parse(cookie);
    
	    $(target).each(function() {
		if (_.contains(collapsed, $(this).attr('id'))) {
		    $(this).children('.category_collapsable').removeClass('expanded').addClass('collapsed');
		    var ul = '#'+($(this).attr('id').slice('title-'.length));
		    $(ul).addClass('hidden');
		}
	    });
	}
    } 

    // Put profiles into the correct categories to be built in the template
    function MakeProfileCategories(profiles) {
      var result = {favorite:{},inproj:{},sysproj:{},otherproj:{}};

      // This section should probably be rethought as it's not very clean. 
      // Didn't have time to refactor for initial release.
      _.each(profilelist, function(obj, key) {
	    var isSystem = (window.ISPNET && _.contains(psysprojlist, obj.project)) || (!window.ISPNET &&_.contains(sysprojlist, obj.project))
	    if (obj.favorite == 1) {
	      if (isSystem	) {
		result.favorite[key] = $.parseJSON(JSON.stringify(obj));
		result.favorite[key].project = "System";
	      }
	      else {
		result.favorite[key] = obj;
	      }
	    }
	    if (isSystem) {
	      result.sysproj[key] = obj;
	    }
	    if (_.contains(projlist, obj.project)) {
	      if (!result.inproj[obj.project]) {
		result.inproj[obj.project] = {};
	      }
	      result.inproj[obj.project][key] = obj;
	    }
	    else if (!isSystem) {
	      result.otherproj[key] = obj;
	    }
	});
      return result;
    }

    var doingformcheck = 0;
    var doingrunscript = 0;
    
    // Step is changing
    function StepChanging(step, event, currentIndex, newIndex) {
	if (currentIndex == 0 && newIndex == 1) {
	    // Check step 0 form values. Any errors, we stop here.
	    if (!registered && !doingformcheck) {
		doingformcheck = 1;
		CheckStep0(function (success) {
		    if (success) {
			$('#stepsContainer-t-0').parent().removeClass('error');
			$('#stepsContainer').steps('next');
		    }
		    else {
			$('#stepsContainer-t-0').parent().addClass('error');
		    }
		    // Here to avoid recursion.
		    doingformcheck = 0;
		});
		// Prevent step from advancing until check is finished.
		return false;
	    } 
	    if (ispprofile) {
		if (selected_uuid != loaded_uuid) {
		    $('#stepsContainer-p-1 > div')
			.attr('style','display:block');
		    ppstart.StartPP({
			uuid         : selected_uuid,
			ppdivname    : "pp-container",
			registered   : registered,
			isadmin      : isadmin,
			callback     : ConfigureDone,
			rspec        : null,
			multisite    : multisite
		    });
		    loaded_uuid = selected_uuid;
		    ppchanged = true; 
		}
	    }
	    else if (isscript && rerunscripts && !doingrunscript) {
		// Run the genilib script to get an updated rspec.
		doingrunscript = 1;
		RunScript(selected_uuid, function (success) {
		    if (success) {
			$('#stepsContainer-t-0').parent().removeClass('error');
			$('#stepsContainer').steps('next');
		    }
		    else {
			$('#stepsContainer-t-0').parent().addClass('error');
		    }
		    // Here to avoid recursion.
		    doingrunscript = 0;
		});
		// Prevent step from advancing until check is finished.
		return false;
	    }
	    else {
		$('#stepsContainer-p-1 > div').attr('style','display:none');
		loaded_uuid = selected_uuid;
	    }
	}
	else if (currentIndex == 1 && newIndex == 2) {
	    if (ispprofile && ppchanged) {
		ppstart.HandleSubmit(function(success) {
		    if (success) {
			ppchanged = false;
			$('#stepsContainer-t-1').parent().removeClass('error');
			$('#stepsContainer').steps('next');
		    }
		    else {
			$('#stepsContainer-t-1').parent().addClass('error');
		    }
		});
		// We do not proceed until the form is submitted
		// properly. This has a bad side effect; the steps
		// code assumes this means failure and adds the error
		// class.
		return false;
	    }
	}
	if (currentIndex == 2) {
	    SwitchJacks('small');
	}
	if (currentIndex == 0 && selected_uuid == null) {
	    return false;
	}
	return true;
    }

    // Step is done changing.
    function StepChanged(step, event, currentIndex, priorIndex) {
	var cIndex = currentIndex;
	if (currentIndex == 1) {
	    // If the profile isn't parameterized, skip the second step
	    if (!ispprofile) {
		if (priorIndex < currentIndex) {
		    // Generate the profile on the third tab
		    ShowProfileSelectionInline($('#profile_name .current'),
			       $('#stepsContainer-p-2 #inline_jacks'), true);

		    $(step).steps('next');
		    $('#stepsContainer-t-1').parent().removeClass('done')
			.addClass('disabled');
		}
		if (priorIndex > currentIndex) {
		    $(step).steps('previous');
		    cIndex--;
		}
	    }
	    $('#pp_form input').change(function() {
		ppchanged = true;
	    });
	    $('#pp_form select').change(function() {
		ppchanged = true;
	    });

	    // TEMPORARY STOPGAP
	    // Refer to Issue #71
	    // https://gitlab.flux.utah.edu/emulab/emulab-devel/issues/71
	    if ($('#pp_form #hwinfo').length == 0) {
		$('#pp_form input[data-key=osNodeType]').parent().append(''+
		    '<a href="http://docs.cloudlab.us/hardware.html" style="'+
			'position:absolute;'+
			'right:21px;'+
			'top: 8.5px;'+
		    '" target="_blank">'+
		    '<span id="hwinfo" class="glyphicon glyphicon-info-sign" style="font-size:16px;"'+
			'data-toggle="popover" data-trigger="hover"'+
			'data-content="Click here to see what hardware types are available">'+
		    '</span>'+
		    '</a>'+
		'');
	    }

	    $('#hwinfo').popover({
		trigger: 'hover',
		placement: 'auto',
		container: 'body',
	    });

	    // END STOPGAP
	}
	else if (currentIndex == 2 && priorIndex == 1) {
	    // Keep the two panes the same height
	    $('#inline_container').css('height',
				       $('#finalize_container').outerHeight());
	// Chrome was having an issue where Jacks was not responding to
	// the height change. Had to also add to Jacks root.
	$('#inline_jacks').css('height',
			      $('#finalize_container').outerHeight());
	}
	if (currentIndex < priorIndex) {
	    // Disable going forward by clicking on the labels
	    for (var i = cIndex+1; i < $('.steps > ul > li').length; i++) {
		$('#stepsContainer-t-'+i).parent()
		    .removeClass('done').addClass('disabled');
	    }
	}
    }

    /*
     * Check the form values on step 0 of the wizard.
     */
    function CheckStep0(step_callback)
    {
	SubmitForm(1, 0, function (json) {
	    if (json.code == 0) {
		step_callback(true);
		return;
	    }
	    // Internal error.
	    if (json.code < 0) {
		step_callback(false);
		sup.SpitOops("oops", json.value);
		return;
	    }
	    // Form error
	    if (json.code == 2) {
		// Regenerate page with errors.
		ShowFormErrors(json.value);
		step_callback(false);
		return;
	    }
	    // Email not verified, throw up form.
	    if (json.code == 3) {
		sup.ShowModal('#verify_modal');
		
		var doverify = function() {
		    var check_callback = function(json) {
			console.info(json);

			if (!json.code) {
			    // Token was good, we can keep going.
			    sup.HideModal('#verify_modal');
			    $('#verify_modal_submit').off("click");
			    $('#verification_token').parent()
				.removeClass("has-error");
			    $('#verification_token_error')
				.addClass("hidden");

			    if (_.has(json.value, "cookies")) {
				SetCookies(json.value.cookies);
			    }
			    // Redirect if so instructed.
			    if (_.has(json.value, "redirect")) {
				window.location.replace(json.value.redirect);
				return;
			    }
			    // Otherwise continue the work flow.
			    step_callback(true);
			    return;
			}
			// Bad token. Show the error. Continue button
			// is still active.
			$('#verification_token').parent().addClass("has-error");
			$('#verification_token_error').removeClass("hidden");
			$('#verification_token_error').html("Incorrect!");
		    };
		    var token = $('#verification_token').val();
		    var xmlthing = sup.CallServerMethod(null, "instantiate",
							"VerifyEmail",
							{"token" : token});
		    xmlthing.done(check_callback);
		};
		$('#verify_modal_submit').on("click", function (event) {
		    // Submit token for check. We loop until it passes.
		    doverify();
		});
	    }
	});
    }

    /*
     * Run the genilib script.
     */
    function RunScript(uuid, step_callback)
    {
	var callback = function(json) {
	    $("#waitwait-modal").modal('hide');
	    console.info(json);

	    if (json.code == 0) {
		selected_rspec = json.value;
		step_callback(true);
		return;
	    }
	    // Internal error.
	    if (json.code) {
		step_callback(false);
		sup.SpitOops("oops", json.value);
		return;
	    }
	};
	$("#waitwait-modal").modal('show');
	var xmlthing = sup.CallServerMethod(null, "instantiate",
					    "RunScript",
					    {"uuid" : uuid});
	xmlthing.done(callback);
    };

    function Instantiate()
    {
	if (webonly != 0) {
	    event.preventDefault();
	    sup.SpitOops("oops",
			 "You do not belong to any projects at your Portal, " +
			 "so you have have very limited capabilities. Please " +
			 "join or create a project at your " +
			 (portal && portal != "" ?
			  "<a href='" + portal + "'>Portal</a>" : "Portal") +
			 " to enable more capabilities. Thanks!")
	    return false;
	}
	// Prevent double click.
	if ($(this).data('submitted') === true) {
	    // Previously submitted - don't submit again
	    console.info("Ignoring double submit");
	    event.preventDefault();
	    return false;
	}
	else {
	    // See if all cluster selections have been made. Seems
	    // to be a common problem.
	    if (!AllClustersSelected()) {
		alert("Please make all your cluster selections!");
		event.preventDefault();
		return false;
	    }
	    // Mark it so that the next submit can be ignored
	    $(this).data('submitted', true);
	}
	
	// Submit with checkonly first, then for real
	SubmitForm(1, 2, function (json) {
	    console.info(json);
	    // Internal error.
	    if (json.code < 0) {
		sup.SpitOops("oops", json.value);
		return;
	    }
	    // Form error
	    if (json.code == 2) {
		ShowFormErrors(json.value);
		return;
	    }
	    $("#waitwait-modal").modal('show');
	    SubmitForm(0, 2, function(json) {
		$("#waitwait-modal").modal('hide');
		if (json.code) {
		    console.info(json);
		    if (json.code == 2) {
			ShowFormErrors(json.value);
			return;
		    }
		    sup.SpitOops("oops", json.value);               
		}
		/*
		 * The return value will have a redirect url in it,
		 * and some optional cookies.
		 */
		if (_.has(json.value, "cookies")) {
		    SetCookies(json.value.cookies);
		}
		window.location.replace(json.value.redirect);
	    });
	});
	return true;
    }

    function ShowFormErrors(errors) {
	$('.step-forms').find('.format-me').each(function () {
	    var input = $(this).find(":input")[0];
	    var label = $(this).find(".control-error")[0];
	    var key   = $(input).data("key");
	    if (key && _.has(errors, key)) {
		$(this).addClass("has-error");
		$(label).html(_.escape(errors[key]));
		$(label).removeClass("hidden");
	    }
	});
	// General Error on the last step.
	if (_.has(errors, "error")) {
	    $('#general_error').html(_.escape(errors["error"]));
	}
    }

    function ClearFormErrors() {
	$('.step-forms').find('.format-me').each(function () {
	    var input = $(this).find(":input")[0];
	    var label = $(this).find(".control-label")[0];
	    var key   = $(input).data("key");
	    if (key) {
		$(this).removeClass("has-error");
		$(label).html("");
		$(label).addClass("hidden");
	    }
	});
	$('#general_error').html("");
    }

    //
    // Submit the form. The step matters only when checking.
    //
    function SubmitForm(checkonly, step, callback)
    {
	// Current form contents as formfields array.
	var formfields  = {};
	var sites       = {};
	
	var rpc_callback = function(json) {
	    console.info(json);
	    callback(json);
	}
	ClearFormErrors();
	// Convert form data into formfields array, like all our
	// form handler pages expect.
	var fields = $('.step-forms').serializeArray();
	$.each(fields, function(i, field) {
	    /*
	     * The sites array is special since we want that to be
	     * an array inside of the formfields array, and serialize
	     * is not going to do that for us. 
	     */
	    var site = /^sites\[(.*)\]$/g.exec(field.name);
	    if (site) {
		sites[site[1]] = field.value;
	    }
	    else if (! (field.name == "where" && field.value == "(any)")) {
		formfields[field.name] = field.value;
	    }
	});
	if (Object.keys(sites).length) {
	    formfields["sites"] = sites;
	}
	console.info(formfields);
	var xmlthing = sup.CallServerMethod(null, "instantiate",
					    (checkonly ?
					     "CheckForm" : "Submit"),
					    {"formfields" : formfields,
					     "step"       : step});
	xmlthing.done(rpc_callback);
    }

    function SetCookies(cookies) {
	// Delete existing cookies first
	var expires = "expires=Thu, 01 Jan 1970 00:00:01 GMT;";

	$.each(cookies, function(name, value) {
	    document.cookie = name + '=; ' + expires;

	    var cookie = 
		name + '=' + value.value +
		'; domain=' + value.domain +
		'; max-age=' + value.expires + '; path=/; secure';

	    document.cookie = cookie;
	});
    }

    function SetCookie(name, value, days) {
	// Delete existing cookies first
	var expires = "expires=Thu, 01 Jan 1970 00:00:01 GMT;";
	document.cookie = name + '=; ' + expires;

	var date = new Date();
	date.setTime(date.getTime()+(days*24*60*60*1000))

	var cookie = name + '=' + value +
		'; expires=' + date.toGMTString() + '; path=/';

	document.cookie = cookie;
    }

    // Cookie parser found from Google
    function GetCookie(name) {
	var nameEQ = name + "=";
	var ca = document.cookie.split(';');
	for(var i=0;i < ca.length;i++) {
	    var c = ca[i];
	    while (c.charAt(0)==' ') c = c.substring(1,c.length);
	    if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length,c.length);
	}
	return null;
    }
    
    function CreateClusterStatus() {
	//console.log("CreateClusterStatus", monitor);
	if (monitor == null || $.isEmptyObject(monitor)) {
	    return;
	}

	$('#finalize_options .cluster-group').each(function() {
	    if ($(this).hasClass("pickered")) {
		return;
	    }
	    $(this).addClass("pickered");
	    
	    var resourceTypes = ["PC"];
	    // Have to do look this up based off of the site name since that's 
	    // the only hook Jacks is giving.
	    var label = $(this).find('.control-label').attr('name');
	    if (types && label && types[label]) {
		if (types[label]['emulab-xen']) {
		    if (Object.keys(types[label]).length == 1) {
			resourceTypes = [];
		    }
		    resourceTypes.push("VM");
		}
	    }
	    var which = $(this).parent().attr('id');

	    var html = wt.ClusterStatusHTML($('#'+which+' .form-control option'), window.FEDERATEDLIST);

	    $('#'+which+' .form-control').after(html);
	    $('#'+which+' select.form-control').addClass('hidden');

	    html.find('.dropdown-menu a').on('click', function() {    
		wt.StatusClickEvent(html, this);
		$('#'+which+' .form-control').val($('#'+which+' .cluster_picker_status .value').html()); 
	    });

	    _.each(amlist, function(name, key) {
		var data = monitor[key];
		var target = $('#'+which+' .cluster_picker_status .dropdown-menu .enabled a:contains("'+name+'")');
		if (data && !$.isEmptyObject(data)) {
		    // Calculate testbed rating and set up tooltips.
		    var rating = wt.CalculateRating(data, resourceTypes);
		    
		    target.parent().attr('data-health', rating[0]).attr('data-rating', rating[1]);
		    
		    var classes = wt.AssignStatusClass(rating[0], rating[1]);
		    target.addClass(classes[0]).addClass(classes[1]);

		    target.append(wt.StatsLineHTML(classes, rating[2]));
		}
	    });

	    var sort = function (a, b) {
		var aHealth = Math.ceil((+a.dataset.health)/50);
		var bHealth = Math.ceil((+b.dataset.health)/50);

		if (aHealth > bHealth) {
		    return -1;
		}
		else if (aHealth < bHealth) {
		    return 1;
		}
		return +b.dataset.rating - +a.dataset.rating;
	    };

	    $('#'+which+' .cluster_picker_status .dropdown-menu').find('.enabled.native').sort(sort).prependTo($('#'+which+' .cluster_picker_status .dropdown-menu'));
	    $('#'+which+' .cluster_picker_status .dropdown-menu').find('.enabled.federated').sort(sort).insertAfter($('#'+which+' .cluster_picker_status .dropdown-menu .federatedDivider'));

	    var pickerStatus = $('#'+which+' .cluster_picker_status .dropdown-menu .enabled a');
	    if (pickerStatus.length == 2) {
		pickerStatus[1].click();
	    }
	    else {
		pickerStatus[0].click();
	    }
	});
	
	$('[data-toggle="tooltip"]').tooltip();
    }

    function SwitchJacks(which) {
	if (which == 'small' && $('#stepsContainer-p-2 #inline_jacks').html() == '') {
			$('#stepsContainer #finalize_container').removeClass('col-lg-12 col-md-12 col-sm-12');
		$('#stepsContainer #finalize_container').addClass('col-lg-8 col-md-8 col-sm-8');
			$('#stepsContainer #inline_large_jacks').html('');
			$('#inline_large_container').addClass('hidden');
			if (ispprofile) {
				ppstart.ChangeJacksRoot($('#stepsContainer-p-2 #inline_jacks'), true);
			}
			else {
				ShowProfileSelectionInline($('#profile_name .current'), $('#stepsContainer-p-2 #inline_jacks'), true);
			}
			$('#stepsContainer-p-2 #inline_container').removeClass('hidden');
	}
	else if (which == 'large') {
		// Sometimes the steps library will clean up the added elements
		if ($('#inline_large_container').length === 0) {        
			$('<div id="inline_large_container" class="hidden"></div>').insertAfter('#stepsContainer .content');
				$('#inline_large_container').html(''
					+'<button id="closeLargeInline" type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>'
					+'<div id="inline_large_jacks"></div>');
				$('#stepsContainer #inline_large_container').addClass('col-lg-8 col-lg-offset-2 col-md-8 col-md-offset-2 col-sm-10 col-sm-offset-1 col-xs-12 col-xs-offset-0');
		
				$('#closeLargeInline').click(function() {
					SwitchJacks('small');
				});
		}

		$('#stepsContainer #finalize_container').removeClass('col-lg-8 col-md-8 col-sm-8');
			$('#stepsContainer #finalize_container').addClass('col-lg-12 col-md-12 col-sm-12');
			$('#stepsContainer-p-2 #inline_jacks').html('');
			$('#stepsContainer-p-2 #inline_container').addClass('hidden');
			if (ispprofile) {
				ppstart.ChangeJacksRoot($('#stepsContainer #inline_large_jacks'), false);
			}
			else {
				ShowProfileSelectionInline($('#profile_name .current'), $('#stepsContainer #inline_large_jacks'), false);
			}
			$('#inline_large_container').removeClass('hidden');
	}
    }

    function resetForm($form) {
	$form.find('input:text, input:password, select, textarea').val('');
    }

    function ShowProfileSelection(selectedElement) {
	if (!$(selectedElement).hasClass('selected')) {
	    $('#profile_name li').each(function() {
		$(this).removeClass('selected');
	    });
	    $(selectedElement).addClass('selected');
	}
	console.info("ShowProfileSelection: " +
		     $(selectedElement).attr('value'));
	
	var continuation = function(profile_blob) {
	    var profileInfo = profilelist[$(selectedElement).attr('value')]
	    var isFavorite = profileInfo.favorite;

	    // Add title name and favorite button
	    $('#showtopo_title').html("<h3>" + profile_blob.name + "</h3>" +
		"<button id='favorite_button' class='btn btn-default btn-sm'>" + 
		"<span id='set_favorite' class='glyphicon glyphicon-star" + ((isFavorite == 1) ? " favorite" : "") + "'></span>" + 
		"</button>");

	    $('#showtopo_author').html(profile_blob.creator);
	    $('#showtopo_project').html(profileInfo.project);  
	    $('#showtopo_version').html(profile_blob.version); 
	    $('#showtopo_last_updated').html(profile_blob.created);
	    $('#showtopo_description').html(profile_blob.description);

	    sup.maketopmap('#showtopo_div',
			   profile_blob.rspec, false, !multisite);

	    // Set favorite toggle click event
	    $('#favorite_button').click(function() {
		ToggleFavorite(selectedElement)}
	    );
	};
	GetProfile($(selectedElement).attr('value'), continuation);
    }
    
    function ToggleFavorite(target) {
	var wasFav = profilelist[$(target).attr('value')].favorite;
	var callback = function(e) {    
	    if (wasFav) {
		$('#set_favorite').removeClass('favorite');
		profilelist[$(target).attr('value')].favorite = 0;
		$('#favorites li[value='+$(target).attr('value')+']').remove();

		// They were selected on the item in the favorites list, which was just removed
		// Adjust their selection to the first instance of that profile.
		if ($('#profile_name .selected').length == 0) {
		    $('#profile_name li[value='+$(target).attr('value')+']')[0].click();
		}

		if ($('#favorites li').length == 0) {
		    $('#title_favorites').addClass('hidden');
		}
	    }
	    else {
		$('#set_favorite').addClass('favorite');
		profilelist[$(target).attr('value')].favorite = 1;

		var clone = $(target).clone();
		$(clone).removeClass('selected');
		$('#favorites').append(clone);
		$(clone).click(function (event) {
		    event.preventDefault();
		    ShowProfileSelection(event.target);
		});

		$('#title_favorites').removeClass('hidden');
	    }
	}
	var $xmlthing = sup.CallServerMethod(ajaxurl,
					     "instantiate", (wasFav ? "ClearFavorite" : "MarkFavorite"),
					     {"uuid" : $(target).attr('value')});
	$xmlthing.done(callback);
    }

    // Used to generate the topology on Tab 3 of the wizard for non-pp profiles
    function ShowProfileSelectionInline(selectedElement, root, selectionPane) {
	console.info("ShowProfileSelectionInline: " +
		     $(selectedElement).attr('value'));
	
	editor = new JacksEditor(root, true, true,
				 selectionPane, true, !multisite);
	editor.show(selected_rspec);
    }

    function ChangeProfileSelection(selectedElement) {
	if (!$(selectedElement).hasClass('current')) {
	    $('#profile_name li').each(function() {
		$(this).removeClass('current');
	    });
	    $(selectedElement).addClass('current');
	}
	console.info("ChangeProfileSelection: " +
		     $(selectedElement).attr('value'));
	
	var profile_name = $(selectedElement).attr('name');
	var profile_value = $(selectedElement).attr('value');
	$('#selected_profile').attr('value', profile_value);
	$('#selected_profile_text').html("" + profile_name);
	
	var continuation = function(profile_blob) {
	    $('#showtopo_title').html("<h3>" + profile_blob.name + "</h3>");
	    $('#showtopo_description').html(profile_blob.description);
	    $('#selected_profile_description').html(profile_blob.description);
	    $('#finalize_profile_name').text(profile_blob.name);
	    $('#finalize_profile_version').text(profile_blob.version);

	    ispprofile       = profile_blob.ispprofile;
	    isscript         = profile_blob.isscript;
	    selected_uuid    = profile_value;
	    selected_rspec   = profile_blob.rspec;
	    selected_version = profile_blob.version;
	    amdefault        = profile_blob.amdefault;

	    CreateAggregateSelectors(selected_rspec);
	    
	    // Set the default aggregate.
	    if ($('#profile_where').length) {
		// Deselect current option.
		$('#profile_where option').prop("selected", false);
		// Find and select new option.
		$('#profile_where option')
		    .filter('[value="'+ amdefault + '"]')
		    .prop('selected', true);            
	    }
	};
	GetProfile($(selectedElement).attr('value'), continuation);
    }
    
    function GetProfile(profile, continuation) {
	var callback = function(json) {
	    if (json.code) {
		alert("Could not get profile: " + json.value);
		return;
	    }
	    //console.info(json);
	    
	    var xmlDoc = $.parseXML(json.value.rspec);
	    var xml    = $(xmlDoc);
	    console.log(json);
	    /*
	     * We now use the desciption from inside the rspec, unless there
	     * is none, in which case look to see if the we got one in the
	     * rpc reply, which we will until all profiles converted over to
	     * new format rspecs.
	     */
	    var description = null;
	    $(xml).find("rspec_tour").each(function() {
		$(this).find("description").each(function() {
		    var marked = require("marked");
		    description = marked($(this).text());
		});
	    });
	    if (!description || description == "") {
		description = "Hmm, no description for this profile";
	    }
	    json.value.description = description;
	    continuation(json.value);
	}
	var $xmlthing = sup.CallServerMethod(ajaxurl,
					     "instantiate", "GetProfile",
					     {"uuid" : profile});
	$xmlthing.done(callback);
    }

    /*
     * Callback from the PP configurator. Stash rspec into the form.
     */
    function ConfigureDone(newRspec) {
	// If not a registered user, we do not get an rspec back, since
	// the user is not allowed to change the configuration.
	if (newRspec) {
	    $('#pp_rspec_textarea').val(newRspec);
	    selected_rspec = newRspec;
	    CreateAggregateSelectors(newRspec);
	}
	if (window.NOPPRSPEC) {
	    alert("Guest users may configure parameterized profiles " +
		  "for demonstration purposes only. The parameterized " +
		  "configuration will not be used if you Create this " +
		  "experiment.");
	}
    }

    var sites  = {};
    var siteIdToSiteNum = {};
    /*
     * Build up a list of Aggregate selectors. Normally just one, but for
     * a multisite aggregate, need more then one.
     */
    function CreateAggregateSelectors(rspec)
    {
	var xmlDoc = $.parseXML(rspec);
	var xml    = $(xmlDoc);
	var html   = "";
	var bound  = 0;
	var count  = 0;
	sites = {};

	//console.info("CreateAggregateSelectors");

	/*
	 * Find the sites. Might not be any if not a multisite topology
	 */
	$(xml).find("node").each(function() {
	    var node_id = $(this).attr("client_id");
	    var site    = this.getElementsByTagNameNS(JACKS_NS, 'site');
	    var manager = $(this).attr("component_manager_id");

	    // Keep track of how many bound nodes, of the total.
	    count++;

	    if (manager && manager.length) {
		var parser = /^urn:publicid:idn\+([\w#!:.]*)\+/i;
		var matches = parser.exec(manager);
		if (! matches) {
		    console.error("Could not parse urn: " + manager);
		    return;
		}
		// Bound node, no dropdown will be provided for these
		// nodes, and if all nodes are bound, no dropdown at all.
		bound++;
	    }
	    else if (site.length) {
		var siteid = $(site).attr("id");
		if (siteid === undefined) {
		    console.error("No site ID in " + site);
		    return;
		}
		sites[siteid] = siteid;
	    }
	});

	// All nodes bound, no dropdown.
	if (count == bound) {
	    $("#cluster_selector").addClass("hidden");
	    // Clear the form data.
	    $("#cluster_selector").html("");
	    // Tell the server not to whine about no aggregate selection.
	    $("#fully_bound").val("1");
	    return;
	}

	// Clear for new profile.
	siteIdToSiteNum = {};
	var sitenum = 0;

	// Create the dropdown selection lists.
	var options = "";
	_.each(amlist, function(name, key) {
	    options = options +
		"<option value='" + name + "'>" + name + "</option>";
	});

	// If multisite is disabled for the user, or no sites or 1 site.
	if (!multisite || Object.keys(sites).length <= 1) {
	    html = 
		"<div id='nosite_selector' " +
		"     class='form-horizontal experiment_option'>" +
		"  <div class='form-group cluster-group'>" +
		"    <label class='col-sm-4 control-label' name='" + _.values(sites)[0] + "' " +
		"           style='text-align: right;'>Cluster:</a>" +
		"    </label> " +
		"    <div class='col-sm-6'>" +
		"      <select name='where' id='profile_where' " +
		"              class='form-control'>" +
		"        <option value=''>Please Select</option>" +
		options +
		"      </select>" +
		"    </div>" +
		"<div class='col-sm-4'></div>" +
		"<div class='col-sm-6 alert alert-danger' id='where-nowhere' style='display: none; margin-top: 5px; margin-bottom: 5px'>This site <b>will not work on any clusters</b>. All clusters are unselectable.</div>" +
		"  </div>" +
		"</div>";
	}
	else {
	    _.each(sites, function(siteid) {
		siteIdToSiteNum[siteid] = sitenum;

		html = html +
		    "<div id='site"+sitenum+"cluster' " +
		    "     class='form-horizontal experiment_option'>" +
		    "  <div class='form-group cluster-group'>" +
		    "    <label class='col-sm-4 control-label' name='" + siteid + "' " +
		    "           style='text-align: right;'>"+
		    "          Site " + siteid  + " Cluster:</a>" +
		    "    </label> " +
		    "    <div class='col-sm-6'>" +
		    "      <select name=\"sites[" + siteid + "]\"" +
		    "              class='form-control'>" +
		    "        <option value=''>Please Select</option>" +
		    options +
		    "      </select>" +
		    "    </div>" +
		    "<div class='col-sm-4'></div>" +
		    "<div class='col-sm-6 alert alert-danger' id='where-nowhere' style='display: none; margin-top: 5px; margin-bottom: 5px'>This site <b>will not work on any clusters</b>. All clusters are unselectable.</div>" +
		    "  </div>" +
		    "</div>";
		sitenum++;
	    });
	}
	//console.info(html);

	$("#cluster_selector").html("");
	$("#cluster_selector").html(html);
	updateWhere();  
	CreateClusterStatus();
	$("#cluster_selector").removeClass("hidden");
    }

    /*
     * Make sure all clusters selected before submit.
     */
    function AllClustersSelected() 
    {
	var allgood = 1;

	$('#cluster_selector').find('select').each(function () {
	    if ($(this).val() == null || $(this).val() == "") {
		allgood = 0;
		return;
	    }
	});
	return allgood;
    }

    var constraints;
    var context;

    function contextReady(data)
    {
      context = data;
      if (typeof(context) === 'string')
      {
	context = JSON.parse(context);
      }
      if (context.canvasOptions.defaults.length === 0)
      {
	delete context.canvasOptions.defaults;
      }
      constraints = new Constraints(context);
      jacks.instance = new window.Jacks({
	mode: 'viewer',
	source: 'rspec',
	root: '#jacks-dummy',
	nodeSelect: true,
	readyCallback: function (input, output) {
	  jacks.input = input;
	  jacks.output = output;
	  jacks.output.on('found-images', onFoundImages);
	  jacks.output.on('found-types', onFoundTypes);
	  updateWhere();
	},
	canvasOptions: context.canvasOptions,
	constraints: context.constraints
      });
    }

    var foundImages = [];

    function onFoundImages(images)
    {
	if (! doconstraints) {
	    return true;
	}
	if (! _.isEqual(foundImages, images)) {
	    foundImages = images;

	    UpdateImageConstraints();
	}
	return true;
    }

    function onFoundTypes(t) 
    {
	types = {};
	_.each(t, function(item) {
	    types[item.name] = item.types;
	});
    }

    /*
     * Update the image constraints if anything changes.
     */
    function UpdateImageConstraints() {
	if (!foundImages.length || !doconstraints) {
	    return;
	}
      
	$('#stepsContainer .actions a[href="#finish"]').attr('disabled', true);
	var callback = function(json) {
	    if (json.code) {
		alert("Could not get image info: " + json.value);
		return;
	    }
	    // This gets munged someplace, and so the printed value
	    // is not what actually comes back. Copy before print.
	    var mycopy = $.extend(true, {}, json.value);
	    //console.log('json', mycopy);
	    constraints = new Constraints(context);
	    constraints.addPossibles({ images: foundImages });
	    allowWithSites(json.value[0].images, json.value[0].constraints);
	    CreateAggregateSelectors(selected_rspec);
	    $('#stepsContainer .actions a[href="#finish"]')
		.removeAttr('disabled');
	};
	/*
	 * Must pass the selected project along for constraint checking.
	 */
	var $xmlthing =
	    sup.CallServerMethod(ajaxurl,
				 "instantiate", "GetImageInfo",
				 {"images"  : foundImages,
				  "project" : $('#project_selector #profile_pid')
						  .val()});
	$xmlthing.done(callback);
	return true;
    }

  function allowWithSites(newImages, newConstraints)
  {
    console.log('newImages', newImages);
    console.log('newConstraints', newConstraints);
    var sites = context.canvasOptions.site_info;
    var finalItems = [];
    _.each(newConstraints, function (item) {
      console.log('item:', item);
      var valid = [];
      _.each(_.keys(sites), function (key) {
	// Items from server might just be comma-separated lists in
	// strings instead of split out properly. Let's split them out
	// here.
	item.node.hardware = splitItems(item.node.hardware);
	item.node.types = splitItems(item.node.types);

	// The image list returned are the only valid images
	if (_.findWhere(newImages, { id: item.node.images[0] }))
	{
	  _.each(item.node.hardware, function (hardware) {
	    if (_.contains(sites[key].hardware, hardware))
	    {
	      finalItems.push({
		node: {
		  hardware: [hardware],
		  images: item.node.images,
		  aggregates: [key]
		}
	      });
	    }
	  });
	  _.each(item.node.types, function (type) {
	    if (_.contains(sites[key].types, type))
	    {
	      finalItems.push({
		node: {
		  types: [type],
		  images: item.node.images,
		  aggregates: [key]
		}
	      });
	    }
	  });
	}
      });
    });
    //console.log(finalItems);
    constraints.allowAllSets(finalItems);
    constraints.allowAllSets([
      {
	node: {
	  aggregates: ['!'],
	  images: ['!'],
	  hardware: ['!']
	}
      },
      {
	node: {
	  aggregates: ['!'],
	  images: ['!'],
	  types: ['!']
	}
      },
    ]);
  }

  function splitItems(list) {
    var result = [];
    _.each(list, function (item) {
      result = result.concat(item.split(','));
    });
    return result;
  }

    function contextFail(fail1, fail2)
    {
	console.log('Failed to fetch context', fail1, fail2);
	alert('Failed to fetch context from ' + contextUrl + '\n\n' + 'Check your network connection and try again or contact testbed support with this message and the URL of this webpage.');
    }

    function updateWhere()
    {
	//console.info("updateWhere");
	
	if (jacks.input && constraints && selected_rspec)
	{
	  jacks.input.trigger('change-topology',
			      [{ rspec: selected_rspec }],
			      { constrainedFields: finishUpdateWhere });
	}
    }

    function finishUpdateWhere(allNodes, nodesBySite)
    {
	if (!multisite || Object.keys(sites).length <= 1) {
	    updateSiteConstraints(allNodes,
				  $('#cluster_selector .cluster-group'));
	}
	else {
	    _.each(_.keys(sites), function (siteId) {
		var nodes   = nodesBySite[siteId];
		var sitenum = siteIdToSiteNum[siteId];
		var domid   = '#cluster_selector #site' + sitenum + 'cluster' +
		    '.cluster-group';
		if (nodes) {
		    updateSiteConstraints(nodes, $(domid));
		}
		else {
		    console.log('Could not find siteId', siteId, nodesBySite);
		}
	    })
	}
    }

    function updateSiteConstraints(nodes, domNode)
    {
      var allowed = [];
      var rejected = [];
      var bound = nodes;
      var subclause = 'node';
      var clause = 'aggregates';
      allowed = constraints.getValidList(bound, subclause,
					 clause, rejected);

      if (0) {
	console.info("updateSiteConstraints");
	console.info(domNode);
	console.info(bound);
	console.info(allowed);
	console.info(rejected);
      }
	
      if (allowed.length == 0)
      {
	domNode.find('#where-warning').hide();
	domNode.find('#where-nowhere').show();
      }
      else if (rejected.length > 0)
      {
	domNode.find('#where-warning').show();
	domNode.find('#where-nowhere').hide();
      }
      else
      {
	domNode.find('#where-warning').hide();
	domNode.find('#where-nowhere').hide();
      }
      domNode.find('select').children().each(function () {
	var value = $(this).attr('value');
	// Skip the Please Select option
	if (value == "") {
	    return;
	}
	var key = amValueToKey[value];
	var i = 0;
	var found = false;
	for (; i < allowed.length; i += 1)
	{
	  if (allowed[i] === key)
	  {
	    found = true;
	    break;
	  }
	}
	if (found)
	{
	  $(this).prop('disabled', false);
	  if (allowed.length == 1) {
	      $(this).attr('selected', "selected");
	      // This does not appear to do anything, at least in Chrome
	      $(this).prop('selected', true);
	  }
	}
	else
	{
	  $(this).prop('disabled', true);
	  $(this).removeAttr('selected');
	  // See above comment.
	  $(this).prop('selected', false);
	}
      });
    }

    $(document).ready(initialize);
});
