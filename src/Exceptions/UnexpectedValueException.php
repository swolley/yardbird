<?php
namespace Swolley\YardBird\Exceptions;

class UnexpectedValueException extends \UnexpectedValueException
{ 
	public function __construct(string $message, $code = 0, \Exception $previous = null)
	{
		parent::__construct($message, is_numeric($code) ? $code : 0, $previous);
		$this->code = $code;
	}
}