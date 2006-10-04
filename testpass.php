<?php

function openDb() {
	$db = mysql_connect('localhost', 'root', '', true);
	mysql_select_db('authtest', $db);
	mysql_query('SET NAMES latin1', $db); // fake utf8 due to poor mysql unicode support
	return $db;
}

function addQuotes($x) {
	if( is_null( $x ) ) {
		return 'NULL';
	} elseif( is_int( $x ) ) {
		return strval( $x );
	} else {
		return "'" . mysql_real_escape_string( $x ) . "'";
	}
}

function doQuery($sql, $db) {
	$res = mysql_query($sql, $db);
	if( $res === false ) {
		die(mysql_error($db) . "\n");
	}
	return $res;
}

function firstPass($dbr, $dbw) {
	echo "Creating a stub global account for each name...\n";
	$sql = "SELECT * FROM localuser FORCE KEY (lu_name) ORDER BY lu_name,lu_dbname";
	$res = mysql_unbuffered_query( $sql, $dbr );
	
	$lastname = false;
	while( $row = mysql_fetch_object( $res ) ) {
		if( $lastname === false ) {
			$queue = array( $row );
		} elseif( $row->lu_name == $lastname ) {
			$queue[] = $row;
		} else {
			firstPassUser( $dbw, $queue );
			$queue = array( $row );
		}
		$lastname = $row->lu_name;
	}
	if( $queue ) {
		firstPassUser( $dbw, $queue );
	}
	mysql_free_result( $res );
	echo "Done.\n";
}

function firstPassUser($dbw, $rows) {
	global $stats;
	
	$stats['all']++;
	
	$winner = false;
	$attach = array();
	$unattach = array();
	$total = count($rows);
	
	if( $total == 1 ) {
		$stats['singleton']++;
		$winner = $rows[0];
		$attach[] = $winner;
	} else {
		// The winner is the one with the most edits, usually
		$max = -1;
		foreach( $rows as $row ) {
			if( $row->lu_editcount > $max ) {
				$winner = $row;
				$max = $row->lu_editcount;
			}
		}
		
		// Do they all match?
		$allMatch = true;
		$allMatchOrEmpty = true;
		$allMatchOrUnused = true;
		$isConflict = false;
		$winningMail = ($winner->lu_email == '' ? false : $winner->lu_email);
		foreach( $rows as $row ) {
			if( $row->lu_dbname == $winner->lu_dbname ) {
				$attach[] = $row;
			} else {
				if( $row->lu_email !== $winningMail ) {
					$allMatch = false;
					if( $row->lu_email !== '' ) {
						$allMatchOrEmpty = false;
					}
					if( $row->lu_editcount == 0 ) {
						// Unused accounts are fair game for reclaiming
						$attach[] = $row;
					} else {
						$allMatchOrUnused = false;
						$unattach[] = $row;
						$isConflict = true;
					}
				} else {
					$attach[] = $row;
				}
			}
		}
		
		if( $allMatch ) $stats['identical mail']++;
		if( $allMatchOrEmpty) $stats['identical or empty mail']++;
		if( $allMatchOrUnused) $stats['identical or unused']++;
		if( $isConflict ) $stats['potential conflict']++;
	}
	
	if( false === $winner ) {
		var_dump( $rows );
		die ('wtf');
	}
	
	if( $winner->lu_editcount == 0 ) {
		$stats['unused']++;
	}
	
	$xname = addQuotes( $winner->lu_name );
	$xemail = addQuotes( $winner->lu_email );
	$xauthenticated = addQuotes( $winner->lu_email_authenticated );
	$sql = "INSERT IGNORE INTO globaluser (gu_name, gu_email, gu_email_authenticated) " .
		"VALUES ($xname, $xemail, $xauthenticated)";
	doQuery( $sql, $dbw );
	
	foreach( $attach as $row ) {
		$xdbname = addQuotes( $row->lu_dbname );
		$sql = "UPDATE localuser SET lu_attached=1 " .
			"WHERE lu_dbname=$xdbname AND lu_name=$xname";
		doQuery( $sql, $dbw );
	}
	
	// fixme: update the attached markers
	
	$matches = prettyList( $attach );
	$failures = prettyList( $unattach );
	echo "$xname {$total}x winner: [{$winner->lu_dbname}] attached: ($matches) loose: ($failures) $xemail\n";
}

function prettyList( $list ) {
	return implode( ', ', array_map( 'prettyListItem', $list ) );
}

function prettyListItem( $item ) {
	return $item->lu_dbname . '.' . $item->lu_editcount;
}

$dbr = openDb();
$dbw = openDb();

$stats = array(
	'all' => 0,
	'singleton' => 0,
	'identical mail' => 0,
	'identical or empty mail' => 0,
	'identical or unused' => 0,
	'potential conflict' => 0,
	'unused' => 0,
);
	
firstPass($dbr, $dbw);

var_dump( $stats );

?>