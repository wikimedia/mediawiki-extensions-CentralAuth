( function () {

	/**
	 * Extend mw.ForeignRest with CentralAuth authentication handling.
	 *
	 * Every request to the foreign wiki will be preceded by a 'action=centralauthtoken'
	 * request to the local wiki. The acquired token will be passed added to the foreign
	 * request 'Authorization' header.
	 *
	 * @see mw.ForeignApi.plugin.CentralAuth for more details.
	 *
	 * @class mw.ForeignRest.plugin.CentralAuth
	 * @extends mw.ForeignRest
	 *
	 * @constructor
	 * @param {string} url URL pointing to another wiki's `rest.php` endpoint.
	 * @param {Object} [options] See mw.RestApi.
	 * @param {Object} [options.anonymous=false] See mw.ForeignRest.
	 * @param {Object} [optionsCompat] No longer used, kept for compatibility.
	 */
	function CentralAuthForeignRest( url, options, optionsCompat ) {
		// Call parent constructor
		CentralAuthForeignRest.super.call( this, url, options, optionsCompat );

		// For backwards compatibility, we support passing options in the 3rd parameter
		if ( options instanceof mw.Api ) {
			options = optionsCompat;
		}

		// Properties
		// mw.Api instance used for action=centralauthtoken requests
		this.localApi = new mw.Api();
		if ( mw.config.get( 'wgUserName' ) === null || ( options && options.anonymous ) ) {
			// Anonymous users cannot obtain a centralauthtoken
			this.noTokenNeeded = true;
		}
	}

	OO.inheritClass( CentralAuthForeignRest, mw.ForeignRest );

	/**
	 * @inheritDoc
	 */
	CentralAuthForeignRest.prototype.abort = function () {
		this.localApi.abort();
		CentralAuthForeignRest.super.prototype.abort.call( this );
	};

	/**
	 * @inheritdoc
	 */
	CentralAuthForeignRest.prototype.ajax = function ( path, ajaxOptions ) {
		ajaxOptions = ajaxOptions || {};
		const abortable = this.localApi.makeAbortablePromise( ajaxOptions );

		const parent = CentralAuthForeignRest.super.prototype.ajax;

		// If we know we can't get a 'centralauthtoken', don't request it
		let tokenPromise;
		if ( this.noTokenNeeded ) {
			tokenPromise = $.Deferred().reject();
		} else {
			tokenPromise = this.localApi.getCentralAuthToken( ajaxOptions );
		}

		return tokenPromise.then(
			( centralAuthToken ) => {
				const newAjaxOptions = Object.assign( {}, ajaxOptions );
				newAjaxOptions.headers = Object.assign( {}, newAjaxOptions.headers, {
					Authorization: 'CentralAuthToken ' + centralAuthToken
				} );
				// Do not send browser credentials (cookies). The 'Authorization' header serves as
				// authentication, and sending cookies to the REST API causes MediaWiki errors by
				// default, unless $wgRestAllowCrossOriginCookieAuth is enabled. (T391180)
				newAjaxOptions.xhrFields = Object.assign( {}, newAjaxOptions.xhrFields, {
					withCredentials: false
				} );

				return parent.call( this, path, newAjaxOptions );
			},
			// We couldn't get the token, but continue anyway, using browser credentials.
			// This is expected in some cases, like anonymous users.
			() => parent.call( this, path, ajaxOptions )
		).promise( abortable );
	};

	// Expose
	mw.ForeignRest = CentralAuthForeignRest;

}() );
