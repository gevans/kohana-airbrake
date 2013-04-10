<?php defined('SYSPATH') OR die('No direct script access.');
/**
 * Handles backtrace parsing line by line.
 *
 * @package   Airbrake
 * @category  Backtrace
 */
class Kohana_Airbrake_Backtrace_Line {

	/**
	 * @var  string  The file portion of the line (such as `application/classes/model/user.php`)
	 */
	protected $file;

	/**
	 * @var  integer  The line number portion of the line
	 */
	protected $number;

	/**
	 * @var  string  The method of the line (such as index)
	 */
	protected $method;

	/**
	 * Parses a single line of a given backtrace.
	 *
	 * @param   array   $unparsed_line   The raw line from `caller` or some backtrace
	 * @return  Airbrake_Backtrace_Line  The parsed backtrace line
	 */
	public static function parse(array $unparsed_line)
	{
		// Extract the line parameters, adding required variables
		extract($unparsed_line + array(
			'file'     => '',
			'line'     => '',
			'function' => '',
		));

		return new Airbrake_Backtrace_Line($file, $line, $function);
	}

	/**
	 * Instantiates a new backtrace line from a given filename, line number, and
	 * method name.
	 *
	 * @param  string   $file    The filename in the given backtrace line
	 * @param  integer  $number  The line number of the file
	 * @param  string   $method  The method referenced in the given backtrace line
	 */
	public function __construct($file, $number, $method)
	{
		if (empty($file))
		{
			// The filename could not be determined
			$this->file = '{'.__('PHP internal call').'}';
		}
		else
		{
			// Replace starting paths with constants (e.g. APPPATH)
			$this->file = Debug::path($file);
		}

		$this->number = $number;
		$this->method = $method;
	}

	/**
	 * Provides read-only access to the file, line number, and method of the
	 * backtrace line.
	 *
	 * @param   string  $attr   `file`, `number`, or `method`
	 * @return  string|integer  The requested property
	 */
	public function __get($attr)
	{
		if (in_array($attr, array('file', 'number', 'method')))
		{
			return $this->$attr;
		}
		else
		{
			throw new Kohana_Exception('The :property property does not exist in the Airbrake_Backtrace_Line class',
				array(':property' => $attr));
		}
	}

	/**
	 * Formats the backtrace line as a string.
	 *
	 *     echo $line; // => "DOCROOT/index.php:109:in `execute'"
	 *
	 * @return  string  The backtrace line
	 */
	public function __toString()
	{
		return $this->file.':'.$this->number.':in `'.$this->method."'";
	}

	/**
	 * Formats the backtrace line as an array.
	 *
	 * @return  array  The backtrace line
	 */
	public function as_array()
	{
		return array(
			'file'   => $this->file,
			'number' => $this->number,
			'method' => $this->method,
		);
	}

	/**
	 * Builds a new XML document or appends the backtrace line to a provided
	 * SimpleXML element for sending to the Airbrake notifier.
	 *
	 * @param   SimpleXMLElement  $backtrace  The backtrace element
	 * @return  SimpleXMLElement  The backtrace element with the current line appended
	 */
	public function as_xml(SimpleXMLElement $backtrace = NULL)
	{
		if ($backtrace === NULL)
		{
			$backtrace = new SimpleXMLElement('<backtrace />');
		}

		$line = $backtrace->addChild('line');
		$line->addAttribute('file', $this->file);
		$line->addAttribute('number', $this->number);
		$line->addAttribute('method', $this->method);

		return $backtrace;
	}

} // End Airbrake_Backtrace_Line