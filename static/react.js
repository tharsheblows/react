( function( window, document, settings ) {

	/**
	 * Flag to show if the emoji JSON blob is being loaded
	 *
	 * @type bool
	 */
	var loading = false;

	/**
	 * Flag to show if the emoji JSON blob is loaded
	 *
	 * @type bool
	 */
	var loaded = false;

	/**
	 * The list of all emoji.
	 *
	 * @type array
	 */
	var emoji = [];

	/**
	 * Pointer to the popup element
	 *
	 * @type HtmlElement
	 */
	var popup = null;

	/**
	 * Flag to show if the popup has been populated already.
	 *
	 * @type bool
	 */
	var popupPopulated = false;

	/**
	 * Click handler for when a reaction button is clicked
	 *
	 * @param  Event event The click event
	 */
 	var reactionClick = function( event ) {
		var el, parent;

		event = event || window.event;

		el = event.target || event.srcElement;

		parent = el;

		while ( parent ) {
			if ( 'DIV' === parent.nodeName && parent.className && typeof parent.className === 'string' && parent.className.indexOf( 'emoji-reaction' ) !== -1 ) {
				break;
			}
			parent = parent.parentElement;
		}

		if ( ! parent ) {
			hideReactionPopup();
			return;
		}

		if ( parent.className.indexOf( 'emoji-reaction-add' ) !== -1 ) {
			event.preventDefault();
			event.stopPropagation();
			if ( ! popup || 'none' === popup.style.display ) {
				showReactionPopup( parent );
			} else {
				hideReactionPopup();
			}
		} else if ( parent.className.indexOf( 'emoji-reaction-tab' ) !== -1 ) {
			event.preventDefault();
			event.stopPropagation();
			changeReactionTab( parent.dataset.tab );
		} else if ( parent.className.indexOf( 'emoji-reaction' ) !== -1 && parent.className.indexOf( 'open' ) !== -1 ) {
			event.preventDefault();
			event.stopPropagation();
			react( parent );
			hideReactionPopup();
		}
	}

	/**
	 * Add the emoji list to the reaction popup.
	 */
	var populateReactionPopup = function() {
		var ii, jj, tab, html, character;
		if ( ! loaded ) {
			return;
		}

		if ( popupPopulated ) {
			return;
		}

		popupPopulated = true;

		if ( ! popup ) {
			popup = document.getElementById( 'emoji-reaction-selector' );
		}

		for( ii = 0; ii <= 7; ii++ ) {
			if ( ! emoji[ ii ] ) {
				continue;
			}

			tab = popup.getElementsByClassName( 'container-' + ii );
			if ( 1 !== tab.length ) {
				continue;
			}
			tab = tab[0];

			html = '';
			for( jj = 0; jj < emoji[ ii ].length; jj++ ) {
				if ( ! emoji[ ii ][ jj ] ) {
					continue;
				}

				character = String.fromCodePoint.apply( this, emoji[ ii ][ jj ] );
				// why? well, the duplicate comment check gets confused by emojis so I'm storing them as their unicode character which works
				html += '<div data-emoji="' + emoji[ ii ][ jj ] + '" data-emoji-rendered="' + character + '" class="emoji-reaction open"><div class="emoji">';
				html += character;
				html += '</div></div>';
			}

			tab.innerHTML = html;
		}
	}

	/**
	 * Displays the emoji selector
	 *
	 * @param  HtmlElement el The button that was clicked
	 */
	var showReactionPopup = function( el ) {
		var left = 0, top = 0,
			parent;

		populateReactionPopup();

		popup.dataset.post = el.dataset.post;

		if ( document.documentElement.clientWidth > 768 ) {
			parent = el;

			while ( parent ) {
				left += parent.offsetLeft;
				top += parent.offsetTop;
				parent = parent.offsetParent;
			}

			top -= 300;

			popup.style.left = left + 'px';
			popup.style.top = top + 'px';
		}

		changeReactionTab( 0 );

		popup.style.display = 'block';
	};

	/**
	 * Hide the reaction popup.
	 */
	var hideReactionPopup = function() {
		if ( popup && 'none' !== popup.style.display ) {
			popup.style.display = 'none';
		}
	}

	/**
	 * Switch to a different tab in the reactions popup.
	 *
	 * @param  int tab_number The tab number to switch to.
	 */
	var changeReactionTab = function( tab_number ) {

		var ii;
		for( ii = 0; ii <= 7; ii++ ) {
			tab = popup.getElementsByClassName( 'container-' + ii );
			if ( 1 !== tab.length ) {
				continue;
			}
			tab = tab[0];

			if ( ii === parseInt( tab_number ) ) {
				tab.style.display = 'block';
			} else {
				tab.style.display = 'none';
			}
		}
	}

	/**
	 * Send a reaction message back to the server
	 *
	 * @param  HtmlElement el The button that was clicked
	 */
	var react = function( el ) {
		var post, params, xhr;
		
		var parent = el;
		while ( ( parent = parent.parentElement) && !parent.classList.contains( 'emoji-reactions' ) ); // get emoji reactions div

		if ( el.dataset.post ) {
			post = el.dataset.post;
		} else {
			post = el.parentElement.parentElement.dataset.post;
		}

		params = 'post=' + post + '&emoji=' + el.dataset.emoji + '&emoji_rendered=' + el.dataset.emojiRendered;

		xhr = new XMLHttpRequest();

		xhr.open( 'POST', settings.endpoint, true );

		xhr.onload = function( e ) {
		    if ( xhr.status === 200 ) {
		       var data = JSON.parse( xhr.responseText );

		       var parent_id = parseInt( data.comment_post_ID );

		       var emoji_saved = data.comment;
		       var emoji_rendered = data.comment_rendered;

		       var emoji_reactions = document.querySelector( '#post-' + parent_id + ' .emoji-reactions' );
		       var same_emoji_reaction = emoji_reactions.querySelector( '.emoji-reaction[data-emoji="' + emoji_saved + '"]' );

		       if( same_emoji_reaction ){
		       		var same_emoji_reaction_count = same_emoji_reaction.querySelector( '.count' ).textContent;
		       		var new_count = parseInt( same_emoji_reaction_count ) + 1;
		       		same_emoji_reaction.querySelector( '.count' ).textContent = new_count;
		       }
		       else{
		       		var new_emoji_reaction = '<div data-emoji="' + emoji_saved + '" data-count="1" data-post="' + parent_id + '" class="emoji-reaction open highlight"><div class="emoji">' + emoji_rendered + '</div><div class="count">1</div></div>';
		       		emoji_reactions.innerHTML = emoji_reactions.innerHTML + new_emoji_reaction;
		       }
		    }
		    else if( xhr.status === 409 ) {
		        console.log( ':( ' + xhr.status );
		    }
		};

		xhr.setRequestHeader( 'Content-type', 'application/x-www-form-urlencoded' );
		xhr.setRequestHeader( 'X-WP-Nonce', WP_API_Settings.nonce );  // allow it to pick up cookies to test for logged in users

		xhr.send( params );
	};

	/**
	 * Load the emoji definition JSON blob
	 */
	var loadEmoji = function() {
		var xhr;

		if ( loading ) {
			return;
		}
		loading = true;

		xhr = new XMLHttpRequest();
		xhr.onreadystatechange = function() {
			if ( xhr.readyState === XMLHttpRequest.DONE ) {
				if ( 200 === xhr.status ) {
					loaded = true;
					emoji = JSON.parse( xhr.responseText );
				}
			}
		}

		xhr.open( 'GET', settings.emoji_url, true );
		xhr.send();
	}

	if ( 'complete' === document.readyState ) {
		loadEmoji();
	} else {
		if ( document.addEventListener ) {
			document.addEventListener( 'DOMContentLoaded', loadEmoji, false );
			window.addEventListener( 'load', loadEmoji, false );
		} else {
			window.attachEvent( 'onload', loadEmoji );
			document.attachEvent( 'onreadystatechange', function() {
				if ( 'complete' === document.readyState ) {
					loadEmoji();
				}
			} );
		}
	}

	if ( document.addEventListener ) {
		document.addEventListener( 'click', reactionClick );
	} else {
		document.attachEvent( 'click', reactionClick );
	}

} )( window, document, window.wp.react.settings );


/*! http://mths.be/fromcodepoint v0.1.0 by @mathias */
if (!String.fromCodePoint) {
  (function() {
    var defineProperty = (function() {
      // IE 8 only supports `Object.defineProperty` on DOM elements
      try {
        var object = {};
        var $defineProperty = Object.defineProperty;
        var result = $defineProperty(object, object, object) && $defineProperty;
      } catch(error) {}
      return result;
    }());
    var stringFromCharCode = String.fromCharCode;
    var floor = Math.floor;
    var fromCodePoint = function() {
      var MAX_SIZE = 0x4000;
      var codeUnits = [];
      var highSurrogate;
      var lowSurrogate;
      var index = -1;
      var length = arguments.length;
      if (!length) {
        return '';
      }
      var result = '';
      while (++index < length) {
        var codePoint = Number(arguments[index]);
        if (
          !isFinite(codePoint) ||       // `NaN`, `+Infinity`, or `-Infinity`
          codePoint < 0 ||              // not a valid Unicode code point
          codePoint > 0x10FFFF ||       // not a valid Unicode code point
          floor(codePoint) != codePoint // not an integer
        ) {
          throw RangeError('Invalid code point: ' + codePoint);
        }
        if (codePoint <= 0xFFFF) { // BMP code point
          codeUnits.push(codePoint);
        } else { // Astral code point; split in surrogate halves
          // http://mathiasbynens.be/notes/javascript-encoding#surrogate-formulae
          codePoint -= 0x10000;
          highSurrogate = (codePoint >> 10) + 0xD800;
          lowSurrogate = (codePoint % 0x400) + 0xDC00;
          codeUnits.push(highSurrogate, lowSurrogate);
        }
        if (index + 1 == length || codeUnits.length > MAX_SIZE) {
          result += stringFromCharCode.apply(null, codeUnits);
          codeUnits.length = 0;
        }
      }
      return result;
    };
    if (defineProperty) {
      defineProperty(String, 'fromCodePoint', {
        'value': fromCodePoint,
        'configurable': true,
        'writable': true
      });
    } else {
      String.fromCodePoint = fromCodePoint;
    }
  }());
}
