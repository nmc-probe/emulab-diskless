<?php
#
# Copyright (c) 2000-2014 University of Utah and the Flux Group.
# 
# {{{EMULAB-LICENSE
# 
# This file is part of the Emulab network testbed software.
# 
# This file is free software: you can redistribute it and/or modify it
# under the terms of the GNU Affero General Public License as published by
# the Free Software Foundation, either version 3 of the License, or (at
# your option) any later version.
# 
# This file is distributed in the hope that it will be useful, but WITHOUT
# ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
# FITNESS FOR A PARTICULAR PURPOSE.  See the GNU Affero General Public
# License for more details.
# 
# You should have received a copy of the GNU Affero General Public License
# along with this file.  If not, see <http://www.gnu.org/licenses/>.
# 
# }}}
#
$APTHOST	= "$WWWHOST";
$APTBASE	= "$TBBASE/apt";

#
# Global flag to disable accounts. We do this on some pages which
# should not display login/account info.
#
$disable_accounts = 0;

#
# So, we could be coming in on the alternate APT address (virtual server)
# which causes cookie problems. I need to look into avoiding this problem
# but for now, just change the global value of the TBAUTHDOMAIN when we do.
# The downside is that users will have to log in twice if they switch back
# and forth.
#
if ($TBMAINSITE && $_SERVER["SERVER_NAME"] == "www.aptlab.net") {
    $TBAUTHDOMAIN = ".aptlab.net";
    $APTHOST      = "www.aptlab.net";
    $WWWHOST      = "www.aptlab.net";
    $APTBASE      = "https://www.aptlab.net";
}

#
# Redefine this so APT errors are styled properly.
#
$PAGEERROR_HANDLER = function($msg, $status_code = 0) {
    global $drewheader;

    if (! $drewheader) {
	SPITHEADER();
    }
    echo $msg;
    echo "<script src='js/lib/require.js' data-main='js/null.js'></script>\n";
    SPITFOOTER();
    die("");
};

function SPITHEADER($thinheader = 0)
{
    global $TBMAINSITE;
    global $login_user, $login_status;
    global $disable_accounts, $page_title, $drewheader;
    $title = "AptLab";
    if (isset($page_title)) {
	$title .= " - $page_title";
    }
    $height = ($thinheader ? 150 : 250);
    $drewheader = 1;

    #
    # Figure out who is logged in, if anyone.
    #
    if (($login_user = CheckLogin($status)) != null) {
	$login_status = $status;
	$login_uid    = $login_user->uid();
    }

    echo "<html>
      <head>
        <title>$title</title>
        <link rel='stylesheet' href='bootstrap/css/bootstrap.css'>
        <link rel='stylesheet' href='quickvm.css'>
	<script src='js/common.js'></script>
        <script src='https://www.emulab.net/emulab_sup.js'></script>
      </head>
    <body style='display: none'>\n";
    
    if ($TBMAINSITE && file_exists("../google-analytics.php")) {
	readfile("../google-analytics.php");
    }

    echo "
    <!-- Container for body, needed for sticky footer -->
    <div id='wrap'>
         <div class='navbar navbar-static-top' role='navigation'>
           <div class='navbar-inner'>
             <div class='brand'>
                 <img src='aptlogo.png'/>
             </div>
             <ul class='nav navbar-nav navbar-right'>";
    if (!$disable_accounts) {
	if ($login_user && ISADMINISTRATOR()) {
	    echo "<li>\n";
	    if (ISADMIN()) {
		$url = CreateURL("toggle", $login_user,
				 "type", "adminon", "value", 0);
		
		echo "<a href='/$url'>
                             <img src='/redball.gif'
                                  style='height: 10px;'
                                  border='0' alt='Admin On'></a>\n";
	    }
	    else {
		$url = CreateURL("toggle", $login_user,
				 "type", "adminon", "value", 1);

		echo "<a href='/$url'>
                              <img src='/greenball.gif'
                                   style='height: 10px;'
                                   border='0' alt='Admin Off'></a>\n";
	    }
	    echo "</li>\n";
	}
	echo "<li id='loginstatus'>".
	    ($login_user ? "<p>$login_uid logged in</p>" : "") . "</li>\n";

	if (!NOLOGINS()) {
	    echo "<li id='signupitem' " .
		($login_user ? "class='hidden'" : "") . ">" .
                    "<form><a class='btn btn-primary navbar-btn'
                           id='signupbutton'
                           href='signup.php'>
                        Sign Up</a></form></li>
                  \n";
	    echo "<li id='loginitem' " .
		($login_user ? "class='hidden'" : "") . ">" .
                    "<form><a class='btn btn-primary navbar-btn'
                           id='loginbutton'
	                   data-toggle='modal'
                           href='#quickvm_login_modal'
                           data-target='#quickvm_login_modal'>
                        Login</a></form></li>
                  \n";
	}
    }
    echo "   </ul>
             <ul class='nav navbar-nav navbar-left'>
                <li><form><a class='btn btn-quickvm-home navbar-btn'
                       href='instantiate.php'>Home</a></form></li>\n";
    if (!$disable_accounts) {
	echo "  <li id='quickvm_actions_menu' class='dropdown ".
	    (!$login_user ? "hidden" : "") . "'>" .
	         "<a href='#' class='dropdown-toggle' data-toggle='dropdown'>
                    Actions <b class='caret'></b></a>
                  <ul class='dropdown-menu'>
                   <li><a href='myprofiles.php'>My Profiles</a></li>
                   <li><a href='myexperiments.php'>My Experiments</a></li>
                   <li><a href='manage_profile.php'>Create Profile</a></li>
                   <li class='divider'></li>
	           <li><a href='logout.php'>Logout</a></li>
                  </ul>
                </li>\n";
    }
    echo "   </ul>
           </div>
         </div>\n";

    SpitLoginModal("quickvm_login_modal");
    echo " <!-- Page content -->
           <div class='container'>\n";
}

function SPITFOOTER()
{
    echo "</div>
      </div>\n";
    echo "
      <!--- Footer -->
      <div>
       <div id='footer'>
        <div class='pull-left'>Powered by
             <img src='emulab-whiteout.png' id='elabpower'></div>
        <div class='pull-right'>&copy; 2013 The University of Utah</div>
       </div>
      </div>
      <!-- Placed at the end of the document so the pages load faster -->
     </body></html>\n";
}

function SPITUSERERROR($msg)
{
    PAGEERROR($msg, 0);
}

#
# Does not return; page exits.
#
function SPITAJAX_RESPONSE($value)
{
    $results = array(
	'code'  => 0,
	'value' => $value
	);
    echo json_encode($results);
}

function SPITAJAX_ERROR($code, $msg)
{
    $results = array(
	'code'  => code,
	'value' => $msg
	);
    echo json_encode($results);
}

#
# Spit out an info tooltip.
#
function SpitToolTip($info)
{
    echo "<a href='#' class='btn btn-xs' data-toggle='popover' ".
	"data-content='$info'> ".
        "<span class='glyphicon glyphicon-question-sign'></span> ".
        "</a>\n";
}

#
# Spit out the verify modal. We are not using real password authentication
# like the rest of the Emulab website. Assumed to be inside of a form
# that handles a create button.
#
function SpitVerifyModal($id, $label)
{
    echo "<!-- This is the user verify modal -->
          <div id='$id' class='modal fade'>
            <div class='modal-dialog'>
            <div class='modal-content'>
               <div class='modal-header'>
                <button type='button' class='close' data-dismiss='modal'
                   aria-hidden='true'>&times;</button>
                <h3>Important</h3>
               </div>
               <div class='modal-body'>
                    <p>Check your email for a verification code, and
                       enter it here:</p>
                       <div class='form-group'>
                        <input name='verify' class='form-control'
                               placeholder='Verification code'
                               autofocus type='text' />
                       </div>
                       <div class='form-group'>
                        <button class='btn btn-primary form-control'
                            type='submit' name='create'>
                            $label</button>
                       </div>
               </div>
            </div>
            </div>
         </div>\n";
}

#
# Spit out the login modal. 
#
function SpitLoginModal($id)
{
?>
    <!-- This is the login modal -->
    <div id='<?php echo $id ?>' class='modal fade' role='dialog'>
        <div class='modal-dialog'>
        <form id='quickvm_login_form'
              role='form'
              method='post' action='login.php'>
        <input type=hidden name=refer value=1>
        <div id='quickvm_login_form_error'
             class='align-center'></div>
        <div class='modal-content'>
           <div class='modal-header'>
            <button type='button' class='close' data-dismiss='modal'
               aria-hidden='true'>&times;</button>
               <h4 class='modal-title'>Log in to Apt</h4>
           </div>
           <div class='modal-body form-horizontal'>
             <div class='form-group'>
                       <label for='uid' class='col-sm-2 control-label'>Username</label>
                       <div class='col-sm-10'>
                           <input name='uid' class='form-control'
                                  placeholder='Aptlab.net or Emulab.net Username'
                                  autofocus type='text'>
                       </div>
                   </div>
                   <div class='form-group'>
                       <label for='password' class='col-sm-2 control-label'>Password </label>
                       <div class='col-sm-10'>
                           <input name='password' class='form-control'
                                  placeholder='Password'
                                  type='password'>
                       </div>
                   </div>
             </div>
             <div class='modal-footer'>
                   <div class='form-group'>
                        <button class='btn btn-success btn-sm'
                            id='quickvm_login_modal_button'
                            class='form-control'
                            type='submit' name='login'>
                            Login</button>
                   </div>
             </div>
        </div>
        </form>
        </div>
     </div>
<?php
}

#
# Topology view modal, shared across a few pages.
#
function SpitTopologyViewModal($modal_name, $profile_array)
{
    echo "<!-- This is the topology view modal -->
          <div id='$modal_name' class='modal fade'>
          <div class='modal-dialog topo-modal'  id='showtopo_dialog'>
            <div class='modal-content'>
               <div class='modal-header'>
                <button type='button' class='close' data-dismiss='modal'
                   aria-hidden='true'>
                   &times;</button>
                <h3>Select a Profile</h3>
               </div>
               <div class='modal-body'>
                 <!-- This topo diagram goes inside this div -->
                 <div class='panel panel-default'
                            id='showtopo_container'>
                  <div class='form-group pull-left'>
                    <ul class='list-group' id='profile_name'
                            name='profile'
                            >\n";
    while (list ($id, $title) = each ($profile_array)) {
	$selected = "";
	if ($profile_value == $id)
	    $selected = "selected";
                      
	echo "          <li class='list-group-item profile-item' $selected
                            value='$id'>$title </li>\n";
    }
    echo "          </ul>
                  </div> 
                  <div class='pull-right'>
                    <span id='showtopo_title'></span>
                    <div class='panel-body'>
                     <div id='showtopo_div'></div>
                     <span class='pull-left' id='showtopo_description'></span>
                    </div>
                   </div>
                 </div>
                 <div id='showtopo_buttons' class='pull-right'>
                     <button id='showtopo_select'
                           class='btn btn-primary btn-sm'
                           type='submit' name='select'>
                              Select Profile</button>
                      <button type='button' class='btn btn-default btn-sm' 
                      data-dismiss='modal' aria-hidden='true'>
                     Cancel</button>
                    </div>
               </div>
            </div>
          </div>
       </div>\n";
}

#
# Generate an authentication object to pass to the browser that
# is passed to the web server on boss. This is used to grant
# permission to the user to invoke ssh to a local node using their
# emulab generated (no passphrase) key. This is basically a clone
# of what GateOne does, but that code was a mess. 
#
function SSHAuthObject($uid, $nodeid)
{
    global $USERNODE;
	
    $file = "/usr/testbed/etc/sshauth.key";
    
    #
    # We need the secret that is shared with ops.
    #
    $fp = fopen($file, "r");
    if (! $fp) {
	TBERROR("Error opening $file", 0);
	return null;
    }
    $key = fread($fp, 128);
    fclose($fp);
    if (!$key) {
	TBERROR("Could not get key from $file", 0);
	return null;
    }
    $key   = chop($key);
    $stuff = GENHASH();
    $now   = time();


    $authobj = array('uid'       => $uid,
		     'stuff'     => $stuff,
		     'nodeid'    => $nodeid,
		     'timestamp' => $now,
		     'baseurl'   => "https://${USERNODE}",
		     'signature_method' => 'HMAC-SHA1',
		     'api_version' => '1.0',
		     'signature' => hash_hmac('sha1',
					      $uid . $stuff . $nodeid . $now,
					      $key),
    );
    return json_encode($authobj);
}

#
# This is a little odd; since we are using our local CM to create
# the experiment, we can just ask for the graphic directly.
#
function GetTopoMap($uid, $pid, $eid)
{
    global $TBSUEXEC_PATH;
    $xmlstuff = "";
    
    if ($fp = popen("$TBSUEXEC_PATH nobody nobody webvistopology ".
		    "-x -s $uid $pid $eid", "r")) {

	while (!feof($fp) && connection_status() == 0) {
	    $string = fgets($fp);
	    if ($string) {
		$xmlstuff .= $string;
	    }
	}
	return $xmlstuff;
    }
    else {
	return "";
    }
}

#
# Redirect request to https
#
function RedirectSecure()
{
    global $APTHOST;

    if (!isset($_SERVER["SSL_PROTOCOL"])) {
	header("Location: https://$APTHOST". $_SERVER['REQUEST_URI']);
	exit();
    }
}

#
# Redirect to the login page()
#
function RedirectLoginPage()
{
    # HTTP_REFERER will not work reliably when redirecting so
    # pass in the URI for this page as an argument
    header("Location: login.php?referrer=".
	   urlencode($_SERVER['REQUEST_URI']));
}

?>