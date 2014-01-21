define( 'videopageadmin.views.featured', [
	'videopageadmin.views.editbase',
	'videopageadmin.views.thumbnailupload',
	'videopageadmin.models.validator'
], function( EditBaseView, ThumbnailUploader, Validator ) {
	'use strict';

	var FeaturedVideo = EditBaseView.extend( {
		initialize: function() {
			EditBaseView.prototype.initialize.call( this, arguments );
			this.$fieldsToValidate = this.$el.find( '.description, .display-title, .video-key, .alt-thumb' );

			_.bindAll( this, 'initAddVideo', 'initValidator', 'clearForm' );

			this.initAddVideo();
			this.initValidator();

			// TODO: check if this is the right way to bind this event
			this.$el.on( 'form:reset', this.clearForm );

		},
		events: function() {
			return _.extend( {}, EditBaseView.prototype.events, {
				'click .media-uploader-btn': 'addImage',
				'submit': 'validate'
			} );
		},
		initValidator: function() {
			this.validator = new Validator( {
				form: this.$el,
				fields: this.$fieldsToValidate
			} );

			this.$fieldsToValidate.each( function() {
				$( this ).rules( 'add', {
					required: true,
					messages: {
						required: function( len, elem ) {
							var msg;
							if ( $( elem ).hasClass( 'alt-thumb' ) ) {
								msg = $.msg( 'videopagetool-formerror-altthumb' );
							} else {
								msg = $.msg( 'htmlform-required' );
							}
							return msg;
						}
					}
				} );
			} );
		},
		validate: function( e ) {
			e.preventDefault();
			var success,
				$firstError;

			// check for errors
			success = this.validator.onSubmit();

			// jump back up to form box if errors are present
			if ( !success ) {
				$firstError = $( '.error' ).eq( 0 );
				$firstError
					.closest( '.form-box' )
					.get( 0 )
					.scrollIntoView( true );
			}
		},
		initAddVideo: function() {
			this.$el.find( '.add-video-button' ).each( function() {
				var $this = $( this ),
					$box = $this.closest( '.form-box' ),
					$videoKeyInput = $this.siblings( '.video-key' ),
					$videoTitle = $this.siblings( '.video-title' ),
					$displayTitleInput = $box.find( '.display-title' ),
					$descInput = $box.find( '.description' ),
					$thumb = $box.find( '.video-thumb' ),
					callbackAfterSelect;

				callbackAfterSelect = function( url, vet ) {
					var $altThumbKey,
						req;

					$altThumbKey = $box.find( '.alt-thumb' ).val();
					req = {};

					if ( $altThumbKey.length ) {
						req.altThumbKey = $altThumbKey;
					}

					req.url = url;

					$.nirvana.sendRequest( {
						controller: 'VideoPageAdminSpecial',
						method: 'getVideoData',
						type: 'GET',
						format: 'json',
						data: req,
						callback: function( json ) {
							if( json.result === 'ok' ) {

								var video = json.video;
								$thumb.html();

								// update input value and remove any error messages that might be there.
								$videoKeyInput
									.val( video.videoKey )
									.removeClass( 'error' )
									.next( '.error' )
									.remove();
								$videoTitle
									.removeClass( 'alternative' )
									.text( video.videoTitle );
								$displayTitleInput
									.val( video.displayTitle )
									.trigger( 'keyup' ); // for validation
								$descInput.val( video.description )
									.trigger( 'keyup' ); // for validation

								// Update thumbnail html
								$thumb.html( video.videoThumb );

								// close VET modal
								vet.close();
							} else {
								window.GlobalNotification.show( json.msg, 'error' );
							}
						}
					} );

					// Don't move on to second VET screen.  We're done.
					return false;
				};

				$this.addVideoButton( {
					callbackAfterSelect: callbackAfterSelect
				} );
			} );
		},
		addImage: function( e ) {
			e.preventDefault();

			new ThumbnailUploader( {
				el: $( e.currentTarget ).closest( '.form-box' )
			} );

		},
		/*
		 * This reset is very specific to this form since it covers reverting titles and thumbnails
		 * @TODO: we may want to just create a default empty version of the form and hide it if it's not needed.
		 * that way we could just replace all the HTML to its default state without worrying about clearing every form
		 * field.
		 */
		clearForm: function() {
			// Reset video title
			this.$el.find( '.video-title' )
				.text( $.msg( 'videopagetool-video-title-default-text' ) )
				.addClass( 'alternative' );

			// Rest the video thumb
			this.$el.find( '.video-thumb' )
				.html( '' );

			// Hide all thumbnail preview links
			this.$el.find( '.preview-large-link' )
				.hide();

			// reset custom thumb name
			this.$el.find( '.alt-thumb-name' )
				.text( $.msg( 'videopagetool-image-title-default-text' ) )
				.addClass( 'alternative' );

			// Also clear all error messages for better UX
			this.validator.clearErrors();
		}
	} );

	return FeaturedVideo;
} );

