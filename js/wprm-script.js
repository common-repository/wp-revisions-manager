jQuery(document).ready(function($) {
	//Ajax clear revisions
	if ($( '.misc-pub-revisions b' ).length > 0){
		$( '#wprd-clear-revisions' ).appendTo( '.misc-pub-revisions' ).show();
		$( '#wprd-clear-revisions a.once' ).on("click",function(event){
			event.preventDefault();
			$(this).removeClass( 'once' ).html($(this).data( 'action' )).blur();
			$( '#wprd-clear-revisions a.wprd-link' ).css({'text-decoration' : 'none'})
			$( '#wprd-clear-revisions .wprd-loading' ).css( 'display','inline-block' );
			$.ajax({
				url: ajaxurl,
				data: {
					'action': 'wprd_purge_revisions',
					'wprd-nonce' : $( '#wprd-clear-revisions a' ).data( 'nonce' ),
					'wprd-post_ID' : $( '#post #post_ID' ).val()
				},
				success: function(response) {
					if( response.success) {
						$( '#revisionsdiv' ).slideUp();
						$( '#wprd-clear-revisions .wprd-loading, .misc-pub-revisions > a' ).remove();
						$( '.misc-pub-revisions b' ).text( '0' );
						$( '#wprd-clear-revisions a.wprd-link' ).addClass( 'sucess' ).html( '<span class="dashicons dashicons-yes" style="color:#7ad03a;"></span> ' + response.data);
					} else {
						$( '#wprd-clear-revisions .wprd-loading' ).remove();
						$( '#wprd-clear-revisions a.wprd-link' ).addClass( 'error' ).html(response.data);
					}
					setTimeout( function () {
						$( '#wprd-clear-revisions a.wprd-link' ).fadeOut();
					}, 3500);
				},
				error: function(response){
					$( '#wprd-clear-revisions .wprd-loading' ).remove();
					$( '#wprd-clear-revisions a' ).html($( '#wprd-clear-revisions a' ).data( 'error' )).addClass( 'error' );
				}
			});
		});
	}
	//Ajax single revision delete
	if ($( '.post-php #revisionsdiv' ).length > 0 && $( '#wprd-btn-container' ).length){

		$( '#wprd-btn-container .wprd-btn' ).clone().appendTo( '#revisionsdiv .post-revisions li' );
		$.each( $( '#revisionsdiv .wprd-btn' ), function() {
			var url = $(this).parent( 'li' ).find( 'a' ).attr('href');
			var revID = url.split( 'revision=' ).pop();
			$(this).attr('data-revid', revID);
		});

		$( '#revisionsdiv .wprd-btn.once' ).on("click",function(event){
			event.preventDefault();
			var elem = $(this);
			elem.removeClass( 'once' );
			$('<span class="wprd-loading" style="display:inline-block"></span>').insertAfter( elem );

			$.ajax({
				url: ajaxurl,
				data: {
					'action': 'wprd_single_revision_delete',
					'revID' : elem.data( 'revid' ),
					'wprd-post_ID' : $( '#post #post_ID' ).val()
				},
				success: function(response) {
					elem.hide();
					var count = $( '.misc-pub-revisions b' ).text();
					elem.parent( 'li' ).find( '.wprd-loading' ).hide();
					if( response.success) {
						count = count - 1;
						$( '.misc-pub-revisions b' ).text( count );
						elem.parent( 'li' ).addClass( 'sucess' ).append( '<span class="dashicons dashicons-yes" style="color:#7ad03a;"></span> <b>' + response.data + '</b>' );
					} else {
						elem.parent( 'li' ).addClass( 'error' ).append( '<b> ' + response.data + '</b>' );
					}
					setTimeout( function () {
						elem.parent( 'li' ).fadeOut();
						elem.remove();
						if ( count == '0' ){
							$( '#revisionsdiv' ).slideUp();
							$( '#wprd-clear-revisions, .misc-pub-revisions > a' ).remove();
						}
					}, 3500);
				},
				error: function(response){
					elem.parent( 'li' ).find( '.wprd-loading' ).hide();
					elem.parent( 'li' ).addClass( 'error' ).append( '<b> ' + $( '#wprd-clear-revisions a' ).data( 'error' ) + '</b>' );
					elem.remove();
				}
			});
		});
	}


	$(document).on("click","#wp-admin-bar-wprd-adminbarmenu.action.wprd-btn.all a", function(e) {
		event.preventDefault();
		var elem = $(this);
		//elem.removeClass( 'once' );
		//$('<span class="wprd-loading" style="display:inline-block"></span>').insertAfter( elem );

		$.ajax({
			url: ajaxurl,
			data: {
				'action': 'wprd_purge_allrevisions',
			},
			success: function(response) {
				//elem.hide();
				var count = $( '.misc-pub-revisions b' ).text();
				//elem.parent( 'li' ).find( '.wprd-loading' ).hide();
				if( response.success) {
					count = count - 1;
					//$( '.misc-pub-revisions b' ).text( count );
					//elem.parent( 'li' ).addClass( 'sucess' ).append( '<span class="dashicons dashicons-yes" style="color:#7ad03a;"></span> <b>' + response.data + '</b>' );

				} else {
					//elem.parent( 'li' ).addClass( 'error' ).append( '<b> ' + response.data + '</b>' );
				}
				location.reload();

				setTimeout( function () {
					//elem.parent( 'li' ).fadeOut();
					//elem.remove();
					if ( count == '0' ){
						$( '#revisionsdiv' ).slideUp();
						$( '#wprd-clear-revisions, .misc-pub-revisions > a' ).remove();
					}
				}, 3500);
			},
			error: function(response){
				elem.parent( 'li' ).find( '.wprd-loading' ).hide();
				elem.parent( 'li' ).addClass( 'error' ).append( '<b> ' + $( '#wprd-clear-revisions a' ).data( 'error' ) + '</b>' );
				//elem.remove();
			}
		});
	});


});
