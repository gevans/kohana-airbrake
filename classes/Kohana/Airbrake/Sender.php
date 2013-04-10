<?php defined('SYSPATH') OR die('No direct script access.');
/**
 * Airbrake notification sender. Handles connections, forming requests, and
 * parsing responses.
 *
 * @package   Airbrake
 * @category  Notification
 */
class Kohana_Airbrake_Sender {

	const NOTICES_URI = '/notifier_api/v2/notices/';

	/**
	 * @var  array  Airbrake notifier headers
	 */
	protected static $headers = array(
		'Accept'       => 'text/xml, application/xml',
		'Content-Type' => 'text/xml; charset=utf-8',
	);

	/**
	 * @var  string  The hostname of your proxy server (if using a proxy)
	 */
	protected $proxy_host;

	/**
	 * @var  integer  The port of your proxy server (if using a proxy)
	 */
	protected $proxy_port;

	/**
	 * @var  string  The username to use when logging into your proxy server (if using a proxy)
	 */
	protected $proxy_user;

	/**
	 * @var  string  The password to use hwen logging into your proxy server (if using a proxy)
	 */
	protected $proxy_pass;

	/**
	 * @var  string  The HTTP protocol to use (`https` or `http`)
	 */
	protected $protocol;

	/**
	 * @var  string  The host to connect to (defaults to `airbrake.io`)
	 */
	protected $host;

	/**
	 * @var  integer  The port on which your Airbrake server runs (defaults to `443` for secure connections, `80` for insecure connections)
	 */
	protected $port;

	/**
	 * @var  boolean  `TRUE` for https connections, `FALSE` for http connections
	 */
	protected $secure;

	/**
	 * @var  integer  The HTTP open timeout in seconds (defaults to `2`)
	 */
	protected $http_open_timeout;

	/**
	 * @var  integer  The HTTP read timeout in seconds (defaults to `5`)
	 */
	protected $http_read_timeout;

	/**
	 * @var  array  HTTP messages
	 */
	public static $messages = array(
		200 => 'OK',
		403 => 'Forbidden',
		422 => 'Unprocessable Entity',
		500 => 'Internal Server Error',
	);

	/**
	 * @var  integer  Number of retried requests
	 */
	protected static $retries = 0;

	public function __construct(array $options = array())
	{
		foreach ($options as $option => $value)
		{
			$this->$option = $value;
		}

		if ( ! $this->protocol)
		{
			$this->detect_protocol();
		}
	}

	public function __get($attr)
	{
		if (property_exists($this, $attr))
		{
			return $this->$attr;
		}
		else
		{
			throw new Kohana_Exception('The :property property does not exist in the Airbrake_Sender class',
				array(':property' => $attr));
		}
	}

	public function send_to_airbrake($data)
	{
		if (Airbrake_Sender::$retries > 1)
		{
			// Rage quit
			return FALSE;
		}

		$request = $this->build_http_request($data);
		$client  = $this->setup_http_client();

		try
		{
			$response = $client->execute($request);
		}
		catch (Request_Exception $e)
		{
			$this->log(Log::ERROR, 'Failure: Unable to contact the Airbrake server - '.
				Kohana_Exception::text($e));
			return FALSE;
		}

		if (preg_match('/^2\d{2}$/', $response->status()))
		{
			$this->log(Log::INFO, 'Success: Sent notification', $response->body());

			// Reset retries counter
			Airbrake_Sender::$retries = 0;
		}
		elseif ($response->status() === 403 AND Airbrake_Sender::$retries === 0)
		{
			// Increment retries counter
			Airbrake_Sender::$retries++;

			// Switch protocol to opposite (HTTP/HTTPS)
			$this->secure = ( ! $this->secure);
			$this->detect_protocol();

			// Retry request
			$this->send_to_airbrake($data);
		}
		else
		{
			$this->log(Log::ERROR, 'Failure: Cannot send notification', $response->body());
		}

		if (preg_match('/<error-id[^>]*>(.*?)<\/error-id>/', $response->body(), $matches))
		{
			// Return ID of reported error
			return $matches[1];
		}

		// Something broke. :(
		return FALSE;
	}

	protected function detect_protocol()
	{
		$this->protocol = ($this->secure) ? 'https' : 'http';
	}

	protected function url()
	{
		return $this->protocol.'://'.$this->host.':'.$this->port.Airbrake_Sender::NOTICES_URI;
	}

	protected function log($level, $message, $response = NULL)
	{
		Kohana::$log->add($level, Airbrake::LOG_PREFIX.$message);
		Airbrake::report_environment_info();

		if ($response !== NULL)
		{
			Airbrake::report_response_body($response);
		}
	}

	protected function build_http_request($data)
	{
		return Request::factory($this->url())
			->method(Request::POST)
			->headers(Airbrake_Sender::$headers)
			->body($data);
	}

	protected function setup_http_client()
	{
		try
		{
			$options = array(
				CURLOPT_CONNECTTIMEOUT => $this->http_open_timeout,
				CURLOPT_TIMEOUT        => $this->http_read_timeout,
				CURLOPT_USERAGENT      => $this->user_agent(),
			);

			if ($this->secure)
			{
				// Set to: 0 - no verification
				//         1 - check the existence of a common name in the SSL certificate
				//         2 - check the existence of a common name and also verify that it matches the hostname provided (default)
				$options[CURLOPT_SSL_VERIFYPEER] = 2;
			}

			if ($this->proxy_host)
			{
				$options[CURLOPT_HTTPPROXYTUNNEL] = TRUE;
				$options[CURLOPT_PROXY]           = $this->proxy_host;
				$options[CURLOPT_PROXYPORT]       = $this->proxy_port;

				if ($this->proxy_user AND $this->proxy_pass)
				{
					$options[CURLOPT_PROXYUSERPWD] = $this->proxy_user.':'.$this->proxy_pass;
				}
			}

			return Request_Client_External::factory(array('options' => $options), 'Request_Client_Curl');
		}
		catch (Exception $e)
		{
			$this->log(Log::ERROR, '[Airbrake_Sender::setup_http_client] Failure initializing the Request client. Error: [ '.$e->getCode().' ] '.$e->getMessage());

			// Rethrow exception
			throw $e;
		}
	}

	protected function user_agent()
	{
		return Airbrake::NOTIFIER_NAME.' '.Airbrake::VERSION.' - '.Airbrake::NOTIFIER_URL;
	}

} // End Airbrake_Notifier
