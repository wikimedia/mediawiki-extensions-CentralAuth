'use strict';
const Page = require( '../../../../../tests/selenium/pageobjects/page' );

class CentralAuthPage extends Page {

	get manageUserAccount() { return browser.element( '#centralauth-submit-find' ); }

	open() {
		super.open( 'Special:CentralAuth' );
	}

}
module.exports = new CentralAuthPage();
