<?php defined('SYSPATH') OR die('No direct script access.');
/**
 * Handles backtrace parsing for notices.
 *
 * @package   Airbrake
 * @category  Backtrace
 */
class Kohana_Airbrake_Backtrace {

	/**
	 * @var  array  Holder for an array of [Airbrake_Backtrace_Line] instances
	 */
	protected $lines = array();

	/**
	 * Parses a PHP backtrace and returns a new `Airbrake_Backtrace` object.
	 *
	 * @param   array  $backtrace   [description]
	 * @param   array  $options     [description]
	 * @return  Airbrake_Backtrace  The parsed backtrace
	 */
	public static function parse(array $backtrace, array $options = array())
	{
		// Run backtrace through provided callbacks
		$filters = Arr::get($options, 'filters', array());
		$filtered_lines = Airbrake_Filter::run_callbacks($filters, $backtrace);

		// Parse the filtered lines
		$lines = array_map('Airbrake_Backtrace_Line::parse', $filtered_lines);

		// Instantiate a new backtrace from the lines
		return new Airbrake_Backtrace($lines);
	}

	/**
	 * Instantiates a new `Airbrake_Backtrace` with the given lines.
	 *
	 * @param array $lines [description]
	 */
	public function __construct(array $lines = array())
	{
		$this->lines = $lines;
	}

	/**
	 * Provides read-only access to the backtrace's lines.
	 *
	 * @param   string  $attr   `lines`
	 * @return  string|integer  The requested property
	 */
	public function __get($attr)
	{
		if ($attr == 'lines')
		{
			return $this->lines;
		}
		else
		{
			throw new Kohana_Exception('The :property property does not exist in the Airbrake_Backtrace_Line class',
				array(':property' => $attr));
		}
	}

	/**
	 * Formats the backtrace as a string.
	 *
	 * @return  string  The backtrace as a string
	 */
	public function __toString()
	{
		$backtrace = '';

		foreach ($this->lines as $line)
		{
			$backtrace .= $line."\n";
		}

		return $backtrace;
	}

	/**
	 * Formats the backtrace as an array.
	 *
	 * @return  array  The backtrace as an array
	 */
	public function as_array()
	{
		$backtrace = array(
			'lines' => array(),
		);

		foreach ($this->lines as $line)
		{
			$backtrace['lines'][] = $line->as_array();
		}

		return $backtrace;
	}

	/**
	 * Builds a new XML document or appends the backtrace to a provided
	 * SimpleXML element for sending to the Airbrake notifier.
	 *
	 * @param   SimpleXMLElement  $error  The error element
	 * @return  SimpleXMLElement  The error element with the current line appended
	 */
	public function as_xml(SimpleXMLElement $error = NULL)
	{
		if ($error === NULL)
		{
			$error = new SimpleXMLElement('<error />');
		}

		if (empty($this->lines))
		{
			return $error;
		}

		$backtrace = $error->addChild('backtrace');

		foreach ($this->lines as $line)
		{
			// Add each line to the backtrace
			$backtrace = $line->as_xml($backtrace);
		}

		return $error;
	}

} // End Airbrake_Backtrace