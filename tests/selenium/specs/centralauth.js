'use strict';

var assert = require( 'assert' ),
	CentralAuthPage = require( '../pageobjects/centralauth.page' );

describe( 'Special:CentralAuth', function () {

	it( 'opens', function () {
		CentralAuthPage.open();
		assert( CentralAuthPage.manageUserAccount.isExisting() );
	} );

} );
