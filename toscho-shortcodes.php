<?php
/*
Plugin Name: Toscho’s Shortcodes
Plugin URI:  http://toscho.de/
Description: Some useful shortcodes. Enables Shortcodes in widgets, excerpts and term descriptions.
Version:     0.4
Author:      Thomas Scholz
Author URI:  http://toscho.de
License:     GPL2
License URI: http://www.gnu.org/licenses/gpl-2.0.html

    Copyright 2010  Thomas Scholz  (e-mail: info@toscho.de)

    This program is free software; you can redistribute it and/or
    modify it under the terms of the GNU General Public License
    version 2, as published by the Free Software Foundation.

    You may NOT assume that you can use any other version of the GPL.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    The license for this software can be found here:
    http://www.gnu.org/licenses/gpl-2.0.html


Changelog
v 0.4 (26.08.2010)
	- Added Parameter for feed output in sc_mail_address().
*/

// Initialize the plugin:
Toscho_Shortcodes::setup();

class Toscho_Shortcodes
{
	/**
	 * Usually the only function called from outside.
	 *
	 * @return void
	 */
	public static function setup()
	{
		self::enable_shortcodes_everywhere();

		add_shortcode('mail',
			array ( __CLASS__, 'sc_mail_address') );
		add_shortcode( 'bloginfo',
			array( __CLASS__, 'sc_bloginfo' ) );
		add_shortcode('subpages',
			array ( __CLASS__, 'sc_subpages') );
		add_shortcode('link',
			array ( __CLASS__, 'sc_id_to_link') );
		add_shortcode('table',
			array ( __CLASS__, 'sc_table') );
		add_shortcode('tr',
			array ( __CLASS__, 'sc_table_row') );

		add_filter('http_request_args',
			array ( __CLASS__, 'no_upgrade_check' ), 5, 2);

		return;
	}

	/**
	 * Enable Shortcodes everywhere.
	 *
	 * For Details
	 * @see http://sillybean.net/?p=2719
	 * of Stephanie C. Leary.
	 *
	 * @return void
	 */
	public static function enable_shortcodes_everywhere()
	{
		foreach (
			array (
				'the_excerpt'
			,	'widget_text'
			,	'term_description'
			,	'the_content'
			)
			as $filter )
		{
			add_filter($filter, 'shortcode_unautop');
			add_filter($filter, 'do_shortcode', 11);
		}

		return;
	}

// ---- Shortcode functions ------------------------------------------

	/**
	 * Creates a form to display the email address on POST.
	 *
	 * Parameters:
	 *    address:      Mail address
	 *    text:         Buttontext
	 *    beforelink:   Text before the mail link
	 *    afterlink:    Text after the mail link
	 *    beforeform:   Text before the form
	 *    afterform:    Text after the form
	 *    beforeinside: Text before the form content
	 *    afterinside:  Text after the form content
	 * Usage:
	 * [mail info@example.com] or
	 * [mail address='info@example.com' beforelink='E-Mail: ']
	 *
	 * Add
	 * <code>.mailswitch .checker { display:none; }</code>
	 * to your stylesheet.
	 *
	 * @todo Use a User-ID/Name instead of a mail address.
	 * @param  array $atts
	 * @return string
	 */
	public static function sc_mail_address($atts)
	{
		if ( ! isset ( $atts[0] ) and ! isset ( $atts['address'] ) )
		{
			return;
		}

		if ( ! isset ( $atts['address'] ) )
		{
			$atts['address'] = $atts[0];
		}

		$defaults = array (
			'text'         => 'E-Mail zeigen'
		,	'beforelink'   => ''
		,	'afterlink'    => ''
		,	'beforeform'   => ''
		,	'afterform'    => ''
		,	'beforeinside' => ''
		,	'afterinside'  => ''
		,	'hiddentitle'  => 'Bitte nicht ausfüllen.'
		,	'feed_text'    => 'E-Mail-Adresse auf der Website.'
		);

		extract( array_merge($defaults, $atts) );

		if ( is_feed() )
		{
			return $feed_text;
		}

		if ( 'POST' == $_SERVER['REQUEST_METHOD'] )
		{
			// Someone filled a field meant to stay empty. Spam!
			if ( ! empty ( $_POST['url'] ) )
			{
				return;
			}

			return "$beforelink<a href='mailto:$address'>$address</a>$afterlink";
		}

		$action = esc_attr($_SERVER['REQUEST_URI']);

		return <<<MAILFORM
$beforeform<form class="mailswitch" method="POST" action="$action">
$beforeinside<input size="1" name="url" class="checker" title="$hiddentitle">
<input type="submit" value="$text" class="showmailbutton">
$afterinside</form>$afterform
MAILFORM;
	}

	/**
	 * Creates a HTML table. Its content should be table rows ([tr]).
	 *
	 * Usage:
	 * [table header="Comic Name|Real Name" summary="My glorious summary!" caption="Super Heroes"]
	 * [tr]Batman|Bruce Wayne[/tr]
	 * [tr]Superman|Clark Kent[/tr]
	 * [/table]
	 * Keep the first line on ONE line, otherwise the sky will fall on your head.
	 *
	 * @see    sc_table_row()
	 * @param  array $atts
	 * @param  string $content
	 * @return string
	 */
	public static function sc_table($atts, $content = '')
	{
		$attributes = '';
		$header     = '';
		$caption    = '';

		if ( isset ( $atts['caption'] ) )
		{
			$caption = '<caption>' . $atts['caption'] . '</caption>';

			unset ( $atts['caption'] );
		}

		if ( isset ( $atts['header'] ) )
		{
			$header  = '<thead><tr><th>';
			$header .= str_replace('|', '</th><th>', $atts['header']);
			$header .= '</th></tr></thead>';

			unset ( $atts['header'] );
		}

		$attributes = self::html_attributes($atts);

		// WordPress’ annoying “help”.
		$content = str_replace('<br />', '', $content);
		$content = do_shortcode($content);

		return "<table$attributes>$caption$header$content</table>";
	}

	/**
	 * Creates a table row.
	 *
	 * Usage: [tr title="I like the joker better."]Batman|Bruce Wayne[/tr]
	 *
	 * @see    sc_table()
	 * @param  array $atts
	 * @return string
	 */
	public static function sc_table_row($atts = array(), $content = '')
	{
		if ( empty ( $content ) )
		{
			return;
		}

		$content    = str_replace('|', '</td><td>', $content);
		$attributes = self::html_attributes($atts);

		return "<tr$attributes><td>$content</td></tr>";

	}

	/**
	 * Shortcode for bloginfo()
	 *
	 * Usage: [bloginfo key="template_url"]
	 *
	 * @see    http://codex.wordpress.org/Template_Tags/get_bloginfo
	 * @param  array $atts
	 * @return string
	 */
	public static function sc_bloginfo($atts)
	{
		$defaults = array (
	    		'key'	=>	''
			,	'in_comments'  => FALSE
    	);

	    extract( shortcode_atts($defaults, $atts) );

	    return self::invert_comments(get_bloginfo($key), $in_comments);
	}

	/**
	 * List all childs of a page
	 *
	 * Usage: [subpages]
	 *
	 * @see    http://codex.wordpress.org/Template_Tags/wp_list_pages
	 * @param  array $atts
	 * @return string
	 */
	public static function sc_subpages($atts)
	{
		global $post;

		// Default parameters, overridable.
		$defaults = array (
			'authors'      => '',
			'date_format'  => get_option('date_format'),
			'depth'        => 0,
			'exclude'      => FALSE,
			'exclude_tree' => '',
			// Shortcode embedded in HTML comments? <!--[shortcode]-->
			'in_comments'  => FALSE,
			'include'      => '',
			'link_after'   => '',
			'link_before'  => '',
			'post_type'    => 'page',
			'sort_column'  => 'menu_order, post_title',
			'title_li'     => FALSE,
			'show_date'    => FALSE,
		);

		// Not overridable
		$protected = array (
			'child_of'     => $post->ID,
			'echo'         => FALSE
		);

		$args = array_merge($defaults, (array) $atts, $protected);

		// All overridden values are strings. Now we fix them.
		foreach (
			array ( 'show_date', 'exclude', 'title_li', 'in_comments' )
			as $param )
		{
			$args[$param] = self::string_to_bool($args[$param]);
		}

		$pages = wp_list_pages($args);

		// No child pages found.
		if ( empty ( $pages ) )
		{
			return;
		}

		// Surround on demand.
	    if ( FALSE == $args['title_li'] )
	    {
	    	$pages = "<ul class='subpages'>$pages</ul>";
	    }

	    return self::invert_comments($pages, $args['in_comments']);
	}

	/**
	 * Creates a link from the post id.
	 *
	 * Usage: [link id=42 title="Foo" class="bar"]Hello World![/link]
	 *
	 * Inspired by Sergej Müller
	 * @link   http://playground.ebiene.de/?p=2388
	 *
	 * @param  array $atts id (numeric) and additional HTML attributes
	 * @param  string $data
	 * @return string
	 */
	public static function sc_id_to_link($atts, $data)
	{
		// incomplete
		if ( ! isset ( $atts['id'] ) or ! is_numeric($atts['id']) )
		{
			return $data;
		}

		// test
		$url = get_permalink($atts['id']);

		// No entry with this ID.
		if ( ! $url )
		{
			return $data;
		}

		unset ( $atts['id'] );

		// Convert additional attributes to HTML.
		$attributes = self::html_attributes($atts);

		return "<a href='$url'$attributes>$data</a>";
	}

// ---- Helper -------------------------------------------------------

	/**
	 * Converts an array into HTML attributes.
	 *
	 * @param  array $atts
	 * @return string
	 */
	public static function html_attributes($atts)
	{
		if ( empty ( $atts ) or ! is_array($atts) )
		{
			return '';
		}

		$html = '';

		foreach ($atts as $key => $value )
		{
			$html .= " $key='" . esc_attr($value) . "'";
		}

		return $html;
	}

	/**
	 * Turn strings into boolean values.
	 *
	 * This is needed because user input when using shortcodes
	 * is automatically turned into a string.  So, we'll take those
	 * values and convert them.
	 *
	 * Taken from Justin Tadlock’s Plugin “Template Tag Shortcodes”
	 * @see    http://justintadlock.com/?p=1539
	 * @author Justin Tadlock
	 * @param  string $value String to convert to a boolean.
	 * @return bool|string
	 */
	public static function string_to_bool($value)
	{
		if ( is_numeric($value) )
		{
			return '0' == $value ? FALSE : TRUE;
		}

		// Neither 'true' nor 'false' nor 'null'
		if ( ! isset ( $value[3] ) )
		{
			return $value;
		}

		$lower = strtolower($value);

		if ( 'true' == $lower )
		{
			return TRUE;
		}

		if ( ( 'false' == $lower ) or ( 'null' == $lower ) )
		{
			return FALSE;
		}

		return $value;
	}

	/**
	 * Makes protected shortcodes – <!-- [shortcode] --> – visible
	 * by adding inverted HTML comments.
	 *
	 * @param string $text
	 * @param bool $commented
	 * @return string
	 */
	public static function invert_comments($text, $commented)
	{
		return $commented ? "-->$text<!--" : $text;
	}

	/**
	 * Blocks update checks for this plugin.
	 *
	 * @author Mark Jaquith http://markjaquith.wordpress.com
	 * @link   http://wp.me/p56-65
	 * @param  array $r
	 * @param  string $url
	 * @return array
	 */
	public static function no_upgrade_check($r, $url)
	{
		if ( 0 !== strpos($url, 'http://api.wordpress.org/plugins/update-check') )
		{ // Not a plugin update request. Bail immediately.
			return $r;
		}

		$plugins = unserialize( $r['body']['plugins'] );
		$p_base  = plugin_basename( __FILE__ );

		unset (
			$plugins->plugins[$p_base],
			$plugins->active[array_search($p_base, $plugins->active)]
		);

		$r['body']['plugins'] = serialize($plugins);

		return $r;
	}
}