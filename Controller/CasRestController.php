<?php

namespace Main\CasRestBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

class CasRestController extends Controller
{
	protected $casRestUrl;
	protected $casServiceUrl;
	protected $serviceUrl;
	protected $casCert;
	protected $casLocal;
	protected $sourceDN;
	protected $baseDN;
	protected $errorMessage;

	public function __construct($cas_rest_url, $cas_service_url, $cas_cert = FALSE, $cas_local = FALSE, $source_dn = FALSE, $base_dn = FALSE, $service_url = FALSE)
	{
		$this->casRestUrl = $cas_rest_url;
		$this->casServiceUrl = $cas_service_url;
		$this->serviceUrl = $service_url;
		$this->casCert = $cas_cert;
		$this->casLocal = $cas_local;
		$this->sourceDN = $source_dn;
		$this->baseDN = $base_dn;

		if($cas_local)//If $cas_local is set, set the $service_url
		{
		  $this->serviceUrl = $cas_local;
		}
		elseif(!$cas_local && !$service_url)//else cas_local is not set and $service_url is not set.
		{
		  if(!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443)
		    $prefix = 'https://';
		  else
		    $prefix = 'http://';

		  $this->serviceUrl = $prefix . $_SERVER['SERVER_NAME'];
		}

		$this->errorMessage = 'No error message has been set.';
	}


	/**
	 * Helper function to perform a curl post
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
	public function requestTGT($cas_username, $cas_password)
	{
		$fields = array('username' => $cas_username, 'password' => $cas_password);
		$url = $this->getCASRestUrl();

		$output = $this->curl($fields, $url);
		$tgt = $this->parseOutput($output, '/TGT-?(.*)/');

		if($tgt)
			return $tgt;
		else
			throw new AuthenticationException("Ticket Granting Ticket Was False. Header Output: " . $this->parse_error($output));

	}


	/**
	 * Helper function to find a pattern from the ticket and the REST return.
	 * @param string $output
	 * @param string $pattern
	 * @return string
	 */
	protected function parseOutput($output, $pattern)
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
	 * @param string $tgt the TGT from step one
	 * @return string a string service ticket.
	 */
	public function request_st($tgt)
	{
		$fields = array('service' => $this->getServiceUrl());
		$url = $this->getCASRestUrl();
		$output = $this->curl($fields, $url . '/' .$tgt);
		$st = $this->parseOutput($output, '/ST-?([0-9A-Za-z-.]*)/');

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
	 * @param string $st service ticket from step 2
	 * @return mixed string with full XML from CAS
	 */
	public function requestXML($st)
	{
		$service = urlencode($this->getServiceUrl());
		$url = $this->getCASServiceUrl();

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url . "?service=$service&ticket=$st");

		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$this->set_ssl($ch);
		$output = curl_exec($ch);

		curl_close($ch);

		return $output;
	}



	/**
	 * Helper function to set the cURL SSL settings. This is used for for bypassing certificate
	 * and SSL requirements for local testing. Not recommended, try to setup SSL on all environments
	 * including local.
	 * @param unknown $ch
	 */
	protected function set_ssl(&$ch)
	{
		$cas_cert = $this->casCert;
		$cas_local = $this->casLocal;

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

	/**
	 * Given the XML response from CAS, parse the XML into an array.
	 * @param $xml The XML given given from the CAS response
	 * @return array containing the attributes from the XML
	 * @throws AuthenticationException if the XML contained an authenticationFailure key.
	 */
	public function parseAttributes($xml)
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
	 * If the attributes are required, they can optionally be requested in the return attributes parameter.
	 * If an error occurs, the message is stored in the $error_message of the CAS object.
	 * @param $cas_username CAS username to authenticate with
	 * @param $cas_password CAS password to authenticate with
	 * @param bool $return_attributes Change to TRUE to return attributes array instead of boolean. Default FALSE.
	 * @return array|bool array of attributes if $return_attributes was set to TRUE, otherwise bool based on authentication success.
	 */
	public function authenticate($cas_username, $cas_password, $return_attributes = FALSE)
	{
		try
		{	
		    $tgt = $this->requestTGT($cas_username, $cas_password);
		    $st = $this->request_st($tgt);
		    $xml = $this->requestXML($st);

		    $attributes = $this->parseAttributes($xml);

		}
		catch(AuthenticationException $e)
		{
			//Possibly return error message to help debug.
			$this->errorMessage = $e->getMessage();
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
	 * @return The service URL. If $cas_local is set, will return that override instead.
	 */
	public function getServiceUrl()
	{
		if($this->casLocal != FALSE)
			return $this->casLocal;
		else
			return $this->serviceUrl;
	}

	/**
	 * Returns the cas rest url.
	 */
	public function getCASRestUrl()
	{
		return $this->casRestUrl;
	}


	/**
	 * Gets the CAS SERVICE URL
	 * @return string service URL
	 */
	public function getCASServiceUrl()
	{
		return $this->casServiceUrl;
	}

	/**
	 * Determine the source of the user from the attributes returend by CAS authentication.
	 * @param array $attributes attributes array passed on
	 * @return mixed FALSE if source can not be determined, string 'LDS' if source LDS, 'AD' if source is 'AD'
	 */
	public function determine_source($attributes)
	{
		if(!is_array($attributes))
			return FALSE;
	  $lds_position = stripos($attributes['DN'], $this->sourceDN);
	  $ad_position = stripos($attributes['DN'], $this->baseDN);

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
