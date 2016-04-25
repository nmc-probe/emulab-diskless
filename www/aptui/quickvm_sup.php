<?php
#
# Copyright (c) 2000-2016 University of Utah and the Flux Group.
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
include_once("portal_defs.php");
include_once("instance_defs.php");

#
# Global flag to disable accounts. We do this on some pages which
# should not display login/account info.
#
$disable_accounts = 0;

#
# Global flag for page embedded. We look directly into page arguments
# for this, rather then using standard argument processing in each page.
# Page embedding is used to contain an apt pages withing Emulab. 
#
$embedded = 0;
if (isset($_REQUEST["embedded"]) && $_REQUEST["embedded"]) {
    $embedded = 1;
}

# Flag to signal that a requires was spit. For errors.
$spatrequired = 0;

# For backend scripts to know how they were invoked.
if (isset($_SERVER['SERVER_NAME'])) { 
    putenv("SERVER_NAME=" . $_SERVER['SERVER_NAME']);
}

#
# Redefine this so APT errors are styled properly. Called by PAGEERROR();.
#
$PAGEERROR_HANDLER = function($msg, $status_code = 0) {
    global $drewheader, $ISCLOUD, $ISPNET, $ISEMULAB, $ISAPT, $PORTAL_HELPFORUM;
    global $spatrequired;

    if (! $drewheader) {
	SPITHEADER();
    }
    echo $msg;
    echo "<script type='text/javascript'>\n";
    echo "    window.ISEMULAB  = " . ($ISEMULAB ? "1" : "0") . ";\n";
    echo "    window.ISCLOUD   = " . ($ISCLOUD  ? "1" : "0") . ";\n";
    echo "    window.ISPNET    = " . ($ISPNET   ? "1" : "0") . ";\n";
    echo "    window.ISAPT     = " . ($ISAPT    ? "1" : "0") . ";\n";
    echo "    window.HELPFORUM = " .
        "'https://groups.google.com/d/forum/${PORTAL_HELPFORUM}';\n";
    echo "</script>\n";
    if (!$spatrequired) {
	echo "<script src='js/lib/jquery-2.0.3.min.js'></script>\n";
	echo "<script src='js/lib/bootstrap.js'></script>\n";
	echo "<script src='js/lib/require.js' data-main='js/null.js'>
                 </script>\n";
    }
    SPITFOOTER();
    die("");
};

$PAGEHEADER_FUNCTION = function($thinheader = 0, $ignore1 = NULL,
				 $ignore2 = NULL, $ignore3 = NULL)
{
    global $PORTAL_MANUAL, $PORTAL_MOTD_SITEVAR, $PORTAL_HELPFORUM;
    global $TBMAINSITE, $APTTITLE, $FAVICON, $APTLOGO, $APTSTYLE, $ISAPT;
    global $GOOGLEUA, $ISCLOUD, $ISPNET, $ISEMULAB, $TBBASE, $ISEMULAB;
    global $login_user, $login_status;
    global $disable_accounts, $page_title, $drewheader, $embedded;
    $showmenus = 0;
    $title = $APTTITLE;
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
    if ($login_user && !($login_status & CHECKLOGIN_WEBONLY)) {
        $showmenus = 1;
    }
    echo "<html>
      <head>
        <title>$title</title>
        <link rel='shortcut icon' href='$FAVICON'
              type='image/vnd.microsoft.icon'>
        <link rel='stylesheet' href='css/bootstrap.css'>
        <link rel='stylesheet' href='css/quickvm.css'>
        <link rel='stylesheet' href='css/$APTSTYLE'>";
    echo "<script src='js/lib/jquery.min.js'></script>\n";
    echo "<script>APT_CACHE_TOKEN='" . Instance::CacheToken() . "';</script>";
    echo "<script src='js/common.js?nocache=asdfasdf'></script>
        <link rel='stylesheet' href='css/jquery-steps.css'>
        <script src='$TBBASE/emulab_sup.js'></script>
      </head>
    <body style='display: none;'>\n";

    echo "<script type='text/javascript'>\n";
    echo "    window.ISEMULAB = " . ($ISEMULAB ? "1" : "0") . ";\n";
    echo "    window.ISCLOUD  = " . ($ISCLOUD  ? "1" : "0") . ";\n";
    echo "    window.ISPNET   = " . ($ISPNET   ? "1" : "0") . ";\n";
    echo "    window.ISAPT    = " . ($ISAPT    ? "1" : "0") . ";\n";
    echo "    window.MANUAL   = '$PORTAL_MANUAL';\n";
    echo "    window.HELPFORUM = " .
        "'https://groups.google.com/d/forum/${PORTAL_HELPFORUM}';\n";
    echo "    window.EMBEDDED = $embedded;\n";
    echo "</script>\n";
    
    if ($TBMAINSITE && !$embedded && file_exists("../google-analytics.php")) {
	readfile("../google-analytics.php");
	echo "<script type='text/javascript'>
                ga('create', '$GOOGLEUA', 'auto');
                ga('send', 'pageview');
              </script>";
    }

    echo "
    <!-- Container for body, needed for sticky footer -->
    <div id='wrap'>\n";

    if ($embedded) {
	goto embed;
    }

    #
    # This is the stuff to the right of the logo.
    # 
    $navbar_status = "";
    $navbar_right  = "";

    if (!$disable_accounts) {
        if ($login_user && ISADMINISTRATOR()) {
	    # Extra top margin to align with the rest of the buttons.
            $navbar_status .= 
                "<li class='apt-left' style='margin-top:7px'>\n";
            
	    if (ISADMIN()) {
		$url = CreateURL("toggle", $login_user,
				 "type", "adminon", "value", 0);

                $navbar_status .=
                    "<a href='/$url'>
                          <img src='images/redball.gif'
                               style='height: 10px;'
                               border='0' alt='Admin On'></a>\n";
	    }
	    else {
		$url = CreateURL("toggle", $login_user,
				 "type", "adminon", "value", 1);

                $navbar_status .=
                    "<a href='/$url'>
                          <img src='images/greenball.gif'
                               style='height: 10px;'
                               border='0' alt='Admin Off'></a>\n";
	    }
            $navbar_status .= "</li>\n";
	}
        # Extra top margin to align with the rest of the buttons.
	$navbar_status .=
            "<li id='loginstatus' class='apt-left' style='margin-top:7px'>".
	       ($login_user ? "<p class='navbar-text'>".
                 "$login_uid logged in</p>" : "") . "</li>\n";

	if (!NOLOGINS()) {
	    if (!$login_user) {
                $navbar_right .=
                    "<li id='signupitem' class='apt-left'>" .
                    "  <a class='btn btn-primary navbar-btn apt-navbar-btn'
                                id='signupbutton'
                                href='signup.php'>Sign Up</a></li>\n";
		if ($page_title != "Login") {
                    $navbar_right .=
                        "<li id='loginitem' class='apt-left'>" .
                        "  <a class='btn btn-primary navbar-btn apt-navbar-btn'
                                    id='loginbutton'>Login</a></li>\n";
		}
	    }
	    else {
                $navbar_right .=
                    "<li class='apt-left hidden-xs'>" .
                    "  <a class='btn btn-primary navbar-btn apt-navbar-btn'
                                href='logout.php'>Logout</a></li>\n";
	    }
	}
    }
    # This is for dealing with the narowest window class; we hide some of
    # the buttons when a logged in user shrinks the window the window down,
    # and turn them on inside the action menu.
    $hiddenxs = ($showmenus ? "hidden-xs" : "");
    
    echo "
         <div class='navbar navbar-static-top' style='margin-bottom: 10px'
              role='navigation'>
            <div class='navbar-inner'>
             <div class='brand'>
                 <img src='images/$APTLOGO'/>
             </div>
             <ul class='nav navbar-nav navbar-right apt-right'>
              $navbar_status
              $navbar_right
             </ul>
             <ul class='nav navbar-nav navbar-left apt-left'>
                <li class='apt-left $hiddenxs'>
                    <a class='btn btn-quickvm-home navbar-btn'
                       href='landing.php'>Home</a></li>\n";
    echo "      <li class='apt-left $hiddenxs'>".
        "           <a class='btn btn-quickvm-home navbar-btn' ".
        "              href='$PORTAL_MANUAL' target='_blank'> ".
        ($ISEMULAB || $ISPNET ? "Wiki" : "Manual") . "</a></li>\n";

    if ($login_user && !($login_status & CHECKLOGIN_WEBONLY)) {
	echo "  <li id='quickvm_actions_menu' class='dropdown apt-left'> ".
	         "<a href='#'
                    class='dropdown-toggle btn btn-quickvm-home navbar-btn'
                       data-toggle='dropdown'>
                    Actions <b class='caret'></b></a>
                  <ul class='dropdown-menu'>
                   <li class='visible-xs navbar-nav-shortcuts'>
                       <a href='landing.php'>Home</a></li>
                   <li class='visible-xs navbar-nav-shortcuts'>
                       <a href='$PORTAL_MANUAL' target='_blank'> ".
                      ($ISEMULAB || $ISPNET ? "Wiki" : "Manual") . "</a></li>
                   <li class='visible-xs navbar-nav-shortcuts'>
                       <a href='logout.php'>Logout</a></li>
                   <li><a href='myprofiles.php'>My Profiles</a></li>
                   <li><a href='myexperiments.php'>My Experiments</a></li>
                   <li><a href='manage_profile.php'>Create Profile</a></li>
                   <li><a href='instantiate.php'>Start Experiment</a></li>
                   <li class='divider'></li>
                   <li><a href='getcreds.php'>Download Credentials</a></li>
                   <li><a href='ssh-keys.php'>Manage SSH Keys</a></li>
                   <li><a href='myaccount.php'>Manage Account</a></li>
                   <li><a href='signup.php'>Start/Join Project</a></li>
                   <li class='divider'></li>
	           <li><a href='list-datasets.php?all=1'>List Datasets</a></li>
	           <li><a href='create-dataset.php'>Create Dataset</a></li>";
       echo "      <li class='divider'></li>\n";
       $then = time() - (90 * 3600 * 24);
       echo "      <li><a href='activity.php?user=$login_uid&min=$then'>
                            My History</a></li>\n";
       echo "    </ul>
                </li>\n";
       if (ISADMIN() || ISFOREIGN_ADMIN()) {
           echo "<li id='quickvm_actions_menu' class='dropdown apt-left'>
	            <a href='#'
                        class='dropdown-toggle btn btn-quickvm-home navbar-btn'
                        data-toggle='dropdown'>
                    Admin <b class='caret'></b></a>
                  <ul class='dropdown-menu'>\n";
           echo "  <li><a href='dashboard.php'>DashBoard</a></li>";
           echo "  <li><a href='cluster-status.php'>Cluster Status</a></li>";
           $then = time() - (30 * 3600 * 24);
           echo "  <li><a href='activity.php?min=$then'>
                            History Data</a></li>
	           <li><a href='sumstats.php?min=$then'>Summary Stats</a></li>
	           <li><a href='ranking.php'>User/Proj Ranking</a></li>";
           echo "<li><a href='myexperiments.php?extend=1'>
                            Extension Requests</a></li>";
           echo "<li><a href='myexperiments.php?all=1'>
                            All Experiments</a></li>
	             <li><a href='myprofiles.php?all=1'>
                            All Profiles</a></li>";
           echo " </ul>
                </li>\n";
       }
    }
    echo "   </ul>
          </div>
         </div>\n";

    if (NOLOGINS()) {
        $message = TBGetSiteVar("web/message");
    }
    else {
        #
        # Put the special message, if any, right below the header. Note that
        # the  negative margin is to put it flush below the navbar without
        # having to permanently remove the bottom margin on the navbar
        #
        $message = TBGetSiteVar($PORTAL_MOTD_SITEVAR);
    }
    if ($message && $message != "") {
        echo "<div class='alert alert-warning alert-dismissible'
                 role='alert' style='margin-top: -10px; padding: 5px;'>
                <center>$message</center>
          </div>";
    }

    if ($login_user) {
        list($pcount, $phours) = Instance::CurrentUsage($login_user);
        list($foo, $weeksusage) = Instance::WeeksUsage($login_user);
        list($foo, $monthsusage) = Instance::MonthsUsage($login_user);
        list($rank, $ranktotal) = Instance::Ranking($login_user, 30);
        if ($phours || $weeksusage || $monthsusage) {
            echo "<center style='margin-bottom: 5px; margin-top: -8px'>";
            if ($phours) 
                $phours = sprintf("%.2f", $phours);
            echo "<span class='text-info'>
                       Current Usage: $phours Node Hours</span>";
            if ($weeksusage) {
                $weeksusage = sprintf("%.0f", $weeksusage);
                echo ", ";
                echo "<span class='text-warning'>
                       Prev Week: $weeksusage</span>";
            }
            if ($monthsusage) {
                $monthsusage = sprintf("%.0f", $monthsusage);
                echo ", ";
                echo "<span class='text-danger'>
                       Prev Month: $monthsusage</span>";
                if ($rank) {
                    echo "<span class='text-info'>
                          (30 day rank: $rank of $ranktotal users)</span>";
                }
            }
            echo "<a href='#' class='btn btn-xs' data-toggle='modal' ".
                "data-target='#myusage_modal'> ".
                "<span class='glyphicon glyphicon-question-sign' ".
                "      style='margin-bottom: 4px;'></span> ".
                "</a>";
            echo "</center>\n";
        }
        readfile("template/myusage.html");
    }

    if (!NOLOGINS() && !$login_user && $page_title != "Login") {
	SpitLoginModal("quickvm_login_modal");
	SpitWaitModal("waitwait-modal");
    }
embed:
    echo " <!-- Page content -->
           <div class='container-fluid'>\n";
};

function SPITHEADER($thinheader = 0,
		    $ignore1 = NULL, $ignore2 = NULL, $ignore3 = NULL)
{
    global $PAGEHEADER_FUNCTION;

    $PAGEHEADER_FUNCTION($thinheader, $ignore1, $ignore2, $ignore3);
}

$PAGEFOOTER_FUNCTION = function($ignored = NULL) {
    global $PORTAL_HELPFORUM, $PORTAL_NSFNUMBER, $embedded;

    echo "</div>
      </div>\n";
    if ($embedded) {
	return;
    }
    if ($PORTAL_NSFNUMBER) {
        SpitNSFModal();
    }
    echo "
      <!--- Footer -->
      <div>
       <div id='footer'>
        <div class='pull-left'>
          <a href='http://www.emulab.net' target='_blank'>
             Powered by
             <img src='images/emulab-whiteout.png' id='elabpower'></a>
        </div>
	<span>Question or comment? Join the
           <a href='https://groups.google.com/forum/#!forum/${PORTAL_HELPFORUM}'
              target='_blank'>Help Forum</a></span>
           <div class='pull-right'>\n";
    if ($PORTAL_NSFNUMBER) {
        echo " <a data-toggle='modal' style='margin-right: 10px;'
              href='#nsf_supported_modal'
	      data-target='#nsf_supported_modal'>Supported by NSF</a>\n";
    }
    echo "&copy; 2015
          <a href='http://www.utah.edu' target='_blank'>
             The University of Utah</a>
        </div>
       </div>
      </div>
      <!-- Placed at the end of the document so the pages load faster -->
     </body></html>\n";
};

function SPITFOOTER($ignored = null)
{
    global $PAGEFOOTER_FUNCTION;

    $PAGEFOOTER_FUNCTION($ignored);
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
	'code'  => $code,
	'value' => $msg
	);
    echo json_encode($results);
}

function SPITREQUIRE($main, $extras = "")
{
    global $spatrequired;
    
    echo $extras;
    echo "<script src='js/lib/bootstrap.js'></script>\n";
    echo "<script src='js/lib/require.js' data-main='js/$main'></script>\n";
    $spatrequired = 1;
}

function SPITNULLREQUIRE()
{
    SPITREQUIRE("main");
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
                            id='verify_modal_submit'
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
    global $PORTAL_PASSWORD_HELP;
    global $APTTITLE, $ISCLOUD;
    $referrer = CleanString($_SERVER['REQUEST_URI']);
?>
    <!-- This is the login modal -->
    <div id='<?php echo $id ?>' class='modal fade' role='dialog'>
        <div class='modal-dialog'>
        <div id='quickvm_login_form_error'
             class='align-center'></div>
        <div class='modal-content'>
           <div class='modal-header'>
            <button type='button' class='close' data-dismiss='modal'
               aria-hidden='true'>&times;</button>
               <h4 class='modal-title'>Log in to <?php echo $APTTITLE ?></h4>
           </div>
           <form id='quickvm_login_form'
                 role='form'
                 method='post' action='login.php'>
           <input type=hidden name=referrer value='<?php echo $referrer ?>'>
           <div class='modal-body form-horizontal'>
             <div class='form-group'>
                <label for='uid' class='col-sm-2 control-label'>Username</label>
                <div class='col-sm-10'>
                    <input name='uid' class='form-control'
                           placeholder='<?php echo $PORTAL_PASSWORD_HELP ?>'
                           autofocus type='text'>
                </div>
             </div>
             <div class='form-group'>
                <label for='password' class='col-sm-2 control-label'>Password
					  </label>
                <div class='col-sm-10'>
                   <input name='password' class='form-control'
                          placeholder='Password'
                          type='password'>
                </div>
             </div>
             <div class='form-group'>
               <div class='col-sm-offset-2 col-sm-10'>
<?php
    if ($ISCLOUD) {
	?>
                 <button class='btn btn-info btn-sm pull-left' disabled
		    type='button'
                    data-toggle="tooltip" data-placement="left"
		    title="You can use your geni credentials to login"
                    id='quickvm_geni_login_button'>Geni User?</button>
        <?php
    }
?>
                 <button class='btn btn-primary btn-sm pull-right'
                         id='quickvm_login_modal_button'
                         type='submit' name='login'>Login</button>
               </div>
             </div>
           </div>
           </form>
        </div>
        </div>
     </div>
<?php
}

#
# Please Wait.
#
function SpitWaitModal($id)
{
    echo "<!-- This is the Please Wait modal -->
          <div id='$id' class='modal fade'>
            <div class='modal-dialog'>
            <div class='modal-content'>
               <div class='modal-header'>
                <center><h3>Please Wait</h3></center>
               </div>
               <div class='modal-body'>
                 <center><img src='images/spinner.gif' /></center>
               </div>
            </div>
            </div>
         </div>\n";
    ?>
	<script>
	function ShowWaitModal(name) { $('#' + name).modal('show'); }
	function HideWaitModal(name) { $('#' + name).modal('hide'); }
	</script>
    <?php
}

#
# Oops modal.
#
function SpitOopsModal($id)
{
    echo "<!-- This is the Oops modal -->
          <div id='${id}_modal' class='modal fade'>
            <div class='modal-dialog'>
            <div class='modal-content'>
               <div class='modal-header'>
                 <button type='button'
                      class='btn btn-default btn-sm pull-right' 
                      data-dismiss='modal' aria-hidden='true'>
                   Close</button>
                 <center><h3>Oops!</h3></center>
               </div>
               <div class='modal-body'>
                 <div id='${id}_text'></div>
               </div>
            </div>
            </div>
         </div>\n";
}

function SpitNSFModal()
{
    global $PORTAL_NSFNUMBER;
    
    echo "<!-- This is the NSF Supported modal -->
          <div id='nsf_supported_modal' class='modal fade'>
            <div class='modal-dialog'>
             <div class='modal-content'>
              <div class='modal-body'>
                This material is based upon work supported by the
                National Science Foundation under Grant
                No. ${PORTAL_NSFNUMBER}. Any opinions, findings, and
                conclusions or recommendations expressed in this
                material are those of the author(s) and do not
                necessarily reflect the views of the National Science
                Foundation.
                <br><br>
                <center>
                <button type='button'
                     class='btn btn-default btn-sm' 
                     data-dismiss='modal' aria-hidden='true'>
                  Close</button>
                </center>
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
    exit(0);
}

#
# Check the login and redirect to login page. We use NONLOCAL modifier
# since the classic emulab interface refuses service to nonlocal users.
#
function CheckLoginOrRedirect($modifier = 0)
{
    RedirectSecure();
    
    $check_status = 0;
    $this_user    = CheckLogin($check_status);
    if (! ($check_status & CHECKLOGIN_LOGGEDIN)) {
	RedirectLoginPage();
    }
    CheckLoginConditions($check_status & ~($modifier|CHECKLOGIN_NONLOCAL));
    return $this_user;
}

?>
