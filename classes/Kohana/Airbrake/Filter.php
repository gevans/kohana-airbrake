<?php defined('SYSPATH') OR die('No direct script access.');
/**
 * Airbrake filter class. Handles filtering of parameters and environment data,
 * stripping sensitive information, and translating special characters into
 * XML-safe strings.
 *
 * @package   Airbrake
 * @category  Configuration
 */
class Kohana_Airbrake_Filter {

	/**
	 * Applies a list of callbacks to an array of data, using
	 * [array_map](php.net/manual/en/function.array-map.php), or [Arr::map]
	 * if `$recursive` is `TRUE`.
	 *
	 * @param   array    $callbacks  List of callbacks to run
	 * @param   array    $data       List of data to filter
	 * @param   boolean  $recursive  `TRUE` will apply filters recursively
	 * @return  array    Filtered data
	 */
	public static function run_callbacks(array $callbacks = array(), array $data = array(), $recursive = FALSE)
	{
		if (empty($callbacks) OR empty($data))
		{
			// There's no sense in filtering nothing.
			return $data;
		}

		foreach ($callbacks as $callback)
		{
			$data = ($recursive) ? Arr::map($callback, $data) : array_map($callback, $data);
		}

		return $data;
	}

	/**
	 * Replaces the specified params with `[FILTERED]` and filters potential
	 * credit card data using [Airbrake_Filter::credit_cards].
	 *
	 * @param   array  $params  Unfiltered parameters
	 * @param   array  $keys    List of keys to replace
	 * @return  array  Filtered parameters
	 * @uses    Airbrake_Filter::credit_cards
	 */
	public static function params(array $params = array(), array $keys = array())
	{
		$params = Airbrake_Filter::keys($params, $keys);

		return Arr::map('Airbrake_Filter::credit_cards', $params);
	}

	/**
	 * Replaces the specified environment variables with `[FILTERED]`.
	 *
	 * @param   array  $params  Unfiltered environment variables
	 * @param   array  $keys    List of keys to replace
	 * @return  array  Filtered parameters
	 */
	public static function environment(array $cgi_data = array(), array $keys = array())
	{
		return Airbrake_Filter::keys($cgi_data, $keys);
	}

	/**
	 * Replaces the specified keys from provided array of `$data` with
	 * `[FILTERED]` for each occurance.
	 *
	 *     $data = array('sensitive' => 'foo', 'bar' => 'baz');
	 *     Airbrake_Filter::keys($data, array('sensitive')); // => array('sensitive' => '[FILTERED]', 'bar' => 'baz')
	 *
	 * @param   array  $data  Data to filter
	 * @param   array  $keys  Keys to remove
	 * @return  array  Filtered data
	 */
	public static function keys(array $data = array(), array $keys = array())
	{
		foreach ($keys as $key)
		{
			unset($data[$key]);
		}

		return $data;
	}

	/**
	 * Scans provided data for valid credit cards and replaces with `[FILTERED]`.
	 *
	 * [!!] This method is an additonal measure to protect customer data, in the
	 * event a user enters their number into the wrong form field, or a field is
	 * mistyped in the params filters. **Do not rely on this.** Ensure you use
	 * [Airbrake_Config::$params_filters] to properly filter your sensitive data.
	 *
	 * @param   string  $value  Potential card number
	 * @return  string  Filtered card number (if found), otherwise original `$value`
	 */
	public static function credit_cards($value)
	{
		// Strip all non-digits
		$number = preg_replace('/[^\d]+/', '', $value);

		// Filter if credit card number, or return original value
		return (strlen($number) >= 12 AND Valid::luhn($number)) ? '[FILTERED]' : $value;
	}

	/**
	 * Encodes HTML entities for each key and value in the array, recursively.
	 *
	 * @param   array  $data  Data to sanitize
	 * @return  array
	 */
	public static function sanitize_data(array $data = array())
	{
		return Arr::map('htmlspecialchars', $data);
	}

}