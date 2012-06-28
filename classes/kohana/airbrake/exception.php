<?php defined('SYSPATH') OR die('No direct script access.');
/**
 * Airbrake exception class and exception handler.
 *
 * @package  Airbrake
 */
class Kohana_Airbrake_Exception extends Kohana_Kohana_Exception {

	/**
	 * Exception handler, sends exceptions to Airbrake and passes exceptions to
	 * [Kohana_Kohana_Exception::_handler] if [Kohana::$errors] is `TRUE`.
	 *
	 * @uses    Airbrake::notify_or_ignore
	 * @param   Exception  $e
	 * @return  boolean
	 */
	public static function _handler(Exception $e)
	{
		try
		{
			// Attempt to send this exception to Airbrake.
			Airbrake::notify_or_ignore($e);
		}
		catch (Exception $e2)
		{
			// It would seem we are in a serious pickle. Now we have *two*
			// exceptions to log.
			Kohana_Exception::log($e2);
		}

		// Pass the exception back to the default handler and return the
		// response if Kohana is configured for error handling.
		if (Kohana::$errors)
		{
			return parent::_handler($e);
		}

		try
		{
			// Otherwise, return an empty error response.
			// TODO: allow customization for error views and content types
			$response = Response::factory();

			// Set the response status
			$response->status(($e instanceof HTTP_Exception) ? $e->getCode() : 500);

			// Set the response headers
			$response->headers('Content-Type', Kohana_Exception::$error_view_content_type.'; charset='.Kohana::$charset);
		}
		catch (Exception $e)
		{
			// This sucks...
			$response = Response::factory();
			$response->status(500);
			$response->headers('Content-Type', 'text/plain');
		}

		return $response;
	}

}