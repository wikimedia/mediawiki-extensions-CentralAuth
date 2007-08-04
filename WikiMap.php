<?php

/**
 * Helper tools for dealing with other locally-hosted wikis
 */

class WikiMap {
	function byDatabase( $dbname ) {
		global $wgConf, $IP;
		
		// This is a damn dirty hack
		if( file_exists( "$IP/InitialiseSettings.php" ) ) {
			require_once "$IP/InitialiseSettings.php";
		}
		
		list( $major, $minor ) = $wgConf->siteFromDB( $dbname );
		if( isset( $major ) ) {
			$server = $wgConf->get( 'wgServer', $dbname, $major,
				array( 'lang' => $minor, 'site' => $major ) );
			$path = $wgConf->get( 'wgArticlePath', $dbname, $major,
				array( 'lang' => $minor, 'site' => $major ) );
			return new WikiReference( $major, $minor, $server, $path );
		} else {
			return null;
		}
		
	}
}

class WikiReference {
	private $mMinor; ///< 'en', 'meta', 'mediawiki', etc
	private $mMajor; ///< 'wiki', 'wiktionary', etc
	private $mServer; ///< server override, 'www.mediawiki.org'
	private $mPath;   ///< path override, '/wiki/$1'
	
	function __construct( $major, $minor, $server, $path ) {
		$this->mMajor = $major;
		$this->mMinor = $minor;
		$this->mServer = $server;
		$this->mPath = $path;
	}
	
	function getHostname() {
		if( substr( $this->mServer, 0, 7 ) === 'http://' ) {
			return substr( $this->mServer, 7 );
		} else {
			throw new MWException( "wtf" );
		}
		// Q: Could it happen that they're using https:// ?
	}
	
	/**
	 * pretty it up
	 */
	function getDisplayName() {
		$url = $this->getUrl( '' );
		$url = preg_replace( '!^https?://!', '', $url );
		$url = preg_replace( '!index\.php(\?title=|/)$!', '', $url );
		$url = preg_replace( '!wiki/$!', '', $url );
		$url = preg_replace( '!/$!', '', $url );
		return $url;
	}
	
	private function getLocalUrl( $page ) {
		// FIXME: this may be generalized...
		return str_replace( '$1', wfUrlEncode( str_replace( ' ', '_', $page ) ), $this->mPath );
	}
	
	function getUrl( $page ) {
		return
			'http://' .
			$this->getHostname() .
			$this->getLocalUrl( $page );
	}
}

