/**
 * CentralAuth's extensions to mw.Api.
 */

/**
 * Get a 'centralauthtoken' from the local wiki for use on a foreign wiki.
 *
 * @param {Object} [ajaxOptions]
 * @return {jQuery.Promise}
 */
mw.Api.prototype.getCentralAuthToken = function ( ajaxOptions ) {
	// This token is single-use and each call has to be a distinct request.
	// Without cache busting, browsers (notably Safari) may coalesce concurrent identical
	// GET requests and hand the same token to multiple callers (phab:T399674).
	ajaxOptions = Object.assign( { cache: false }, ajaxOptions );
	return this.get( { action: 'centralauthtoken' }, ajaxOptions ).then( ( resp ) => {
		if ( resp.error ) {
			return $.Deferred().reject( resp.error );
		} else {
			return resp.centralauthtoken.centralauthtoken;
		}
	} );
};
