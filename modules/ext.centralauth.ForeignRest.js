( function () {

	/**
	 * Extend mw.ForeignRestApi with CentralAuth authentication handling.
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
	 * @param {string|mw.Uri} url URL pointing to another wiki's `rest.php` endpoint.
	 * @param {mw.ForeignApi} foreignActionApi used for action=centralauthtoken requests
	 * @param {Object} [options] See mw.RestApi.
	 * @param {Object} [options.anonymous=false] See mw.ForeignRestApi.
	 */
	function CentralAuthForeignRest( url, foreignActionApi, options ) {
		// Call parent constructor
		CentralAuthForeignRest.parent.call( this, url, foreignActionApi, options );

		if ( mw.config.get( 'wgUserName' ) === null || ( options && options.anonymous ) ) {
			// Anonymous users cannot obtain a centralauthtoken
			this.noTokenNeeded = true;
		} else {
			// We're logged in locally, check to see if we're logged in on the foreign wiki too, and
			// thus can skip 'centralauthtoken' requests
			this.foreignLoginPromise = this.foreignActionApi.checkForeignLogin();
		}
	}

	OO.inheritClass( CentralAuthForeignRest, mw.ForeignRest );

	/**
	 * @inheritDoc
	 */
	CentralAuthForeignRest.prototype.abort = function () {
		this.foreignActionApi.abort();
		CentralAuthForeignRest.parent.prototype.abort.call( this );
	};

	/**
	 * @inheritdoc
	 */
	CentralAuthForeignRest.prototype.ajax = function ( path, ajaxOptions ) {
		var tokenPromise,
			self = this,
			parent = CentralAuthForeignRest.parent.prototype.ajax,
			abortedPromise = $.Deferred().reject(
				'http',
				{ textStatus: 'abort', exception: 'abort' }
			).promise(),
			abortable,
			aborted;

		// If we know we can't get a 'centralauthtoken', or if one was provided, don't request it
		if ( this.noTokenNeeded ) {
			tokenPromise = $.Deferred().reject();
		} else if ( this.foreignLoginPromise ) {
			tokenPromise = this.foreignLoginPromise.then(
				// If succeeded, no 'centralauthtoken' needed
				function () { return $.Deferred().reject(); },
				// If failed, get the token
				function () { return ( abortable = self.foreignActionApi.getCentralAuthToken() ); }
			);
		} else {
			tokenPromise = abortable = self.foreignActionApi.getCentralAuthToken();
		}

		return tokenPromise.then(
			function ( centralAuthToken ) {
				var newAjaxOptions;
				if ( aborted ) {
					return abortedPromise;
				}

				newAjaxOptions = $.extend( {}, ajaxOptions );
				newAjaxOptions.headers = $.extend( {}, newAjaxOptions.headers, {
					Authorization: 'CentralAuthToken ' + centralAuthToken
				} );
				return ( abortable = parent.call( self, path, newAjaxOptions ) );
			},
			function () {
				if ( aborted ) {
					return abortedPromise;
				}
				// We couldn't get the token, but continue anyway. This is expected in some cases,
				// like anonymous users.
				return ( abortable = parent.call( self, path, ajaxOptions ) );
			}
		).promise( { abort: function () {
			aborted = true;
			if ( abortable && abortable.abort ) {
				abortable.abort();
			}
		} } );
	};

	// Expose
	mw.ForeignRest = CentralAuthForeignRest;

}() );
