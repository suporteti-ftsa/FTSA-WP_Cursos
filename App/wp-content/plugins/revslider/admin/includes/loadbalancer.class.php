<?php
/**
 * @author    ThemePunch <info@themepunch.com>
 * @link      https://www.themepunch.com/
 * @copyright 2024 ThemePunch
 */
 
if(!defined('ABSPATH')) exit();

class RevSliderLoadBalancer extends RevSliderFunctions {

	public $servers = [];
	public $defaults = ['themepunch.tools', 'themepunch-ext-a.tools', 'themepunch-ext-b.tools', 'themepunch-ext-c.tools'];
	 

	/**
	 * set the server list on construct
	 **/
	public function __construct(){
		$this->servers = $this->get_options(['system', 'servers'], []);
		if(empty($this->servers)){
			shuffle($this->defaults);
			$this->update_option(['system', 'servers'], $this->defaults);
		}
		
		$this->servers = (empty($this->servers)) ? $this->defaults : $this->servers;
		
		
	}

	/**
	 * get the url depending on the purpose, here with key, you can switch do a different server
	 **/
	public function get_url($purpose, $key = 0, $force_http = false){
		$url	 = ($force_http ) ? 'http://' : 'https://';
		$use_url = (!isset($this->servers[$key])) ? reset($this->servers) : $this->servers[$key];
		
		switch($purpose){
			case 'updates':
				$url .= 'updates.';
				break;
			case 'templates':
				$url .= 'templates.';
				break;
			case 'library':
				$url .= 'library.';
				break;
			default:
				return false;
		}
		
		$url .= $use_url;
		
		return $url;
	}
	
	/**
	 * refresh the server list to be used, will be done once in a month
	 **/
	public function refresh_server_list($force = false){
		global $wp_version;
		
		$rs_rsl		= (isset($_GET['rs_refresh_server'])) ? true : false;
		$last_check	= $this->get_options(['timestamps', 'servers'], false);
		if($last_check === false || empty($last_check)) $this->update_option(['timestamps', 'servers'], time());

		if($force === true || $rs_rsl === true || ($last_check !== false && time() - $last_check > 60 * 60 * 24 * 30)){
			//$url = $this->get_url('updates');
			$url	 = 'https://updates.themepunch.tools';
			$request = wp_safe_remote_post($url.'/get_server_list.php', [
				'user-agent' => 'WordPress/'.$wp_version.'; '.get_bloginfo('url'),
				'body'		 => [
					'item'		=> urlencode(RS_PLUGIN_SLUG),
					'version'	=> urlencode(RS_REVISION)
				],
				'timeout'	 => 45
			]);
			
			if(!is_wp_error($request)){
				if($response = maybe_unserialize($request['body'])){
					$list = json_decode($response, true);
					$this->update_option(['system', 'servers'], $list);
				}
			}
			
			$this->update_option(['timestamps', 'servers'], time());
		}
	}
	
	/**
	 * move the server list, to take the next server as the one currently seems unavailable
	 **/
	public function move_server_list(){
		$servers	= $this->servers;
		$a			= array_shift($servers);
		$servers[]	= $a;
		
		$this->servers = $servers;
		$this->update_option(['system', 'servers'], $servers);
	}

	protected function validate_url($url, $subdomain, $force_http){
		if(!preg_match("/^https?:\/\//i", $url)){
			//just a filename passed, lets build an url
			$server	 = $this->get_url($subdomain, 0, $force_http);
			$url = $server . '/' . ltrim($url, '/');
		}else{
			//full URL passed, lets check if we need to force http
			if($force_http) $url = preg_replace("/^https:\/\//i", "http://", $url);
		}

		return $url;
	}
	
	/**
	 * call an themepunch URL and retrieve data
	 **/
	public function call_url($url, $data, $subdomain = 'updates', $force_http = false){
		global $wp_version;
		
		//add version if not passed
		$data['version'] = (!isset($data['version'])) ? urlencode(RS_REVISION) : $data['version'];
		$count	= 0;
		
		do{
			$url = $this->validate_url($url, $subdomain, $force_http);
			$request = wp_safe_remote_post($url, [
				'user-agent' => 'WordPress/'.$wp_version.'; '.get_bloginfo('url'),
				'body'		 => $data,
				'timeout'	 => 45
			]);
			
			$response_code = wp_remote_retrieve_response_code($request);
			if($response_code == 200) return $request;
			
			$this->move_server_list();
			$count++;
		}while($count < 3);
		
		return $request;
	}

	/**
	 * Streams a downloaded file directly to the disk
	 * USE WITH DIRECT URLS, as downloads_url() has wp_safe_remote_get() inside
	 *
	 * @param string $media      media path on the server
	 * @param string $dst        destination path
	 * @param string $subdomain  subdomain to use
	 * @param bool   $force_http force http
	 *
	 * @return string|WP_Error   Destination path on success, WP_Error object on failure
	 */
	public function download_url($media, $dst, $subdomain = 'templates', $force_http = false){
		if(!function_exists('download_url')) require_once ABSPATH . 'wp-admin/includes/file.php';
		$url = $this->validate_url($media, $subdomain, $force_http);
		$tmp  = download_url($url, 45);
		if (is_wp_error($tmp)) {
			return $tmp;
		}

		if (!wp_mkdir_p(dirname($dst))) {
			wp_delete_file($tmp);
			return new WP_Error('mkdir_fail', 'Uploads dir not writable');
		}
		if (!@rename($tmp, $dst)) {
			wp_delete_file($tmp);
			return new WP_Error('move_fail', 'Failed to move file');
		}

		return $dst;
	}

	/**
	 * Downloads a file directly to the disk using wp_remote_post()
	 *
	 * @param string $url media url
	 * @param string $dst destination path
	 * @param array  $data POST data
	 * @param string $subdomain subdomain to use
	 * @param bool $force_http force http
	 *
	 * @return string|WP_Error   Destination path on success, WP_Error object on failure
	 */
	public function download_url_post($url, $dst, $data, $subdomain = 'updates', $force_http = false) {
		global $wp_version;
		
		if(!function_exists('wp_tempnam')) require_once ABSPATH . 'wp-admin/includes/file.php';

		$tmpfname = wp_tempnam( $url );
		if ( ! $tmpfname ) {
			return new WP_Error( 'http_no_file', __( 'Could not create temporary file.' ) );
		}

		$url = $this->validate_url($url, $subdomain, $force_http);

		$response = wp_safe_remote_post($url, [
			'user-agent' => 'WordPress/'.$wp_version.'; '.get_bloginfo('url'),
			'body'		 => $data,
			'timeout'	 => 45,
			'stream'   => true,
			'filename' => $tmpfname,
		]);

		if ( is_wp_error( $response ) ) {
			@unlink( $tmpfname );
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $response_code ) {
			$error_body = file_get_contents( $tmpfname );
			@unlink( $tmpfname );

			return new WP_Error(
				'http_error',
				'Remote server returned ' . $response_code,
				array( 'body' => $error_body )
			);
		}

		if (!wp_mkdir_p(dirname($dst))) {
			wp_delete_file($tmpfname);
			return new WP_Error('mkdir_fail', 'Uploads dir not writable');
		}
		if (!@rename($tmpfname, $dst)) {
			wp_delete_file($tmpfname);
			return new WP_Error('move_fail', 'Failed to move file');
		}

		return $dst;
	}
}
