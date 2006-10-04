<?php

// using fake latin1 as it explodes in utf8 mode because
// MYSQL DOES NOT SUPPORT UNICODE YET ONLY A SUBSET

echo "set names latin1;

DROP TABLE IF EXISTS globaluser;
CREATE TABLE globaluser (
  gu_id int auto_increment,
  gu_name varchar(255) binary,
  gu_email varchar(255) binary,
  gu_email_authenticated char(14) binary,
  
  primary key (gu_id),
  unique key (gu_name)
) CHARSET=latin1;

DROP TABLE IF EXISTS localuser;
CREATE TABLE localuser (
  lu_dbname varchar(32) binary,
  lu_id int,
  lu_name varchar(255) binary,
  lu_email varchar(255) binary,
  lu_email_authenticated char(14) binary,
  
  lu_editcount int,
  lu_attached tinyint,
  
  primary key (lu_dbname,lu_id),
  unique key (lu_dbname,lu_name),
  key (lu_name,lu_dbname)
) CHARSET=latin1;
";

function isUtf8( $s ) {
	$ishigh = preg_match( '/[\x80-\xff]/', $s);
	$isutf = ($ishigh ? preg_match( '/^([\x00-\x7f]|[\xc0-\xdf][\x80-\xbf]|' .
	         '[\xe0-\xef][\x80-\xbf]{2}|[\xf0-\xf7][\x80-\xbf]{3})+$/', $s ) 
	         : true );
	return $isutf;
}

function importUserData( $dbname ) {
	// Some stuff was extracted from user tables for testing
	$filename = "ca-testing/{$dbname}-user.csv";
	$infile = fopen( $filename, "rt" );
	$headerLine = fgets( $infile );
	while( !feof( $infile ) ) {
		$line = trim( fgets( $infile ) );
		if( $line == '' ) break;
		$data = explode( "\t", $line );
		
		$id = mysql_real_escape_string( @$data[0] );
		$name = mysql_real_escape_string( @$data[1] );
		$email = mysql_real_escape_string( @$data[2] );
		$confirmed = mysql_real_escape_string( @$data[3] );
		if( $confirmed == 'NULL' ) {
			$xconfirmed = 'NULL';
		} else {
			$xconfirmed = "'$confirmed'";
		}
		
		$sql = "INSERT INTO localuser (lu_dbname,lu_id,lu_name,lu_email," .
			"lu_email_authenticated,lu_editcount,lu_attached) " .
			"VALUES ('$dbname','$id','$name','$email',$xconfirmed,0,0)";
		
		if( !isUtf8( $name ) ) {
			echo "--invalid $sql;\n";
		} else {
			echo "$sql;\n";
		}
	}
	fclose( $infile );
}

function importEditCounts( $dbname ) {
	// Some stuff was extracted from user tables for testing
	$filename = "ca-testing/{$dbname}-count.csv";
	$infile = fopen( $filename, "rt" );
	$headerLine = fgets( $infile );
	while( !feof( $infile ) ) {
		$line = trim( fgets( $infile ) );
		if( $line == '' ) break;
		$data = explode( "\t", $line );
		
		$id = intval( @$data[0] );
		$count = intval( @$data[1] );
		
		$sql = "UPDATE localuser SET lu_editcount=$count " .
			"WHERE lu_dbname='$dbname' AND lu_id=$id";
		echo "$sql;\n";
	}
	fclose( $infile );
}

$dblist = array_filter( array_map( 'trim', file( 'ca-testing/all.dblist' ) ) );


foreach( $dblist as $db ) {
	echo "-- importing $db data...\n";
	importUserData( $db );
	importEditCounts( $db );
}

?>