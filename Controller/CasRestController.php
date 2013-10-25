<?php

namespace Main\CasRestBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

class CasRestController extends Controller
{
        protected $cas_rest_url; 
	protected $cas_service_url;
	protected $service_url;
	protected $cas_cert;
	protected $cas_local;
	protected $source_dn;
	protected $base_dn;
	protected $error_message;

	public function __construct($cas_rest_url, $cas_service_url, $cas_cert = FALSE, $cas_local = FALSE, $source_dn = FALSE, $base_dn = FALSE, $service_url = FALSE)
	{
		$this->cas_rest_url = $cas_rest_url;
		$this->cas_service_url = $cas_service_url;
		$this->service_url = $service_url;
		$this->cas_cert = $cas_cert;
		$this->cas_local = $cas_local;
		$this->source_dn = $source_dn;
		$this->base_dn = $base_dn;

		if($cas_local)//If $cas_local is set, set the $service_url
		{
		  $this->service_url = $cas_local;
		}
		elseif(!$cas_local && !$service_url)//else cas_local is not set and $service_url is not set.
		{
		  if(!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443)
		    $prefix = 'https://';
		  else
		    $prefix = 'http://';

		  $this->service_url = $prefix . $_SERVER['SERVER_NAME'];
		}

		$this->error_message = 'No error message has been set.';
	}


	/**
	 * Helper function ito perform a curl post
	 */
	protected function curl($fields, $url)
	{
		$fields_query = http_build_query($fields);

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_HEADER, TRUE);
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_POST, TRUE);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_query);

		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$this->set_ssl($ch);
		$output = curl_exec($ch);


		curl_close($ch);

		return $output;
	}

	/**
	 * Get the TGT value by passing the cas username nad password to the API.
	 * Step 1 of CAS REST
	 * @param unknown $cas_username cas username to authenticate against
	 * @param unknown $cas_password cas password to use
	 * @returns array containing TGT with key 'tgt'
	 */
	public function request_tgt($cas_username, $cas_password)
	{
		$fields = array('username' => $cas_username, 'password' => $cas_password);
		$url = $this->get_cas_rest_url();

		$output = $this->curl($fields, $url);
		$tgt = $this->parse_output($output, '/TGT-?(.*)/');

		if($tgt)
			return $tgt;
		else
			throw new AuthenticationException("Ticket Granting Ticket Was False. Header Output: " . $this->parse_error($output));

	}


	/**
	 * Performs a regex pattern match of the REST return.
	 * @param unknown $output
	 * @param unknown $pattern
	 * @return string
	 */
	protected function parse_output($output, $pattern)
	{
		$ticket = FALSE;
		$value = FALSE;
		preg_match($pattern, $output, $ticket);

		if(is_array($ticket))
		{
			$value = array_shift($ticket);
		}

		return trim($value);
	}

	/**
	 * Using the TGT, get the service ticket.
	 * Step 2 of CAS REST
	 * @param unknown $tgt
	 */
	public function request_st($tgt)
	{
		$fields = array('service' => $this->get_service_url());
		$url = $this->get_cas_rest_url();
		$output = $this->curl($fields, $url . '/' .$tgt);
		$st = $this->parse_output($output, '/ST-?([0-9A-Za-z-.]*)/');

		if($st)
			return $st;
		else
			throw new AuthenticationException("Service Ticket Was False. Header Output: " . $this->parse_error($output));
	}

	protected function parse_error($output)
	{
		$lines = preg_split('/\r\n|\r|\n/', $output, 2);
		return $lines[0];
	}



	/**
	 * Returns the XML response.
	 * Step 3 of CAS REST
	 * @param unknown $st service ticket from step 2
	 * @return mixed string with full XML from CAS
	 */
	public function request_xml($st)
	{
		$service = urlencode($this->get_service_url());
		$url = $this->get_cas_service_url();

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url . "?service=$service&ticket=$st");

		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$this->set_ssl($ch);
		$output = curl_exec($ch);

		curl_close($ch);

		return $output;
	}



	/**
	 * Helper function to set the cURL SSL settings.
	 * @param unknown $ch
	 */
	protected function set_ssl(&$ch)
	{
		$cas_cert = $this->cas_cert;
		$cas_local = $this->cas_local;

		if ($cas_cert != FALSE && $cas_local == FALSE)
		{
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, TRUE);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
			curl_setopt($ch, CURLOPT_CAINFO, $cas_cert);
		}
		else
		{
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);//This should be set to one, so it only verifies based on name, however setting was removed in php 5.3+
		}
	}


	public function parse_attributes($xml)
	{
		$serviceResponse = new \SimpleXMLElement($xml, 0, FALSE, 'cas', TRUE);
		$attributes = (array)$serviceResponse->authenticationSuccess->attributes;

		if(!empty($attributes) && is_array($attributes))
			return $attributes;
		elseif($serviceResponse->authenticationFailure)
		{
			throw new AuthenticationException('XML Authentication Failure: ' . $serviceResponse->authenticationFailure);
		}
		else
		{
			throw new AuthenticationException('XML Authentication Failure.');
		}

	}

	/**
	 * Given a username and password, this method returns a boolean of whether or not the user is authenticated.
	 * If an error occurs, the message is stored in the $error_message of the CAS object.  
	 * @param $cas_username CAS username to authenticate with
	 * @param $cas_password CAS password to authenticate with
	 * @param $attributes Change to TRUE to return attributes array instead of boolean. Default FALSE.
	 
	 */
	public function authenticate($cas_username, $cas_password, $return_attributes = FALSE)
	{
		try
		{	
		    $tgt = $this->request_tgt($cas_username, $cas_password);
		    $st = $this->request_st($tgt);
		    $xml = $this->request_xml($st);

		    $attributes = $this->parse_attributes($xml);

		}
		catch(AuthenticationException $e)
		{
			//Possibly return error message to help debug.
			$this->error_message = $e->getMessage();
			return FALSE;
		}

		if(empty($attributes))
		{
			return FALSE;
		}
		elseif(is_array($attributes) && count($attributes) > 0)
		{
			if($return_attributes)
				return $attributes;
			else
				return TRUE;
		}
		else
		{
			return FALSE;
		}
	}

	/**
	 * Gets the service URL that's being passed to CAS.
	 * @return The service URL. Reads cas_local_service from settings.php
	 */
	public function get_service_url()
	{
		if($this->cas_local != FALSE)
			return $this->cas_local;
		else
			return $this->service_url;
	}

	/**
	 * Get's the CAS REST URL, throws an exception if it's not properly set.
	 * @throws Exception
	 * @return The
	 */
	public function get_cas_rest_url()
	{
		return $this->cas_rest_url;
	}


	/**
	 * Gets the CAS SERVICE URL from settings.php
	 * @throws Exception if service_url is not defined in settings.php
	 * @return The service URL
	 */
	public function get_cas_service_url()
	{
		return $this->cas_service_url;
	}

	/**
	 * Determine the source of the user from the attributes returend by CAS authentication.
	 * @param attributes is the attributes returned by authenticate.
	 * @return mixed FALSE if source can not be determined, string 'LDS' if source LDS, 'AD' if source is 'AD'
	 */
	public function determine_source($attributes)
	{

	  $lds_position = stripos($attributes['DN'], $this->source_dn);
	  $ad_position = stripos($attributes['DN'], $this->base_dn);

	  //This checks through the return value of the attributes, and determines where the source is.
	  //Strict operators are required because position can be 0, which can be considered false by ==
	  if($lds_position === FALSE && $ad_position === FALSE)
	    return FALSE;
	  elseif($lds_position >= 0 && $lds_position !== FALSE)
	    return 'LDS';
	  elseif($ad_position >= 0 && $ad_position !== FALSE)
	    return 'AD';
	  else
	    return FALSE;
	}

}
