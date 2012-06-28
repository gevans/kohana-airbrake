<?php defined('SYSPATH') OR die('No direct script access.');
/**
 * Airbrake notice class. Builds Airbrake notices from exceptions, with helpers
 * for conversion to XML for submission to the notifier API.
 *
 * @package   Airbrake
 * @category  Notification
 */
class Kohana_Airbrake_Notice {

	/**
	 * @var  Exception  The exception that caused the notice, if any
	 */
	protected $exception;

	/**
	 * @var  string  The API key for the project to which this notice should be sent
	 */
	protected $api_key;

	/**
	 * @var  Airbrake_Backtrace  The backtrace from the given exception or hash
	 */
	protected $backtrace;

	/**
	 * @var  The name of the class of error (e.g. [View_Exception])
	 */
	protected $error_class;

	/**
	 * @var  The name of the server environment (e.g. `production`)
	 */
	protected $environment_name;

	/**
	 * @var  CGI variables such as `HTTP_METHOD`
	 */
	protected $cgi_data = array();

	/**
	 * @var  string  The message from the exception, or a general description of the error
	 */
	protected $error_message;

	/**
	 * @var  array  Backtrace filters (see [Airbrake_Config::$backtrace_filters])
	 */
	protected $backtrace_filters = array();

	/**
	 * @var  array  CGI data filters (see [Airbrake_Config::$cgi_data_filters])
	 */
	protected $cgi_data_filters = array();

	/**
	 * @var  array  Parameter filters (see [Airbrake_Config::$params_filters])
	 */
	protected $params_filters = array();

	/**
	 * @var  array  Parameters from the query, request, and post body
	 */
	protected $parameters = array();

	/**
	 * @var  string  The component (if any) which was used in this request (usually the controller)
	 */
	protected $component;

	/**
	 * @var  string  The action (if any) that was called in this request
	 */
	protected $action;

	/**
	 * @var  array  An array of session data from the request
	 */
	protected $session_data;

	/**
	 * @var  string  The path to the project that caused the error (usually `DOCROOT`)
	 */
	protected $project_root;

	/**
	 * @var  string  The URL at which the error occured (if any)
	 */
	protected $url;

	/**
	 * @var  array  See [Airbrake_Config::$ignore]
	 */
	protected $ignore = array();

	/**
	 * @var  array  See [Airbrake_Config::$ignore_by_filters]
	 */
	protected $ignore_by_filters = array();

	/**
	 * @var  string  The name of the notifier library sending this notice, such as "Kohana Airbrake Notifier"
	 */
	protected $notifier_name = Airbrake::NOTIFIER_NAME;

	/**
	 * @var  string  The version number of the notifier library sending this notice such as "2.1.3"
	 */
	protected $notifier_version = Airbrake::VERSION;

	/**
	 * @var  string  A URL for more information about the notifier library sending this notice
	 */
	protected $notifier_url = Airbrake::NOTIFIER_URL;

	/**
	 * @var  string  The host name this error occured (if any)
	 */
	protected $hostname;

	public function __construct(array $options = array())
	{
		if ($request = Request::$current)
		{
			// Set defaults from current request
			$this->url       = URL::site($request->detect_uri(), $request).htmlspecialchars(URL::query());
			$this->component = $request->controller();
			$this->action    = $request->action();

			$this->find_parameters($request);
			$this->find_cgi_data($request);
			$this->find_session_data();
		}

		$this->hostname = $this->local_hostname();

		foreach ($options as $option => $value)
		{
			$this->$option = $value;
		}

		$this->parameters = Airbrake_Filter::params($this->parameters, $this->params_filters);
		$this->cgi_data   = Airbrake_Filter::environment($this->cgi_data, $this->cgi_data_filters);

		if (( ! $this->backtrace OR ! $this->error_class OR ! $this->error_message) AND $this->exception)
		{
			$this->backtrace     = Airbrake_Backtrace::parse($this->exception->getTrace(), array(
				'filters' => $this->backtrace_filters));
			$this->error_class   = get_class($this->exception);
			$this->error_message = Kohana_Exception::text($this->exception);
		}
	}

	public function ignored()
	{
		if (in_array($this->error_class, $this->ignore))
		{
			return TRUE;
		}

		foreach ($this->ignore_by_filters as $filter)
		{
			if (call_user_func($filter, $this))
			{
				return TRUE;
			}
		}

		return FALSE;
	}

	public function as_xml()
	{
		// Create document with API version and API key
		$notice = new SimpleXMLElement('<?xml version="1.0"?><notice />');
		$notice->addAttribute('version', Airbrake::API_VERSION);
		$notice->addChild('api-key', $this->api_key);

		// Attach notifier information
		$notifier = $notice->addChild('notifier');
		$notifier->addChild('name', $this->notifier_name);
		$notifier->addChild('version', $this->notifier_version);
		$notifier->addChild('url', $this->notifier_url);

		// Attach error details and backtrace
		$error = $notice->addChild('error');
		$error->addChild('class', $this->error_class);
		$error->addChild('message', $this->error_message);
		$this->backtrace->as_xml($error);

		// Attach environment and request variables
		if ($this->url OR $this->component OR $this->action)
		{
			$request = $notice->addChild('request');
			$request->addChild('url', $this->url);
			$request->addChild('component', $this->component);
			$request->addChild('action', $this->action);

			if ($this->parameters !== NULL AND ! empty($this->parameters))
			{
				// Attach parameters
				$this->xml_vars_for($request->addChild('params'), $this->parameters);
			}

			if ($this->session_data !== NULL AND ! empty($this->session_data))
			{
				// Attach session data
				$this->xml_vars_for($request->addChild('session'), $this->session_data);
			}

			if ($this->cgi_data !== NULL AND ! empty($this->cgi_data))
			{
				// Attach environment data
				$this->xml_vars_for($request->addChild('cgi-data'), $this->cgi_data);
			}
		}

		// Attach server environment details
		$server = $notice->addChild('server-environment');
		$server->addChild('project-root', $this->project_root);
		$server->addChild('environment-name', $this->environment_name);
		$server->addChild('hostname', $this->hostname);

		return $notice->asXML();
	}

	/**
	 * Returns an array of all properties in the notice.
	 *
	 * @return  array
	 */
	public function as_array()
	{
		return get_object_vars($this);
	}

	public function __get($attr)
	{
		if (property_exists($this, $attr))
		{
			return $this->$attr;
		}
		else
		{
			throw new Kohana_Exception('The :property property does not exist in the Airbrake_Notice class',
				array(':property' => $attr));
		}
	}

	protected function find_parameters(Request $request)
	{
		$parameters = array();

		if ($get = $request->query() AND ! empty($get))
		{
			$parameters = Arr::merge($parameters, $get);
		}

		if ($params = $request->param() AND ! empty($params))
		{
			$parameters = Arr::merge($parameters, $params);
		}

		if ($post = $request->post() AND ! empty($post))
		{
			$parameters = Arr::merge($parameters, $post);
		}

		$this->parameters = $parameters;
	}

	protected function find_cgi_data(Request $request)
	{
		$cgi_data = array();

		if (isset($_ENV) AND ! empty($_ENV))
		{
			$cgi_data = Arr::merge($cgi_data, $_ENV);
		}

		if (isset($_SERVER) AND ! empty($_SERVER))
		{
			$cgi_data = Arr::merge($cgi_data, $_SERVER);
		}

		$this->cgi_data = $cgi_data;
	}

	protected function find_session_data()
	{
		$this->session_data = (isset($_SESSION) AND ! empty($_SESSION)) ?
			$_SESSION : Session::instance()->as_array();
	}

	protected function xml_vars_for(SimpleXMLElement $element, $vars)
	{
		// Filter variables and convert special characters to HTML entities
		$vars = Airbrake_Filter::sanitize_data($vars);

		foreach ($vars as $key => $value)
		{
			if (Arr::is_array($value))
			{
				if (is_object($value) AND method_exists($value, 'as_array'))
				{
					$value = $value->as_array();
				}

				$var = $element->addChild('var');
				$var->addAttribute('key', $key);
				$this->xml_vars_for($var, $value);
			}
			elseif (is_object($value))
			{
				// Are you freakin' kidding me?!
				// TODO: Possibly throw an exception or ignore the item instead?
				$element
					->addChild('var', serialize($value))
					->addAttribute('key', $key);
			}
			else
			{
				$element
					->addChild('var', (string) $value)
					->addAttribute('key', $key);
			}
		}
	}

	protected function local_hostname()
	{
		if (version_compare(PHP_VERSION, '5.3.0') >= 0)
		{
			if ($hostname = gethostname())
			{
				return $hostname;
			}
		}

		return php_uname('n');
	}

}