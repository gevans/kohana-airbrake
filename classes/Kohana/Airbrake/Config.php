<?php defined('SYSPATH') OR die('No direct script access.');
/**
 * Airbrake notifier configuration class.
 *
 * @package   Airbrake
 * @category  Configuration
 */
class Kohana_Airbrake_Config {

	/**
	 * @var  string  The API key for your project, found on the project edit form
	 */
	public $api_key;

	/**
	 * @var  string  The host to connect to (defaults to `api.airbrake.io`)
	 */
	public $host = 'api.airbrake.io';

	/**
	 * @var  integer  The port on which your Airbrake server runs (defaults to `443` for secure connections, `80` for insecure connections)
	 */
	public $port;

	/**
	 * @var  boolean  `TRUE` for https connections, `FALSE` for http connections
	 */
	public $secure = FALSE;

	/**
	 * @var  integer  The HTTP open timeout in seconds (defaults to `2`)
	 */
	public $http_open_timeout = 2;

	/**
	 * @var  integer  The HTTP read timeout in seconds (defaults to `5`)
	 */
	public $http_read_timeout = 5;

	/**
	 * @var  string  The hostname of your proxy server (if using a proxy)
	 */
	public $proxy_host;

	/**
	 * @var  integer  The port of your proxy server (if using a proxy)
	 */
	public $proxy_port;

	/**
	 * @var  string  The username to use when logging into your proxy server (if using a proxy)
	 */
	public $proxy_user;

	/**
	 * @var  string  The password to use hwen logging into your proxy server (if using a proxy)
	 */
	public $proxy_pass;

	/**
	 * @var  array  A list of filters for cleaning and pruning the backtrace. See [Airbrake_Config::filter_backtrace]
	 */
	public $backtrace_filters = array();

	/**
	 * @var  array  A list of parameters that should be filtered out of what is sent to Airbrake. By default, all `password` and `password_confirmation` attributes will have their contents replaced
	 */
	public $params_filters = array();

	/**
	 * @var  array  array  A list of environment variables that should be filtered out of what is sent to Airbrake (e.g. `$_SERVER['DATABASE_URL']` if you use this to pass configuration settings)
	 */
	public $cgi_data_filters = array();

	/**
	 * @var  array  A list of filters for ignoring exceptions. See [Airbrake_Config::ignore_by_filter]
	 */
	public $ignore_by_filters = array();

	/**
	 * @var  array  A list of exception classes to ignore
	 */
	public $ignore = array();

	/**
	 * @var  array  A list of user agents to ignore
	 */
	public $ignore_user_agents = array();

	/**
	 * @var  array  A list of environments in which notifications should not be sent
	 */
	public $development_environments = array(
		'development', 'testing',
	);

	/**
	 * @var  boolean  `TRUE` if you want to check for production errors matching development errors, `FALSE` otherwise
	 */
	public $development_lookup = TRUE;

	/**
	 * @var  string  The name of the environment the application is running in
	 */
	public $environment_name;

	/**
	 * @var  string  The path to the project in which the error occursed, such as `DOCROOT`
	 */
	public $project_root = DOCROOT;

	/**
	 * @var  string  The name of the notifier library being used to send notifications
	 */
	public $notifier_name = Airbrake::NOTIFIER_NAME;

	/**
	 * @var  string  The version of the notifier library being used to send notifications
	 */
	public $notifier_version = Airbrake::VERSION;

	/**
	 * @var  string  The url of the notifier library being used to send notifications
	 */
	public $notifier_url = Airbrake::NOTIFIER_URL;

	/**
	 * @var  string  The text that the placeholder is replaced with. `{{error_id}}` is the actual error number.
	 */
	public $user_information = 'Airbrake Error {{error_id}}';

	/**
	 * @var  string  The framework Airbrake is configured to use
	 */
	public $framework = 'Kohana';

	/**
	 * @var  array  Default filtered environment variables
	 */
	public static $default_cgi_data_filters = array(
		'DATABASE_URL', 'HTTP_AUTHORIZATION', 'PHP_AUTH_USER', 'PHP_AUTH_PW',
	);

	/**
	 * @var  array  Default filtered parameters
	 */
	public static $default_params_filters = array(
		'password', 'password_confirmation',
	);

	/**
	 * @var  array  Default backtrace filters
	 */
	public static $default_backtrace_filters = array();

	/**
	 * @var  array  Default ignored classes
	 */
	public static $default_ignore = array(
		'HTTP_Exception_404',
	);

	/**
	 * Instantiates a new configuration object, applies user-specified options,
	 * and sets defaults.
	 *
	 * @param  array  $config  User-specified configuration settings
	 */
	public function __construct($config = array())
	{
		// Find all constants in the Kohana class
		$reflection = new ReflectionClass('Kohana');
		$constants  = $reflection->getConstants();

		// Set the constant name for the current environment
		$this->environment_name = strtolower(array_search(Kohana::$environment, $constants, TRUE));

		// Set user-specified configuration
		foreach ($config as $item => $value)
		{
			$this->$item = $value;
		}

		// Merge in configured defaults
		$this->cgi_data_filters  = Arr::merge($this->cgi_data_filters, Airbrake_Config::$default_cgi_data_filters);
		$this->params_filters    = Arr::merge($this->params_filters, Airbrake_Config::$default_params_filters);
		$this->backtrace_filters = Arr::merge($this->backtrace_filters, Airbrake_Config::$default_backtrace_filters);
		$this->ignore            = Arr::merge($this->ignore, Airbrake_Config::$default_ignore);

		if ( ! $this->api_key)
		{
			throw new Airbrake_Exception('Airbrake cannot send notifications without a configured API key');
		}

		if ( ! $this->port)
		{
			$this->port = $this->default_port();
		}
	}

	/**
	 * Takes a callback and adds it to the list of backtrace filters. When the
	 * filters run, the callback will be handed each line of the backtrace and
	 * can modify it as necessary.
	 *
	 *     // Callback style
	 *     Airbrake::$config->filter_backtrace('strrev');
	 *
	 *     // Closure style
	 *     Airbrake::$config->filter_backtrace(function($line) {
	 *         return preg_replace('/^'.APPPATH.'/', '[APPPATH]', $line);
	 *     });
	 *
	 * @param   callback  $filter  The new backtrace filter
	 * @return  void
	 */
	public function filter_backtrace($filter)
	{
		$this->backtrace_filters[] = $filter;
	}

	/**
	 * Takes a callback and adds it to the list of ignore filters. When the
	 * filters run, the callback will be handed the exception.
	 *
	 *     // Callback style
	 *     Airbrake::$config->ignore_by_filter('Some_Class::some_method');
	 *
	 *     // Closure style
	 *     Airbrake::$config->ignore_by_filter(function ($exception_data) {
	 *         if ($exception_data['error_class'] == 'ORM_Validation_Exception')
	 *         {
	 *             return TRUE;
	 *         }
	 *     });
	 *
	 * [!!] If the callback returns `TRUE` the exception will be ignored,
	 * otherwise it will be processed by Airbrake.
	 *
	 * @param   callback  $filter  The new ignore filter
	 * @return  void
	 */
	public function ignore_by_filter($filter)
	{
		$this->ignore_by_filters[] = $filter;
	}

	/**
	 * Overrides the list of ignored errors.
	 *
	 * @param   array  $names  A list of exceptions to ignore
	 * @return  void
	 */
	public function ignore_only(array $names = array())
	{
		$this->ignore = $names;
	}

	/**
	 * Overrides the list of default ignored user agents.
	 *
	 * @param  array  $names  A list of user agents to ignore
	 */
	public function ignore_user_agents_only(array $names = array())
	{
		$this->ignore_user_agents = array();
	}

	/**
	 * Returns an array of all configurable options.
	 *
	 * @return  array
	 */
	public function as_array()
	{
		return get_object_vars($this);
	}

	/**
	 * Returns an array of all configurable options merged with `$config`.
	 * @param   array  $config  Options to merge with configuration
	 * @return  array
	 */
	public function merge(array $config = array())
	{
		return Arr::merge($this->as_array(), $config);
	}

	/**
	 * Determines if the notifier will send notices.
	 *
	 * @return  boolean  `FALSE` if in a development environment
	 */
	public function is_public()
	{
		return ( ! in_array($this->environment_name, $this->development_environments));
	}

	/**
	 * Determines a default port, depending on whether a secure connection is
	 * configured.
	 *
	 * @return  integer  Default port
	 */
	protected function default_port()
	{
		return ($this->secure) ? 443 : 80;
	}

} // End Airbrake_Config