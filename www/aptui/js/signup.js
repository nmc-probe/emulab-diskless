require(window.APT_OPTIONS.configObject,
	['underscore', 'js/quickvm_sup', 'js/aptforms',
	 'js/lib/text!template/about-account.html',
	 'js/lib/text!template/verify-modal.html',
	 'js/lib/text!template/signup-personal.html',
	 'js/lib/text!template/signup-project.html',
	 'js/lib/text!template/signup.html',
	 'js/lib/text!template/toomany-modal.html',
	 // jQuery modules
	 'formhelpers'],
function (_, sup, aptforms,
	  aboutString, verifyString, personalString,
	  projectString, signupString, toomanyString)
{
    'use strict';

    var aboutTemplate = _.template(aboutString);
    var verifyTemplate = _.template(verifyString);
    var personalTemplate = _.template(personalString);
    var projectTemplate = _.template(projectString);
    var signupTemplate = _.template(signupString);

    function initialize()
    {
	window.APT_OPTIONS.initialize(sup);
	$('#toomany_div').html(toomanyString);

	var fields = JSON.parse(_.unescape($('#form-json')[0].textContent));
	var errors = JSON.parse(_.unescape($('#error-json')[0].textContent));

	console.info(fields);
	console.info(errors);
	
	renderForm(fields, errors,
		   window.APT_OPTIONS.joinproject,
		   window.APT_OPTIONS.ShowVerifyModal,
		   window.APT_OPTIONS.this_user,
		   (window.APT_OPTIONS.this_user ?
		    window.APT_OPTIONS.promoting : false));

	/*
	 * When switching from start to join, show the hidden fields
	 * and change the button.
	 */
	$("input[id='startorjoin']").change(function(e){
	    if ($(this).val() == "join") {
		$('#start_project_rollup').addClass("hidden");
		$('#submit_button').text("Join Project");
		$('#signup_panel_title').text("Join Project");
	    }
	    else {
		$('#start_project_rollup').removeClass("hidden");
		$('#submit_button').text("Start Project");
		$('#signup_panel_title').text("Start Project");
	    }
	});
	if (window.APT_OPTIONS.toomany) {
	    sup.ShowModal('#toomany_modal');
	}
    }

    function renderForm(formfields, errors, joinproject, showVerify,
			thisUser, promoting)
    {
	var buttonLabel = (joinproject ? "Join Project" : "Start Project");
	var about = aboutTemplate({});
	var verify = verifyTemplate({
	    id: 'verify_modal',
	    label: buttonLabel
	});
	var personal_html = personalTemplate({
	    formfields: formfields,
	    promoting: promoting
	});
	var project_html = projectTemplate({
	    joinproject: joinproject,
	    formfields: formfields
	});
	var signup = signupTemplate({
	    button_label: buttonLabel,
	    general_error: (errors.error || ''),
	    about_account: (window.ISAPT && !thisUser ? about : null),
	    this_user: thisUser,
	    promoting: promoting,
	    joinproject: joinproject,
	    verify_modal: verify,
	    pubkey: formfields.pubkey,
	    personal_fields: personal_html,
	    project_fields: project_html,
	});
	$('#signup-body').html(aptforms.FormatFormFields(signup));
	aptforms.GenerateFormErrors('#quickvm_signup_form', errors);
	if (showVerify)
	{
	    sup.ShowModal('#verify_modal');
	}
	$('#signup_countries').bfhcountries({ country: formfields.country,
					      blank: false, ask: true });
	$('#signup_states').bfhstates({ country: 'signup_countries',
					state: formfields.state,
					blank: false, ask: true });
	
	aptforms.EnableUnsavedWarning('#quickvm_signup_form');
	
	// Handle submit button.
	$('#submit_button').click(function (event) {
	    aptforms.DisableUnsavedWarning('#quickvm_signup_form');
	});
    }
    
    $(document).ready(initialize);
});
