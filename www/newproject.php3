<?php
include("defs.php3");

#
# Standard Testbed Header
#
PAGEHEADER("Start a New Testbed Project");

#
# First off, sanity check the form to make sure all the required fields
# were provided. I do this on a per field basis so that we can be
# informative. Be sure to correlate these checks with any changes made to
# the project form. 
#
if (!isset($pid) ||
    strcmp($pid, "") == 0) {
  FORMERROR("Name");
}
if (!isset($proj_head_uid) ||
    strcmp($proj_head_uid, "") == 0) {
  FORMERROR("Username");
}
if (!isset($proj_name) ||
    strcmp($proj_name, "") == 0) {
  FORMERROR("Long Name");
}
if (!isset($proj_members) ||
    strcmp($proj_members, "") == 0) {
  FORMERROR("Estimated #of Project Members");
}
if (!isset($proj_pcs) ||
    strcmp($proj_pcs, "") == 0) {
  FORMERROR("Estimated #of PCs");
}
if (!isset($proj_sharks) ||
    strcmp($proj_sharks, "") == 0) {
  FORMERROR("Estimated #of Sharks");
}
if (!isset($proj_why) ||
    strcmp($proj_why, "") == 0) {
  FORMERROR("Please describe your project");
}
if (!isset($usr_name) ||
    strcmp($usr_name, "") == 0) {
  FORMERROR("Full Name");
}
if (!isset($proj_URL) ||
    strcmp($proj_URL, "") == 0 ||
    strcmp($proj_URL, $HTTPTAG) == 0) {
  FORMERROR("Project URL");
}
if (!isset($proj_funders) ||
    strcmp($proj_funders, "") == 0) {
  FORMERROR("Project Funders and Grant Numbers");
}
if (!isset($usr_email) ||
    strcmp($usr_email, "") == 0) {
  FORMERROR("Email Address");
}
if (!isset($usr_addr) ||
    strcmp($usr_addr, "") == 0) {
  FORMERROR("Postal Address");
}
if (!isset($usr_affil) ||
    strcmp($usr_affil, "") == 0) {
  FORMERROR("Institutional Afilliation");
}
if (!isset($usr_title) ||
    strcmp($usr_title, "") == 0) {
  FORMERROR("Title/Position");
}
if (!isset($usr_phones) ||
    strcmp($usr_phones, "") == 0) {
  FORMERROR("Phone #");
}
if (!isset($proj_public) ||
    (strcmp($proj_public, "yes") && strcmp($proj_public, "no"))) {
  FORMERROR("Publicly Visible");
}
if ((strcmp($proj_public, "no") == 0) &&
    (!isset($proj_whynotpublic) || strcmp($proj_whynotpublic, "") == 0)) {
  FORMERROR("Please tell us why we may not list your project publicly");
}

#
# Check uid and pid for sillyness.
#
if (! ereg("^[a-zA-Z][-_a-zA-Z0-9]+$", $pid)) {
    USERERROR("The project name ($pid) must be composed of alphanumeric ".
	      "characters only (includes _ and -), and must begin with an ".
	      "alpha character.", 1);
}
if (! ereg("^[a-z][a-z0-9]+$", $proj_head_uid)) {
    USERERROR("Your username ($proj_head_uid) must be composed of ".
	      "lowercase alphanumeric characters only, and must begin ".
	      "with a lowercase alpha character!", 1);
}

#
# Check database length limits.
#
if (strlen($pid) > $TBDB_PIDLEN) {
    USERERROR("The project name \"$pid\" is too long! ".
              "Please select one that is shorter than $TBDB_PIDLEN.", 1);
}
if (strlen($proj_head_uid) > $TBDB_UIDLEN) {
    USERERROR("The name \"$proj_head_uid\" is too long! ".
              "Please select one that is shorter than $TBDB_UIDLEN.", 1);
}

#
# Check that email address looks reasonable. We need the domain for
# below anyway.
#
$email_domain = strstr($usr_email, "@");
if (! $email_domain ||
    strcmp($usr_email, $email_domain) == 0 ||
    strlen($email_domain) <= 1 ||
    ! strstr($email_domain, ".")) {
    USERERROR("The email address `$usr_email' looks invalid!. Please ".
	      "go back and fix it up", 1);
}
$email_domain = substr($email_domain, 1);
$email_user   = substr($usr_email, 0, strpos($usr_email, "@", 0));

#
# Check URLs. 
#
if (strcmp($usr_url, $HTTPTAG) == 0) {
    $usr_url = "";
}
VERIFYURL($usr_url);
VERIFYURL($proj_URL);

#
# Certain of these values must be escaped or otherwise sanitized.
# 
$proj_why     = addslashes($proj_why);
$proj_name    = addslashes($proj_name);
$proj_funders = addslashes($proj_funders);
$proj_whynotpublic = addslashes($proj_whynotpublic);
$usr_affil    = addslashes($usr_affil);
$usr_title    = addslashes($usr_title);
$usr_addr     = addslashes($usr_addr);
$usr_phones   = addslashes($usr_phones);

#
# Convert project visibility to boolean value. Tested above for yes/no.
#
if (strcmp($proj_public, "yes") == 0) {
    $public = 1;
}
else {
    $public = 0;
}

#
# This is a new project request. Make sure it does not already exist.
#
$project_result =
    DBQueryFatal("SELECT pid FROM projects WHERE pid='$pid'");

if ($row = mysql_fetch_row($project_result)) {
    USERERROR("The project name \"$pid\" you have chosen is already in use. ".
              "Please select another.", 1);
}

#
# Check early that we can guarantee uniqueness of the unix group name.
# 
$query_result =
    DBQueryFatal("select gid from groups where unix_name='$pid'");

if (mysql_num_rows($query_result)) {
    TBERROR("Could not form a unique Unix group name for $pid!", 1);
}

#
# See if this is a new user or one returning.
#
$returning = TBCurrentUser($proj_head_uid);

#
# If a user returning, then the login must be valid to continue any further.
# For a new user, the password must pass our tests.
#
if ($returning) {
    if (CHECKLOGIN($proj_head_uid) != 1) {
        USERERROR("The Username '$proj_head_uid' is in use. ".
		  "If you already have an Emulab account, please go back ".
		  "and login before trying to create a new project.<br><br>".
		  "If you are a <em>new</em> Emulab user trying to start ".
                  "your first project, please go back and select a different ".
		  "Username.", 1);
    }
}
else {
    #
    # Check new username against CS logins so that external people do
    # not pick names that overlap with CS names.
    #
    if (! strstr($email_domain, "cs.utah.edu")) {
	$dbm = dbmopen($TBCSLOGINS, "r");
	if (! $dbm) {
	    TBERROR("Could not dbmopen $TBCSLOGINS from newproject.php3\n", 1);
	}
	if (dbmexists($dbm, $proj_head_uid)) {
	    dbmclose($dbm);
	    USERERROR("The username '$proj_head_uid' is already in use. ".
		      "Please go back and choose another.", 1);
	}
	dbmclose($dbm);
    }
    
    if (strcmp($password1, $password2)) {
        USERERROR("You typed different passwords in each of the two password ".
                  "entry fields. <br> Please go back and correct them.",
                  1);
    }
    $mypipe = popen(escapeshellcmd(
    "$TBCHKPASS_PATH $password1 $proj_head_uid '$usr_name:$usr_email'"),
    "w+");
    if ($mypipe) { 
        $retval=fgets($mypipe, 1024);
        if (strcmp($retval,"ok\n") != 0) {
            USERERROR("The password you have chosen will not work: ".
                      "<br><br>$retval<br>", 1);
        } 
    }
    else {
        TBERROR("TESTBED: checkpass failure\n".
                "\n$usr_name ($proj_head_uid) just tried to set up a testbed ".
                "account,\n".
                "but checkpass pipe did not open (returned '$mypipe').", 1);
    }
}

#
# For a new user:
# * Create a new account in the database.
# * Generate a mail message to the user with the verification key.
# 
if (! $returning) {
    $encoding = crypt("$password1");
    
    $newuser_command = "INSERT INTO users ".
        "(uid,usr_created,usr_expires,usr_name,usr_email,usr_addr,".
        " usr_URL,usr_title,usr_affil,usr_phone,usr_pswd,unix_uid,".
	" status,pswd_expires) ".
        "VALUES ('$proj_head_uid', now(), '$proj_expires', '$usr_name', ".
        "'$usr_email', '$usr_addr', '$usr_url', '$usr_title', '$usr_affil', ".
        "'$usr_phones', '$encoding', NULL, 'newuser', ".
	"date_add(now(), interval 1 year))";

    DBQueryFatal($newuser_command);

    $key = GENKEY($proj_head_uid);

    mail("$usr_name '$proj_head_uid' <$usr_email>",
	 "TESTBED: Your New User Key",
	 "\n".
         "Dear $usr_name:\n\n".
         "    Here is your key to verify your account on the ".
         "Utah Network Testbed:\n\n".
         "\t\t$key\n\n".
         "Please return to $TBWWW and log in using\n".
	 "the user name and password you gave us when you applied. You will\n".
	 "then find an option on the menu called 'New User Verification'.\n".
	 "Select that option, and on that page enter your key.\n".
	 "You will then be verified as a user. When you have been both\n".
         "verified and approved by Testbed Operations, you will\n".
	 "be marked as an active user, and will be granted full access to\n".
  	 "your user account.\n\n".
         "Thanks,\n".
         "Testbed Ops\n".
         "Utah Network Testbed\n",
         "From: $TBMAIL_APPROVAL\n".
         "Bcc: $TBMAIL_AUDIT\n".
         "Errors-To: $TBMAIL_WWW");
}

#
# Now for the new Project
# * Create a new project in the database.
# * Create a new default group for the project.
# * Create a new group_membership entry in the database, default trust=none.
# * Generate a mail message to testbed ops.
#
DBQueryFatal("INSERT INTO projects ".
	     "(pid, created, expires, name, URL, head_uid, ".
	     " num_members, num_pcs, num_sharks, why, funders, unix_gid, ".
	     " public, public_whynot)".
	     "VALUES ('$pid', now(), '$proj_expires','$proj_name', ".
	     "        '$proj_URL', '$proj_head_uid', '$proj_members', ".
	     "        '$proj_pcs', '$proj_sharks', '$proj_why', ".
	     "        '$proj_funders', NULL, $public, '$proj_whynotpublic')");

DBQueryFatal("INSERT INTO groups ".
	     "(pid, gid, leader, created, description, unix_gid, unix_name) ".
	     "VALUES ('$pid', '$pid', '$proj_head_uid', now(), ".
	     "        'Default Group', NULL, '$pid')");

DBQueryFatal("insert into group_membership ".
	     "(uid, gid, pid, trust, date_applied) ".
	     "values ('$proj_head_uid','$pid','$pid','none', now())");

#
# Grab the unix GID that was assigned.
#
TBGroupUnixInfo($pid, $pid, $unix_gid, $unix_name);

#
# The mail message to the approval list.
# 
mail($TBMAIL_APPROVAL,
     "TESTBED: New Project '$pid' ($proj_head_uid)",
     "'$usr_name' wants to start project '$pid'.\n".
     "Contact Info:\n".
     "Name:            $usr_name ($proj_head_uid)\n".
     "Email:           $usr_email\n".
     "User URL:        $usr_url\n".
     "Project:         $proj_name\n".
     "Expires:	       $proj_expires\n".
     "Project URL:     $proj_URL\n".
     "Public URL:      $proj_public\n".
     "Why Not Public:  $proj_whynotpublic\n".
     "Funders:         $proj_funders\n".
     "Title:           $usr_title\n".
     "Affiliation:     $usr_affil\n".
     "Address:         $usr_addr\n".
     "Phone:           $usr_phones\n".
     "Members:         $proj_members\n".
     "PCs:             $proj_pcs\n".
     "Sharks:          $proj_sharks\n".
     "Unix GID:        $unix_name ($unix_gid)\n".
     "Reasons:\n$proj_why\n\n".
     "Please review the application and when you have\n".
     "made a decision, go to $TBWWW and\n".
     "select the 'Project Approval' page.\n\nThey are expecting a result ".
     "within 72 hours.\n", 
     "From: $usr_name '$proj_head_uid' <$usr_email>\n".
     "Reply-To: $TBMAIL_APPROVAL\n".
     "Errors-To: $TBMAIL_WWW");

#
# Now give the user some warm fuzzies
#
echo "<center><h1>Project '$pid' successfully queued.</h1></center>
      Testbed Operations has been notified of your application.
      Most applications are reviewed within a day; some even within
      the hour, but sometimes as long as a week (rarely). We will notify
      you by e-mail at '$usr_name&nbsp;&lt;$usr_email>' of their decision
      regarding your proposed project '$pid'.\n";

if (! $returning) {
    echo "<p>In the meantime, for
          security purposes, you will receive by e-mail a key. When you
          receive it, come back to the site, and log in. When you do, you
          will see a new menu option called 'New User Verification'. On
          that page, enter in your key,
          exactly as you received it in your e-mail. You will then be
          marked as a verified user.
          <p>Once you have been both verified
          and approved, you will be classified as an active user, and will 
          be granted full access to your user account.";
}

#
# Standard Testbed Footer
# 
PAGEFOOTER();
?>
