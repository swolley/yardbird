<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Swolley\YardBird\Utils\Utils;
use Swolley\YardBird\Exceptions\QueryException;
use Swolley\YardBird\Exceptions\BadMethodCallException;
use Swolley\YardBird\Exceptions\UnexpectedValueException;

final class UtilsTest extends TestCase
{
	///////////////////////////////// UNIT ////////////////////////////////////////////////
	public function test_trimQueryString_should_return_replaced_string(): void
	{
		$query = <<<EOT
		string
		withouth
		cr
		EOT;
		$this->assertEquals('string withouth cr', Utils::trimQueryString($query));
	}
}