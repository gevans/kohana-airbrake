<?php defined('SYSPATH') OR die('No direct script access.');
/**
 * @package  Airbrake
 */
class Kohana_Airbrake {

	// Airbrake version
	const VERSION = '0.1.0';

	// Airbrake API version to use
	const API_VERSION = '2.2';

	// Notifier constants
	const NOTIFIER_NAME = 'Kohana Airbrake Notifier';
	const NOTIFIER_URL  = 'https://github.com/gevans/kohana-airbrake';
	const LOG_PREFIX    = '** [Airbrake] ';

	/**
	 * @var  object  [Airbrake_Config]
	 */
	public static $config;

	/**
	 * @var  boolean  [Airbrake::init] has been called?
	 */
	protected static $_init = FALSE;

	/**
	 * @return  void
	 */
	public static function init()
	{
		if (Airbrake::$_init)
		{
			// Airbrake is already initialized
			return;
		}

		// Airbrake is now initialized
		Airbrake::$_init = TRUE;

		// Retrieve configuration
		Airbrake::$config = new Airbrake_Config(Kohana::$config->load('airbrake'));
	}

	public static function sender(array $options = array())
	{
		return new Airbrake_Sender(Airbrake::$config->merge($options));
	}

	/**
	 * Sends an exception manually using this method, even when you are not in
	 * a controller.
	 *
	 * @param   array|object  $exception  The exception to notify
	 * @param   array         $options    Overridden configuration and options
	 * @return  integer|boolean  `error_id` if successful, `FALSE` otherwise
	 */
	public static function notify($exception, array $options = array())
	{
		return Airbrake::send_notice(Airbrake::build_notice_for($exception, $options));
	}

	/**
	 * Sends the notice unless it is one of the default ignored exceptions.
	 *
	 * @param   array|object  $exception  The exception to notify
	 * @param   array         $options    Overridden configuration and options
	 * @return  integer|boolean  `error_id` if successful, `FALSE` if failure, `NULL` if ignored
	 */
	public static function notify_or_ignore($exception, array $options = array())
	{
		$notice = Airbrake::build_notice_for($exception, $options);
		return ($notice->ignored()) ? NULL : Airbrake::send_notice($notice);
	}

	/**
	 * Helper method that allows quick embedding of Airbrake for use with
	 * Javascript notifications and exception handling.
	 *
	 * @return  string  Airbrake embed HTML
	 */
	public static function javascript_notifier(array $options = array())
	{
		if ( ! Airbrake::$config->is_public())
		{
			return;
		}

		$default_options = array(
			'host'            => Airbrake::$config->host,
			'api_key'         => Airbrake::$config->api_key,
			'environment'     => Airbrake::$config->environment_name,
			'action_name'     => Request::$current->action(),
			'controller_name' => Request::$current->controller(),
			'url'             => URL::site(Request::$current->detect_uri(), Request::$current).URL::query(),
		);

		return View::factory('airbrake/javascript_notifier', Arr::merge($default_options, $options))->render();
	}

	protected static function send_notice(Airbrake_Notice $notice)
	{
		if (Airbrake::$config->is_public())
		{
			return Airbrake::sender()->send_to_airbrake($notice->as_xml());
		}

		// Skipped sending in local environment
		return NULL;
	}

	protected static function build_notice_for($exception, array $options = array())
	{
		if ($exception instanceof Exception)
		{
			$options['exception'] = $exception;
		}
		elseif (Arr::is_array($exception))
		{
			$options = Arr::merge($options, $exception);
		}
		else
		{
			throw new Airbrake_Exception('Exceptions must be passed as an array or instance of the Exception class');
		}

		return new Airbrake_Notice(Airbrake::$config->merge($options));
	}

	public static function report_environment_info()
	{
		Airbrake::write_verbose_log('Environment Info: '.Airbrake::environment_info());
	}

	public static function environment_info()
	{
		return '[PHP: '.phpversion().'] ['.Airbrake::$config->framework.'] [Env: '.Airbrake::$config->environment_name.']';
	}

	public static function report_response_body($response)
	{
		Airbrake::write_verbose_log("Response from Airbrake: \n".$response);
	}

	public static function write_verbose_log($message)
	{
		Kohana::$log->add(Log::INFO, $message);
	}

} // End Airbrake