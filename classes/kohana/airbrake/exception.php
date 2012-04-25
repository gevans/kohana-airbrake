<?php defined('SYSPATH') OR die('No direct script access.');
/**
 * Airbrake exception class and exception handler.
 *
 * @package  Airbrake
 */
class Kohana_Airbrake_Exception extends Kohana_Exception {

	/**
	 * @var  callback  Previously defined exception handler
	 */
	public static $previous_exception_handler;

	/**
	 * Sends exceptions to Airbrake and passes exceptions to the previously
	 * defined exception handler (e.g. [Kohana_Exception::handler]).
	 *
	 * @param   Exception  $exception  Exception object
	 * @return  void
	 */
	public static function handler(Exception $exception)
	{
		try
		{
			Airbrake::notify_or_ignore($exception);
		}
		catch (Exception $e)
		{
			// Oddness, something broke...
			Kohana::$log->add(Log::ERROR, Kohana_Exception::text($e));
		}

		if (is_callable(Airbrake_Exception::$previous_exception_handler))
		{
			// Pass the exception to the previously defined exception handler
			call_user_func(Airbrake_Exception::$previous_exception_handler, $exception);
		}
	}

}