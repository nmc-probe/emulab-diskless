require(window.APT_OPTIONS.configObject,
	['underscore', 'js/quickvm_sup', 'js/aptforms',
	 'js/lib/text!template/myaccount.html',
	 'js/lib/text!template/verify-modal.html',
	 'js/lib/text!template/oops-modal.html',
	 'js/lib/text!template/waitwait-modal.html',
	 // jQuery modules
	 'formhelpers'],
function (_, sup, aptforms,
	  myaccountString, verifyString, oopsString, waitwaitString)
{
    'use strict';

    var myaccountTemplate = _.template(myaccountString);
    var verifyTemplate    = _.template(verifyString);

    function initialize()
    {
	window.APT_OPTIONS.initialize(sup);

	// Initial form contents.
	var fields = JSON.parse(_.unescape($('#form-json')[0].textContent));

	$('#oops_div').html(oopsString);	
	$('#waitwait_div').html(waitwaitString);	

	// Watch for USA
	if (fields.country === "USA") {
	    fields.country = "US";
	}
	renderForm(fields, null);
    }

    function renderForm(formfields)
    {
	var verify = verifyTemplate({
	    id: 'verify_modal',
	    label: "Confirm",
	});
	var myaccount = aptforms.FormatFormFields(myaccountTemplate({
	    formfields: formfields,
	    verify_modal: verify,
	    nopassword: window.APT_OPTIONS.nopassword,
	}));
	
	$('#page-body').html(myaccount);
	$('#signup_countries').bfhcountries({ country: formfields.country,
					      blank: false, ask: true });
	$('#signup_states').bfhstates({ country: 'signup_countries',
					state: formfields.state,
					blank: false, ask: true });

	aptforms.EnableUnsavedWarning('#myaccount_form', function () {
	    $('#submit_button').removeAttr("disabled");
	});
	$('#submit_button').click(function (event) {
	    event.preventDefault();
	    SubmitForm();
	    return false;
	});
	$('#verify_modal_submit').click(function (event) {
	    event.preventDefault();
	    sup.HideModal('#verify_modal');
	    SubmitForm();
	    return false;
	});
    }
    
    //
    // Submit the form.
    //
    function SubmitForm()
    {
	var submit_callback = function(json) {
	    if (json.code) {
		sup.SpitOops("oops", json.value);
		return;
	    }
	    window.location.reload();	    
	};
	var checkonly_callback = function(json) {
	    if (json.code) {
		// Email not verified, throw up form.
		if (json.code == 3) {
		    sup.ShowModal('#verify_modal');
		}
		else if (json.code != 2) {
		    sup.SpitOops("oops", json.value);		    
		}
		return;
	    }
	    aptforms.SubmitForm('#myaccount_form', "myaccount", "update",
				submit_callback);
	};
	aptforms.CheckForm('#myaccount_form', "myaccount", "update",
			   checkonly_callback);
    }
    $(document).ready(initialize);
});
