define( 'videopageadmin.views.editbase', [], function() {
	'use strict';

	var EditBase = Backbone.View.extend( {
		initialize: function() {
			this.switcher();
		},
		events: {
			'click .reset': 'reset'
		},
		switcher: function() {
			var opts = {};

			if ( $( '.form-wrapper' ).length ) {
				opts.boxes = '.form-wrapper';
			}

			opts.onChange = function( $elem, $switched ) {
				// Update the numbers beside the elements
				var $oCount = $elem.find( '.count' ),
					oCountVal = $oCount.html(),
					$nCount = $switched.find( '.count' ),
					nCountVal = $nCount.html();

				$oCount.html( nCountVal );
				$nCount.html( oCountVal );
			};
			this.$el.switcher( opts );
		},
		reset: function( e ) {
			e.preventDefault();

			$.confirm( {
				title: $.msg( 'videopagetool-confirm-clear-title' ),
				content: $.msg( 'videopagetool-confirm-clear-message' ),
				onOk: $.proxy( function() {
					// Clear all form input values.
					this.$el.find( 'input:text, input:hidden, textarea' )
						.val( '' );

					this.$el.trigger( 'form:reset' );
				}, this ),
				width: 700
			} );
		}
	} );

	return EditBase;
} );