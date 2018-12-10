/*!
 * MediaWiki Widgets - GlobalUserInputWidget class.
 *
 * @copyright 2011-2015 MediaWiki Widgets Team and others; see AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */
( function ( mw ) {

	/**
	 * Creates a mw.widgets.GlobalUserInputWidget object.
	 *
	 * @class
	 * @extends OO.ui.TextInputWidget
	 * @mixins OO.ui.mixin.LookupElement
	 *
	 * @constructor
	 * @param {Object} [config] Configuration options
	 * @cfg {number} [limit=10] Number of results to show
	 */
	mw.widgets.GlobalUserInputWidget = function MwWidgetsGlobalUserInputWidget( config ) {
		// Config initialization
		config = config || {};

		// Parent constructor
		mw.widgets.GlobalUserInputWidget.parent.call( this, $.extend( {}, config, { autocomplete: false } ) );

		// Mixin constructors
		OO.ui.mixin.LookupElement.call( this, config );

		// Properties
		this.limit = config.limit || 10;

		// Initialization
		this.$element.addClass( 'mw-widget-userInputWidget' );
		this.lookupMenu.$element.addClass( 'mw-widget-userInputWidget-menu' );
	};

	/* Setup */

	OO.inheritClass( mw.widgets.GlobalUserInputWidget, OO.ui.TextInputWidget );
	OO.mixinClass( mw.widgets.GlobalUserInputWidget, OO.ui.mixin.LookupElement );

	/* Methods */

	/**
	 * @inheritdoc
	 */
	mw.widgets.GlobalUserInputWidget.prototype.onLookupMenuItemChoose = function ( item ) {
		this.closeLookupMenu();
		this.setLookupsDisabled( true );
		this.setValue( item.getData() );
		this.setLookupsDisabled( false );
	};

	/**
	 * @inheritdoc
	 */
	mw.widgets.GlobalUserInputWidget.prototype.focus = function () {
		var retval;

		// Prevent programmatic focus from opening the menu
		this.setLookupsDisabled( true );

		// Parent method
		retval = mw.widgets.GlobalUserInputWidget.parent.prototype.focus.apply( this, arguments );

		this.setLookupsDisabled( false );

		return retval;
	};

	/**
	 * @inheritdoc
	 */
	mw.widgets.GlobalUserInputWidget.prototype.getLookupRequest = function () {
		var inputValue = this.value;

		return new mw.Api().get( {
			action: 'query',
			list: 'globalallusers',
			// Prefix of list=globalallusers is case sensitive. Normalise first
			// character to uppercase so that "fo" may yield "Foo".
			aguprefix: inputValue[ 0 ].toUpperCase() + inputValue.slice( 1 ),
			agulimit: this.limit
		} );
	};

	/**
	 * Get lookup cache item from server response data.
	 *
	 * @method
	 * @param {Mixed} response Response from server
	 * @return {Object}
	 */
	mw.widgets.GlobalUserInputWidget.prototype.getLookupCacheDataFromResponse = function ( response ) {
		return response.query.globalallusers || {};
	};

	/**
	 * Get list of menu items from a server response.
	 *
	 * @param {Object} data Query result
	 * @return {OO.ui.MenuOptionWidget[]} Menu items
	 */
	mw.widgets.GlobalUserInputWidget.prototype.getLookupMenuOptionsFromData = function ( data ) {
		var len, i, user,
			items = [];

		for ( i = 0, len = data.length; i < len; i++ ) {
			user = data[ i ] || {};
			items.push( new OO.ui.MenuOptionWidget( {
				label: user.name,
				data: user.name
			} ) );
		}

		return items;
	};

}( mediaWiki ) );
