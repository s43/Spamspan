<?php if ( !defined( 'HABARI_PATH' ) ) { die( 'No direct access' ); }

/**
 * Spamspan habari plugin
 * Protect your habari blog from spambots that collects email for spam concerns
 **/

class Spamspan extends Plugin
{
	public $emailreg = "([-\.\~\'\!\#\$\%\&\+\/\*\=\?\^\_\`\{\|\}\w\+^@]+) # Group 1 - Match the name part - dash, dot or
						                           #special characters.
						     @                     # @
						     ((?:        # Group 2
						       [-\w]+\.            # one or more letters or dashes followed by a dot.
						       )+                  # The whole thing one or more times
						       [A-Z]{2,6}          # with between two and six letters at the end (NB
						                           # .museum)
						     )";
	public $graphical_at = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAIAAACQkWg2AAAABmJLR0QA/wD/AP+gvaeTAAAACXBIWXMAAAGqAAABqgGhvxHMAAAAB3RJTUUH2AICEjQz+ShK9gAAApZJREFUKM9tkt8rewEYxp9zTtSyo805S1KSTdNJbKRpImqyC0UuXOygUKvlH7DccCfJlZT8KlNuSW78KDNyI2yz8mMlsWXaaTo3Mh3v9+L4rr71fe7e+jzv+/b0MESEv8rlcqurq5FI5PX1VVEUi8Vit9tlWe7p6eE4TmeYgmFubm5mZkYUxcHBQZvNZjKZ3t/fw+Hw3t6ew+HY3Ny02+0AQERE5Pf7jUbjxsaGpmn0r15eXrq7uwVBeH5+JiIQ0fr6Os/ziURCJ5LJ5OLi4vHxsaZpiUTi6Ojo+/vb4/G0tbUREbLZbFlZ2draGhH9/PwMDQ1xHOd0OouLi0dGRrq6upqbm4no8fGRYZjLy0ssLS1VVVXpn0xOTvI8f35+TkTpdLqmpgbAxMSEftnpdM7Pz7M7OzuyLLMsG4/HZ2dnp6am3G43gIqKCp/PB6C1tVVPpbKyMp1Os/f395IkATg8PGRZNhAIFFKurq4G4HK59PH6+tpms7Fvb28WiwXAycmJ1WotLS0tGG5ubgRBqK2tBZBMJlOpVFNTE2s2mzOZDABBEDKZzNfXl05ns9mtrS19fT6fHx8f93g8LpeLrauri8fjADo6OlRVDYVCAFRV9fl8Hx8fkiQpijI8PHx1dbW8vAwACwsLoih+fn5qmtbX1wegvr5eEITp6WmO44qKigwGg9VqjUajelaMqqotLS3t7e0rKyv5fP7g4ODh4cHr9UqSdHZ2dnp62tDQ0NnZaTAYgsGgyWQCEd3e3paUlIyNjSmKQv9TKpXq7e0VRfHp6em3SxcXF26322w2j46Obm9vRyKRWCwWDodDoZAsy0aj0ev13t3d/XapoN3d3YGBgcbGRp7nATAMU15e3t/fv7+/X2D+AO45eOhMvsRFAAAAAElFTkSuQmCC';

	/**
	 * function action_plugin_activation
	 * adds the optional options to configure Spamspan
	**/

	public function action_plugin_activation( $file )
	{
		if ( realpath( $file ) == __FILE__ ) {
			Options::set( 'spamspan__at', '[@]' );
			Options::set( 'spamspan__graphics', 0 );
		}
	}


	/**
	 * Add the configuration option to Spamspan
	 */

	public function filter_plugin_config( $actions, $plugin_id )
	{
		if ( $plugin_id == $this->plugin_id() ) {
			$actions[]= _t( 'Configure' );
		}
		return $actions;
	}


	/**
	 * Add Spamspan Configuration user interface
	 */

	public function action_plugin_ui( $plugin_id, $action )
	{
		if ( $plugin_id == $this->plugin_id() ) {
			switch ( $action ) {
				case _t( 'Configure' ):
					$ui = new FormUI( strtolower( get_class( $this ) ) );
					$ui->append( 'text', 'custom_at', 'spamspan__at', _t( 'Custom replacement for "@"' ) );
					$ui->append( 'checkbox', 'enable_graphics', 'spamspan__graphics', _t( 'Use a graphical replacement for "@"' ) );
					$ui->append( 'submit', 'save', _t( 'Save' ) );
					$ui->on_success( array( $this, 'updated_config' ) );
					$ui->out();
					break;
			}

		}
	}


	public function updated_config( $ui )
	{
		$ui->save();
		return false;
	}


	/**
	 * Add required Javascript and, for now, CSS.
	 */

	public function action_template_header_10()
	{
		Stack::add( 'template_header_javascript', $this->get_url('/assets/js/spamspan.js') , 'spamspan' );
	}	

	/**
	 * Add Spamspan's main hook, it includes the filters' calls, and also the customized regular expressions
	 */

	function spamspan_hook( $return ){
		$emailpattern = "!" . $this->emailreg . "!ix";
		$emailpattern_with_options = "!" . $this->emailreg . "[\|](.*) !ix";

		$mailtopattern = "!<a\s+                            # opening <a and spaces
		  (?:(?:\w+\s*=\s*)(?:\w+|\"[^\"]*\"|'[^']*'))*?  # any attributes
		  \s*                                             # whitespace
		  href\s*=\s*(['\"])(mailto:"                     # the href attribute
		  . $this->emailreg .                              # The email address
		  "(?:\?[A-Za-z0-9_= %\.\-\~\_\&]*)?)" .            # an optional ? followed
		                                                  # by a query string. NB
		                                                  # we allow spaces here,
		                                                  # even though strictly
		                                                  # they should be URL
		                                                  # encoded
		  "\\1                                            # the relevant quote
		                                                  # character
		  (?:(?:\s+\w+\s*=\s*)(?:\w+|\"[^\"]*\"|'[^']*'))*? # any more attributes
		  >                                               # end of the first tag
		  (.*?)                                           # tag contents.  NB this
		                                                  # will not work properly
		                                                  # if there is a nested
		                                                  # <a>, but this is not
		                                                  # valid xhtml anyway.
		  </a>                                            # closing tag
		  !ix";

		// Now we can convert all mailto URLs
		$text = preg_replace_callback( $mailtopattern, array($this, 'spamspan_mailto_callback'), $return );
		// all bare email addresses with optional formatting information
		$text = preg_replace_callback( $emailpattern_with_options, array($this, 'spamspan_formatted_callback'), $text );
		// and finally, all bare email addresses
		$text = preg_replace_callback( $emailpattern, array($this, 'spamspan_simple_callback'), $text );

		return $text;
	}

	/**
	 * Set an alias for Spamspan
	 */

	function alias()
	{
	  return array(
	    'spamspan_hook' => array( 'filter_post_content_out', 'filter_post_content_excerpt', 'filter_post_content_summary' ),
	  );
	}

	/**
	 * Filter emails within "a" tags, which includes mailto protocol
	 */

	public function spamspan_mailto_callback( $matches ) {
	  $headers = preg_split( '/[&;]/', parse_url($matches[2], PHP_URL_QUERY) );
	  if ($headers[0] == '') {
	    $headers = array();
	  }
	  return $this->output( $matches[3], $matches[4] );
	}

	/**
	 * Filter emails within the content
	 */

	public function spamspan_simple_callback( $matches ){
		if( strpos( $matches[0], '=' ) !== false ){
			$onlink = true;
		}else{
			$onlink = false;
		}
		return $this->output( $matches[1], $matches[2], $onlink );
	}

	/**
	 * Filter emails with optional formatting informations
	 */

	public function spamspan_formatted_callback( $matches ){
		// if( !isset( $matches[3] ) ) { $matches[3] = ""; }
		return $this->output( $matches[1], $matches[2] );
	}

	/**
	 * Output the filtered emails with spamspan's structure
	 */

	public function output( $name, $domain, $onlink = false ) {
		if( $onlink == false ){
			$at = ( Options::get('spamspan__graphics') == '1' )? '<img class="spam-span-image" src="'. $this->graphical_at .'" alt="" />' : Options::get('spamspan__at');

			$output = '<span class="u">' . $name . '</span>' . $at . '<span class="d">' . $domain . '</span>';

			$output = '<span class="spamspan">' . $output . '</span>';
		}else{
			$output = false;
		}
		return $output;
	}

}


?>