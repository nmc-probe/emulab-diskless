require(window.APT_OPTIONS.configObject,
	['underscore', 'js/quickvm_sup',
	 'js/lib/text!template/ssh-keys.html',
	 'js/lib/text!template/oops-modal.html',
	 'js/lib/text!template/waitwait-modal.html',
	 'filestyle',
	],
function (_, sup, sshkeysString, oopsString, waitwaitString)
{
    'use strict';
    var embedded        = 0;
    var sshkeysTemplate = _.template(sshkeysString);

    function initialize()
    {
	window.APT_OPTIONS.initialize(sup);
	embedded = window.EMBEDDED;
	var pubkeys = JSON.parse(_.unescape($('#sshkey-list')[0].textContent));

	var html = sshkeysTemplate({
	    pubkeys:	pubkeys,
	});
	$('#page-body').html(html);
	$('#oops_div').html(oopsString);
	$('#waitwait_div').html(waitwaitString);

	//
	// Fix for filestyle problem; not a real class I guess, it
	// runs at page load, and so the filestyle'd button in the
	// form is not as it should be.
	//
	$('#sshkey_file').each(function() {
	    $(this).filestyle({input      : false,
			       buttonText : $(this).attr('data-buttonText'),
			       classButton: $(this).attr('data-classButton')});
	});

	//
	// File upload handler.
	// 
	$('#sshkey_file').change(function() {
		var reader = new FileReader();
		reader.onload = function(event) {
		    $('#sshkey_data').val(event.target.result);
		};
		reader.readAsText(this.files[0]);
	});

	// Handler for all of the delete buttons.
	$('.delete_pubkey_button').click(function (event) {
	    event.preventDefault();
	    var index     = $(this)[0].dataset['key'];
	    HandleDeleteKey(index);
	});

	// Add key button.
	$('#ssh_addkey_button').click(function (event) {
	    event.preventDefault();
	    HandleAddKey();
	});
    }

    /*
     * Submit key, look for error.
     */
    function HandleAddKey()
    {
	var keydata = $('#sshkey_data').val();
	if (keydata == "") {
	    alert("Key cannot be blank!");
	    return;
	}
	var callback = function(json) {
	    console.info(json);
	    sup.HideModal("#waitwait-modal");

	    if (json.code) {
		sup.SpitOops("oops", json.value);
		return;
	    }
	    if (embedded) {
		window.parent.location.replace("../ssh-keys.php");
	    }
	    else {
		window.location.replace("ssh-keys.php");
	    }
	}
	sup.ShowModal("#waitwait-modal");

	var xmlthing = sup.CallServerMethod(null, "ssh-keys", "addkey",
					    {"keydata" : keydata});
	xmlthing.done(callback);
    }
    
    function HandleDeleteKey(index)
    {
	var callback = function(json) {
	    console.info(json);
	    sup.HideModal("#waitwait-modal");

	    if (json.code) {
		sup.SpitOops("oops", json.value);
		return;
	    }
	    $('#panel_' + index).remove();
	}
	sup.ShowModal("#waitwait-modal");

	var xmlthing = sup.CallServerMethod(null, "ssh-keys", "deletekey",
					    {"index" : index});
	xmlthing.done(callback);
    }
    
    $(document).ready(initialize);
});
