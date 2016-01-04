<?php
#
# Copyright (c) 2006-2015 University of Utah and the Flux Group.
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
#

class Aggregate
{
    var	$aggregate;
    
    #
    # Constructor by lookup by urn
    #
    function Aggregate($urn) {
	$safe_urn = addslashes($urn);

	$query_result =
	    DBQueryWarn("select * from apt_aggregates where urn='$safe_urn'");

	if (!$query_result || !mysql_num_rows($query_result)) {
	    $this->aggregate = null;
	    return;
	}
	$this->aggregate = mysql_fetch_array($query_result);
    }
    # accessors
    function field($name) {
	return (is_null($this->aggregate) ? -1 : $this->aggregate[$name]);
    }
    function name()	    { return $this->field('name'); }
    function urn()	    { return $this->field('urn'); }
    function nickname()	    { return $this->field('nickname'); }
    function abbreviation() { return $this->field('abbreviation'); }
    function weburl()	    { return $this->field('weburl'); }
    function has_datasets() { return $this->field('has_datasets'); }
    function isfederate()   { return $this->field('isfederate'); }
    function portals()      { return $this->field('portals'); }

    # Hmm, how does one cause an error in a php constructor?
    function IsValid() {
	return !is_null($this->aggregate);
    }

    # Lookup up by urn,
    function Lookup($urn) {
	$foo = new Aggregate($urn);

	if ($foo->IsValid()) {
	    return $foo;
	}	
	return null;
    }

    #
    # Generate the free nodes URL from the web url.
    #
    function FreeNodesURL() {
        return $this->weburl() . "/node_usage/freenodes.svg";
    }

    #
    # Return a list of aggregates supporting datasets.
    #
    function SupportsDatasetsList() {
	$result  = array();

	$query_result =
	    DBQueryFatal("select urn from apt_aggregates ".
			 "where has_datasets!=0");

	while ($row = mysql_fetch_array($query_result)) {
	    $urn = $row["urn"];

	    if (! ($aggregate = Aggregate::Lookup($urn))) {
		TBERROR("Aggregate::SupportsDatasetsList: ".
			"Could not load aggregate $urn!", 1);
	    }
	    $result[] = $aggregate;
	}
        return $result;
    }

    #
    # Return the list of allowed aggregates based on the portal in use.
    #
    function DefaultAggregateList() {
        global $PORTAL_GENESIS;
        $am_array = array();

        $query_result =
            DBQueryFatal("select urn,name,adminonly from apt_aggregates ".
                         "where FIND_IN_SET('$PORTAL_GENESIS', portals)");
        
	while ($row = mysql_fetch_array($query_result)) {
            $urn       = $row["urn"];
            $name      = $row["name"];
            $adminonly = $row["adminonly"];

            if ($adminonly && !ISADMIN()) {
                continue;
            }
            $am_array[$name] = $urn;
        }
        return $am_array;
    }
}

#
# We use this in a lot of places, so build it all the time.
#
$urn_mapping = array();

$query_result =
    DBQueryFatal("select urn,abbreviation from apt_aggregates");
while ($row = mysql_fetch_array($query_result)) {
    $urn_mapping[$row["urn"]] = $row["abbreviation"];
}

?>
