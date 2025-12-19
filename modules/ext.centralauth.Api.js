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
	return this.get( { action: 'centralauthtoken' }, ajaxOptions ).then( ( resp ) => {
		if ( resp.error ) {
			return $.Deferred().reject( resp.error );
		} else {
			return resp.centralauthtoken.centralauthtoken;
		}
	} );
};
