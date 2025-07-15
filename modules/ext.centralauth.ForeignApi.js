( function () {

	const
		hasOwnProperty = Object.prototype.hasOwnProperty,
		// Names of old token types which can be mapped to 'csrf' token now
		csrfTokenOldTypes = [ 'csrf', 'edit', 'delete', 'protect', 'move', 'block', 'unblock',
			'email', 'import', 'options' ];

	/**
	 * Extend mw.ForeignApi with CentralAuth authentication handling.
	 *
	 * Every request to the foreign wiki will be preceded by a 'action=centralauthtoken' request to
	 * the local wiki. The foreign request will be extended with the acquired token to ensure that
	 * the requests to foreign wiki will always be authenticated as the currently logged-in user on
	 * local wiki.
	 *
	 * If we detect that the current user is anonymous, that the foreign wiki doesn't have
	 * CentralAuth, or that the requests to foreign wiki will be correctly authenticated without
	 * sending 'centralauthtoken' every time, the additional requests will be skipped.
	 *
	 * @class mw.ForeignApi.plugin.CentralAuth
	 * @extends mw.ForeignApi
	 *
	 * @constructor
	 * @param {string} url URL pointing to another wiki's `api.php` endpoint.
	 * @param {Object} [options] See mw.Api.
	 * @param {Object} [options.anonymous=false] See mw.ForeignApi.
	 *
	 * @author Bartosz Dziewoński
	 * @author Jon Robson
	 */
	function CentralAuthForeignApi( url, options ) {
		// Call parent constructor
		CentralAuthForeignApi.super.call( this, url, options );

		// Properties
		// mw.Api instance used for action=centralauthtoken requests
		this.localApi = new mw.Api();
		// If this is set, action=centralauthtoken requests will be suppressed
		this.noTokenNeeded = false;
		// Hold return value of checkForeignLogin() (if the user is logged in)
		this.foreignLoginPromise = null;
		// 'csrf' token status, as requested by checkForeignLogin()
		this.csrfToken = null;
		this.csrfTokenBad = false;

		if ( mw.config.get( 'wgUserName' ) === null || ( options && options.anonymous ) ) {
			// Anonymous users cannot obtain a centralauthtoken
			this.noTokenNeeded = true;
		} else {
			// We're logged in locally, check to see if we're logged in on the foreign wiki too, and
			// thus can skip 'centralauthtoken' requests
			this.foreignLoginPromise = this.checkForeignLogin();
		}
	}

	OO.inheritClass( CentralAuthForeignApi, mw.ForeignApi );

	/**
	 * Get a 'centralauthtoken' from the local wiki for use on the foreign wiki.
	 *
	 * @private
	 * @param {Object} [ajaxOptions]
	 * @return {jQuery.Promise}
	 */
	CentralAuthForeignApi.prototype.getCentralAuthToken = function ( ajaxOptions ) {
		return this.localApi.get( { action: 'centralauthtoken' }, ajaxOptions ).then( ( resp ) => {
			if ( resp.error ) {
				return $.Deferred().reject( resp.error );
			} else {
				return resp.centralauthtoken.centralauthtoken;
			}
		} );
	};

	/**
	 * Query the foreign wiki to see if we're already logged in there in the user's browser, which
	 * means that there's no need to query for and use 'centralauthtoken' parameter.
	 *
	 * To avoid wasted requests, get a CSRF token at the same time.
	 *
	 * @private
	 * @return {jQuery.Promise}
	 */
	CentralAuthForeignApi.prototype.checkForeignLogin = function () {
		const foreignApi = this;
		if ( this.foreignLoginPromise ) {
			return this.foreignLoginPromise;
		}
		return CentralAuthForeignApi.super.prototype.ajax.call(
			this,
			{ action: 'query', meta: 'userinfo|tokens' },
			{ type: 'GET' }
		)
			.then( ( resp ) => {
				const userinfo = resp.query.userinfo;
				if ( userinfo.anon === undefined && userinfo.name === mw.config.get( 'wgUserName' ) ) {
					// We are logged in on the foreign wiki
					foreignApi.noTokenNeeded = true;
					foreignApi.csrfToken = resp.query.tokens.csrftoken;
					return true;
				}
				return $.Deferred().reject();
			} );
	};

	/**
	 * @inheritdoc
	 */
	CentralAuthForeignApi.prototype.getToken = function ( type, assert, ajaxOptions ) {
		const foreignApi = this;
		const parent = CentralAuthForeignApi.super.prototype.getToken;
		if ( this.foreignLoginPromise && csrfTokenOldTypes.includes( type ) ) {
			ajaxOptions = ajaxOptions || {};
			const abortable = foreignApi.makeAbortablePromise( ajaxOptions );
			return this.foreignLoginPromise.then(
				() => {
					if ( foreignApi.csrfToken && !foreignApi.csrfTokenBad ) {
						return foreignApi.csrfToken;
					}
					return parent.call( foreignApi, type, assert, ajaxOptions );
				},
				() => parent.call( foreignApi, type, assert, ajaxOptions )
			).promise( abortable );
		}
		return parent.call( this, type, assert, ajaxOptions );
	};

	/**
	 * @inheritdoc
	 */
	CentralAuthForeignApi.prototype.badToken = function ( type ) {
		// This may be a bit too aggressive, but that's better than not being aggressive enough
		this.csrfTokenBad = true;
		return CentralAuthForeignApi.super.prototype.badToken.call( this, type );
	};

	/**
	 * @inheritdoc
	 */
	CentralAuthForeignApi.prototype.ajax = function ( parameters, ajaxOptions ) {
		ajaxOptions = ajaxOptions || {};
		const abortable = this.makeAbortablePromise( ajaxOptions );

		const foreignApi = this,
			parent = CentralAuthForeignApi.super.prototype.ajax,
			// some mw.Api calls modify ajaxOptions, make sure not to reuse it
			tokenAjaxOptions = Object.assign( {}, ajaxOptions );

		let tokenPromise;
		// If we know we can't get a 'centralauthtoken', or if one was provided, don't request it
		if ( this.noTokenNeeded || hasOwnProperty.call( parameters, 'centralauthtoken' ) ) {
			tokenPromise = $.Deferred().reject();
		} else if ( this.foreignLoginPromise ) {
			tokenPromise = this.foreignLoginPromise.then(
				// If succeeded, no 'centralauthtoken' needed
				() => $.Deferred().reject(),
				// If failed, get the token
				() => foreignApi.getCentralAuthToken( tokenAjaxOptions )
			);
		} else {
			tokenPromise = this.getCentralAuthToken( tokenAjaxOptions );
		}

		return tokenPromise.then(
			( centralAuthToken ) => {
				let url, newAjaxOptions;

				// Add 'centralauthtoken' query parameter
				const newParameters = Object.assign( { centralauthtoken: centralAuthToken }, parameters );
				// It must be part of the request URI, and not just POST request body
				if ( ajaxOptions.type !== 'GET' ) {
					url = ( ajaxOptions && ajaxOptions.url ) || foreignApi.defaults.ajax.url;
					url += ( url.includes( '?' ) ? '&' : '?' ) +
						'centralauthtoken=' + encodeURIComponent( centralAuthToken );
					newAjaxOptions = Object.assign( {}, ajaxOptions, { url: url } );
				} else {
					newAjaxOptions = ajaxOptions;
				}

				return parent.call( foreignApi, newParameters, newAjaxOptions );
			},
			// We couldn't get the token, but continue anyway. This is expected in some cases,
			// like anonymous users.
			() => parent.call( foreignApi, parameters, ajaxOptions )
		).promise( abortable );
	};

	// Expose
	mw.ForeignApi = CentralAuthForeignApi;

}() );
