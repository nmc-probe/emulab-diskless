<?php

include_once("defs.php3");

function MayEditPub($user, $isadmin, $row) {
    global $TBDB_TRUST_LOCALROOT;
    if ($isadmin) 
        return true;
    if (!$user)
	return false;
    $uid_idx = $user->uid_idx();
    if ($uid_idx == $row['owner'] && $row['editable_owner'])
        return true;
    $pid = $row['project'];
    if ($pid == '') 
        return false;
    $proj = Project::LookupByPid($pid);
    return TBMinTrust($proj->UserTrust($user), $TBDB_TRUST_LOCALROOT);
}

function MakeBib($user, $isadmin, $r) {
    $res = '';
    if ($r['url'] != '')
        $res .= "<a href=\"" . $r['url'] . "\">";
    $res .= $r['title'];
    #if row[3][-1] != '.' and row[3][-1] != '?' and row[3][-1] != '!':
    $res .= ".";
    if ($r['url'] != '')
        $res .= "</a>";
    $res .= "\n";
    $res .= ' ' . $r['authors'];
    $res .= '.';
    $type = $r['type'];
    if ($type == "article") {
        $res .= ' <em>' . $r['conf'] . '</em>';
        if ($r['volume'] != '')
            $res .= ', Vol. ' . $r['volume'];
        if ($r['number'] != '') {
            if ($r['volume'] != '')
                $res .= ' No.';
            $res .= ' ' . $r['number'];
        }
        if ($r['pages'] != '')
            $res .= ', pages ' . $r['pages'];
        if ($r['where'] != '')
            $res .= ', ' . $r['where'];
    # if Tech report
    } else if ($type == 'techreport') {
        $res .= ' Technical Report '.  $r['number'];
        # Affiliation here, but can't for techreports because of our data
    # if Thesis
    } else if ($type == 'thesis' || $type == 'ms thesis' || 
               $type = 'phd thesis' || $type = 'bs thesis') {
        $res .= ' Thesis';
        if ($r['where'] != '') 
            $res .= ', ' . $r['where'];
    }
    # Not an Article/Tech report/Thesis? Add nothing special (for now)

    if ($r['month_name'] != '' || $r['year'] != '')
        $res .= ', ';
    if ($r['month_name'] != '')
        $res .= $r['month_name'] . ' ';
    $res .= $r['year'];
    if (MayEditPub($user, $isadmin, $r)) {
#      $res .= " <a href=\"submitpub.php?idx=". $r['idx'] . "\" style=\"background:#ffffaa \">Edit</a>";
       $res .= " <a href=\"submitpub.php?idx=". $r['idx'] . "\" style=\"background:yellow\">Edit</a>";
    }
    return $res;
}

function MakeBibList($user, $isadmin, $query_result) {
    $res = '';
    $number = 1;
    $category = "\n\t xyz";
    while ($r = mysql_fetch_array($query_result)) {
	if ($category != $r['category']) {
	    if ($category != "\n\t xyz") {
		$res .= "</ol>\n";
	    }
	    $category = $r['category'];
	    if ($category == '')
                $category = 'Uncategorized';
	    $res .= "<h2>" . $category . "</h2>\n";
	    $res .= "<ol>\n";
	    $category = $r['category'];
	}
	$res .= "<li style=\"margin-bottom: 10pt\" value=$number>";
	$res .= MakeBib($user, $isadmin, $r);
	$res .= "</li>\n";
	$number++;
    }
    $res .= "</ol>\n";
    return $res;
}

function GetPubs($where_clause, $deleted_clause = '!`deleted`') {
    return DBQueryFatal("select p.*,m.month_name,if(category = '',1,0) as category_sort  from emulab_pubs as p natural join emulab_pubs_month_map as m where $where_clause and $deleted_clause order by category_sort,category,year desc,month desc,title");
}

?>
