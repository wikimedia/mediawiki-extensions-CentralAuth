( function () {

	/**
	 * Creates a mw.widgets.GlobalUserInputWidget object.
	 *
	 * @class
	 * @extends OO.ui.TextInputWidget
	 * @mixin OO.ui.mixin.LookupElement
	 *
	 * @constructor
	 * @param {Object} [config] Configuration options
	 * @param {number} [config.limit=10] Number of results to show
	 * @param {boolean} [config.excludenamed] Whether to exclude named users or not
	 * @param {boolean} [config.excludetemp] Whether to exclude temporary users or not
	 */
	function GlobalUserInputWidget( config ) {
		// Config initialization
		config = Object.assign( {}, config, { autocomplete: false } );

		// Parent constructor
		GlobalUserInputWidget.super.call( this, config );

		// Mixin constructors
		OO.ui.mixin.LookupElement.call( this, config );

		// Properties
		this.limit = config.limit || 10;
		this.excludeNamed = config.excludenamed || false;
		this.excludeTemp = config.excludetemp || false;

		// Initialization
		this.$element.addClass( 'mw-widget-userInputWidget' );
		this.lookupMenu.$element.addClass( 'mw-widget-userInputWidget-menu' );
	}

	/* Setup */

	OO.inheritClass( GlobalUserInputWidget, OO.ui.TextInputWidget );
	OO.mixinClass( GlobalUserInputWidget, OO.ui.mixin.LookupElement );

	/* Methods */

	/**
	 * @inheritdoc
	 */
	GlobalUserInputWidget.prototype.onLookupMenuChoose = function ( item ) {
		this.closeLookupMenu();
		this.setLookupsDisabled( true );
		this.setValue( item.getData() );
		this.setLookupsDisabled( false );
	};

	/**
	 * @inheritdoc
	 */
	GlobalUserInputWidget.prototype.focus = function () {
		// Prevent programmatic focus from opening the menu
		this.setLookupsDisabled( true );

		// Parent method
		const retval = GlobalUserInputWidget.super.prototype.focus.apply( this, arguments );

		this.setLookupsDisabled( false );

		return retval;
	};

	/**
	 * @inheritdoc
	 */
	GlobalUserInputWidget.prototype.getLookupRequest = function () {
		const inputValue = this.value;

		return new mw.Api().get( {
			action: 'query',
			list: 'globalallusers',
			// Prefix of list=globalallusers is case sensitive. Normalise first
			// character to uppercase so that "fo" may yield "Foo".
			aguprefix: inputValue[ 0 ].toUpperCase() + inputValue.slice( 1 ),
			agulimit: this.limit,
			aguexcludenamed: this.excludeNamed,
			aguexcludetemp: this.excludeTemp
		} );
	};

	/**
	 * Get lookup cache item from server response data.
	 *
	 * @method
	 * @param {Mixed} response Response from server
	 * @return {Object}
	 */
	GlobalUserInputWidget.prototype.getLookupCacheDataFromResponse = function ( response ) {
		return response.query.globalallusers || {};
	};

	/**
	 * Get list of menu items from a server response.
	 *
	 * @param {Object} data Query result
	 * @return {OO.ui.MenuOptionWidget[]} Menu items
	 */
	GlobalUserInputWidget.prototype.getLookupMenuOptionsFromData = function ( data ) {
		const items = [];

		for ( let i = 0, len = data.length; i < len; i++ ) {
			const user = data[ i ] || {};
			items.push( new OO.ui.MenuOptionWidget( {
				label: user.name,
				data: user.name
			} ) );
		}

		return items;
	};

	mw.widgets.GlobalUserInputWidget = GlobalUserInputWidget;
}() );
