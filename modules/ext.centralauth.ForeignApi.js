( function ( mw, $ ) {

	var hasOwnProperty = Object.prototype.hasOwnProperty;

	/**
	 * Extend mw.ForeignApi with CentralAuth authentication handling.
	 *
	 * Every request to the foreign wiki will be preceded by a 'action=centralauthtoken' request to
	 * the local wiki. The foreign request will be extended with the acquired token to ensure that the
	 * requests to foreign wiki will always be authenticated as the currently logged-in user on local
	 * wiki.
	 *
	 * If we detect that the current user is anonymous, that the foreign wiki doesn't have
	 * CentralAuth, or that the requests to foreign wiki will be correctly authenticated without
	 * sending 'centralauthtoken' every time, the additional requests will be skipped.
	 *
	 * @class mw.ForeignApi.plugin.CentralAuth
	 * @extends mw.ForeignApi
	 *
	 * @constructor
	 * @param {Object} [options]
	 *
	 * @author Bartosz Dziewoński
	 * @author Jon Robson
	 */
	function CentralAuthForeignApi( options ) {
		var foreignApi = this;
		this.localApi = new mw.Api();
		// If this is set, the additional requests for 'centralauthtoken' will always be skipped
		this.noTokenEver = false;

		// Call parent constructor
		CentralAuthForeignApi.parent.call( this, options );

		if ( !options || !options.url ) {
			// Actually a local API
			this.noTokenEver = true;
		} else if ( mw.config.get( 'wgUserName' ) === null ) {
			// Anonymous users cannot obtain a centralauthtoken
			this.noTokenEver = true;
		} else {
			// We're logged in locally, check to see if we're logged in on the foreign wiki too, and thus
			// can skip 'centralauthtoken' requests
			CentralAuthForeignApi.parent.prototype.ajax.call(
				this, 
				{ action: 'query', meta: 'userinfo' },
				{ type: 'GET' }
			)
				.done( function ( resp ) {
					var userinfo = resp.query.userinfo;
					if ( userinfo.anon === undefined && userinfo.name === mw.config.get( 'wgUserName' ) ) {
						// We are logged in on the foreign wiki
						foreignApi.noTokenEver = true;
					}
				} );
		}
	}

	OO.inheritClass( CentralAuthForeignApi, mw.ForeignApi );

	/**
	 * Get a 'centralauthtoken' from the local wiki for use on the foreign wiki.
	 *
	 * @private
	 * @return {jQuery.Promise}
	 */
	CentralAuthForeignApi.prototype.getCentralAuthToken = function () {
		var foreignApi = this;
		return this.localApi.get( { action: 'centralauthtoken' } ).then( function ( resp ) {
			if ( resp.error ) {
				// Check some known cases where we'll never get a token, to avoid wasting requests
				if ( resp.error.code === 'notloggedin' ) {
					// Anonymous users cannot obtain a centralauthtoken
					foreignApi.noTokenEver = true;
				}
				if ( resp.error.code === 'unknown_action' ) {
					// Remote wiki doesn't have CentralAuth installed
					foreignApi.noTokenEver = true;
				}
				return $.Deferred().reject( resp.error );
			} else {
				return resp.centralauthtoken.centralauthtoken;
			}
		} );
	};

	/**
	 * @inheritdoc
	 */
	CentralAuthForeignApi.prototype.ajax = function ( parameters, ajaxOptions ) {
		var tokenPromise, foreignApi = this;
		// If we know we can't get a 'centralauthtoken', or if one was provided, don't request it
		if ( this.noTokenEver || hasOwnProperty.call( parameters, 'centralauthtoken' ) ) {
			tokenPromise = $.Deferred().reject().promise();
		} else {
			tokenPromise = this.getCentralAuthToken();
		}

		return tokenPromise.then(
			function ( centralAuthToken ) {
				var url, newParameters, newAjaxOptions;

				// Add 'centralauthtoken' query parameter. Per API documentation, it must also be part of
				// the request URI, and not just request body (even in case of POST requests).
				newParameters = $.extend( { centralauthtoken: centralAuthToken }, parameters );
				url = ( ajaxOptions && ajaxOptions.url ) || foreignApi.defaults.ajax.url;
				url += ( url.indexOf( '?' ) !== -1 ? '&' : '?' ) +
					'centralauthtoken=' + encodeURIComponent( centralAuthToken );
				newAjaxOptions = $.extend( {}, ajaxOptions, { url: url } );

				return CentralAuthForeignApi.parent.prototype.ajax.call( foreignApi, newParameters, newAjaxOptions );
			},
			function () {
				// We couldn't get the token, but continue anyway. This is expected in some cases, like
				// anonymous users.
				return CentralAuthForeignApi.parent.prototype.ajax.call( foreignApi, parameters, ajaxOptions );
			}
		);
	};

	// Expose
	mw.ForeignApi = CentralAuthForeignApi;

}( mediaWiki, jQuery ) );
