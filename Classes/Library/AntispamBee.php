<?php
namespace Tx\Spamshield\Library;
/*
Plugin Name: Antispam Bee
Text Domain: antispam_bee
Domain Path: /lang
Description: Easy and extremely productive spam-fighting plugin with many sophisticated solutions. Includes protection again trackback spam.
Author: Sergej M&uuml;ller
Author URI: http://wpcoder.de
Plugin URI: http://antispambee.com
Version: 2.5.9
*/


/* Sicherheitsabfrage */
if ( ! class_exists('WP') ) {
	die();
}


/**
* Antispam_Bee
*
* @since   0.1
* @change  2.4
*/

class AntispamBee {


	/* Init */
	public static $defaults;
	private static $_base;
	private static $_secret;
	private static $_reason;


	/**
	 * Prüfung und Rückgabe eines Array-Keys
	 *
	 * @since   2.4.2
	 * @change  2.4.2
	 *
	 * @param   array   $array  Array mit Werten
	 * @param   string  $key    Name des Keys
	 * @return  mixed           Wert des angeforderten Keys
	 */

	public static function get_key($array, $key)
	{
		if ( empty($array) or empty($key) or empty($array[$key]) ) {
			return null;
		}

		return $array[$key];
	}


	############################
	######  SPAMPRÜFUNG  #######
	############################


	/**
	* Überprüfung der POST-Werte
	*
	* @since   0.1
	* @change  2.4.2
	*/

	public static function precheck_incoming_request()
	{
		/* Nur Frontend */
		if ( is_feed() or is_trackback() or self::_is_mobile() ) {
			return;
		}

		/* Allgemeine Werte */
		$request_url = self::get_key($_SERVER, 'REQUEST_URI');
		$hidden_field = self::get_key($_POST, 'comment');
		$plugin_field = self::get_key($_POST, self::$_secret);

		/* Falsch verbunden */
		if ( empty($_POST) or empty($request_url) or strpos($request_url, 'wp-comments-post.php') === false ) {
			return;
		}

		/* Felder prüfen */
		if ( empty($hidden_field) && !empty($plugin_field) ) {
			$_POST['comment'] = $plugin_field;
			unset($_POST[self::$_secret]);
		} else {
			$_POST['bee_spam'] = 1;
		}
	}


	/**
	* Prüfung der eingehenden Anfragen auf Spam
	*
	* @since   0.1
	* @change  2.4.2
	*
	* @param   array  $comment  Unbehandelter Kommentar
	* @return  array  $comment  Behandelter Kommentar
	*/

	public static function handle_incoming_request($comment)
	{
		/* Server-Werte */
		$url = self::get_key($_SERVER, 'REQUEST_URI');

		/* Leere Werte? */
		if ( empty($url) ) {
			return self::_handle_spam_request(
				$comment,
				'empty'
			);
		}

		/* Ping-Optionen */
		$ping = array(
			'types'   => array('pingback', 'trackback', 'pings'),
			'allowed' => !self::get_option('ignore_pings')
		);

		/* Kommentar */
		if ( strpos($url, 'wp-comments-post.php') !== false && !empty($_POST) ) {
			/* Filter ausführen */
			$status = self::_verify_comment_request($comment);

			/* Spam lokalisiert */
			if ( !empty($status['reason']) ) {
				return self::_handle_spam_request(
					$comment,
					$status['reason']
				);
			}

		/* Trackback */
		} else if ( in_array(self::get_key($comment, 'comment_type'), $ping['types']) && $ping['allowed'] ) {
			/* Filter ausführen */
			$status = self::_verify_trackback_request($comment);

			/* Spam lokalisiert */
			if ( !empty($status['reason']) ) {
				return self::_handle_spam_request(
					$comment,
					$status['reason'],
					true
				);
			}
		}

		return $comment;
	}


	/**
	* Prüfung eines Kommentars auf seine Existenz im lokalen Spam
	*
	* @since   2.0.0
	* @change  2.5.4
	*
	* @param   string	$ip     Kommentar-IP
	* @param   string	$url    Kommentar-URL [optional]
	* @param   string	$email  Kommentar-Email [optional]
	* @return  boolean          TRUE bei verdächtigem Kommentar
	*/

	private static function _is_db_spam($ip, $url = '', $email = '')
	{
		/* Global */
		global $wpdb;

		/* Default */
		$filter = array('`comment_author_IP` = %s');
		$params = array($ip);

		/* URL abgleichen */
		if ( ! empty($url) ) {
			$filter[] = '`comment_author_url` = %s';
			$params[] = $url;
		}

		/* E-Mail abgleichen */
		if ( ! empty($email) ) {
			$filter[] = '`comment_author_email` = %s';
			$params[] = $email;
		}

		/* Query ausführen */
		$result = $wpdb->get_var(
			$wpdb->prepare(
				sprintf(
					"SELECT `comment_ID` FROM `$wpdb->comments` WHERE `comment_approved` = 'spam' AND (%s) LIMIT 1",
					implode(' OR ', $filter)
				),
				$params
			)
		);

		return !empty($result);
	}


	/**
	* Prüfung auf DNSBL Spam
	*
	* @since   2.4.5
	* @change  2.4.5
	*
	* @param   string   $ip  IP-Adresse
	* @return  boolean       TRUE bei gemeldeter IP
	*/

	private static function _is_dnsbl_spam($ip)
	{
		/* Start request */
		$response = wp_remote_get(
			esc_url_raw(
				sprintf(
					'http://www.stopforumspam.com/api?ip=%s&f=json',
					$ip
				),
				'http'
			)
		);

		/* Response error? */
		if ( is_wp_error($response) ) {
			return false;
		}

		/* Get JSON */
		$json = wp_remote_retrieve_body($response);

		/* Decode JSON */
		$result = json_decode($json);

		/* Empty data */
		if ( empty($result->success) ) {
			return false;
		}

		/* Return status */
		return (bool) $result->ip->appears;
	}


	/**
	* Prüfung auf eine gefälschte IP
	*
	* @since   2.0
	* @change  2.5.1
	*
	* @param   string   $ip    IP-Adresse
	* @param   string   $host  Host [optional]
	* @return  boolean         TRUE bei gefälschter IP
	*/

	private static function _is_fake_ip($ip, $host = false)
	{
		/* Remote Host */
		$hostbyip = gethostbyaddr($ip);

		/* IPv6 */
		if ( !self::_is_ipv4($ip) ) {
			return $ip != $hostbyip;
		}

		/* IPv4 / Kommentar */
		if ( empty($host) ) {
			$found = strpos(
				$ip,
				self::_cut_ip(
					gethostbyname($hostbyip)
				)
			);

		/* IPv4 / Trackback */
		} else {
			/* IP-Vergleich */
			if ( $hostbyip == $ip ) {
				return true;
			}

			/* Treffer suchen */
			$found = strpos(
				$ip,
				self::_cut_ip(
					gethostbyname($host)
				)
			);
		}

		return $found === false;
	}

	/**
	* Kürzung der IP-Adressen
	*
	* @since   0.1
	* @change  2.5.1
	*
	* @param   string   $ip       Original IP
	* @param   boolean  $cut_end  Kürzen vom Ende?
	* @return  string             Gekürzte IP
	*/

	private static function _cut_ip($ip, $cut_end = true)
	{
		/* Trenner */
		$separator = ( self::_is_ipv4($ip) ? '.' : ':' );

		return str_replace(
			( $cut_end ? strrchr( $ip, $separator) : strstr( $ip, $separator) ),
			'',
			$ip
		);
	}


	/**
	* Anonymisierung der IP-Adressen
	*
	* @since   2.5.1
	* @change  2.5.1
	*
	* @param   string  $ip  Original IP
	* @return  string       Anonyme IP
	*/

	private static function _anonymize_ip($ip)
	{
		if ( self::_is_ipv4($ip) ) {
			return self::_cut_ip($ip). '.0';
		}

		return self::_cut_ip($ip, false). ':0:0:0:0:0:0:0';
	}


	/**
	* Dreht die IP-Adresse
	*
	* @since   2.4.5
	* @change  2.4.5
	*
	* @param   string   $ip  IP-Adresse
	* @return  string        Gedrehte IP-Adresse
	*/

	private static function _reverse_ip($ip)
	{
		return implode(
			'.',
			array_reverse(
				explode(
					'.',
					$ip
				)
			)
		);
	}


	/**
	* Prüfung auf eine IPv4-Adresse
	*
	* @since   2.4
	* @change  2.4
	*
	* @param   string   $ip  Zu prüfende IP
	* @return  integer       Anzahl der Treffer
	*/

	private static function _is_ipv4($ip)
	{
		return preg_match('/^\d{1,3}(\.\d{1,3}){3,3}$/', $ip);
	}
}