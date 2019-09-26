<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Swolley\YardBird\Connection;
use Swolley\YardBird\Interfaces\IConnectable;
use Swolley\YardBird\Exceptions\QueryException;
use Swolley\YardBird\Exceptions\BadMethodCallException;
use Swolley\YardBird\Exceptions\UnexpectedValueException;
use Swolley\YardBird\Drivers\MySqliExtended;
use Swolley\YardBird\Drivers\PDOExtended;

final class ConnectionTest extends TestCase
{
	///////////////////////////////// UNIT ////////////////////////////////////////////////
	public function test_checkExtension_should_return_exception_if_empty_string_is_passed(): void
  	{
		$this->expectException(BadMethodCallException::class);
		$reflection = new \ReflectionClass(get_class(new Connection));
		$method = $reflection->getMethod('checkExtension');
		$method->setAccessible(true);

		$method->invokeArgs($reflection, ['']);
	}
	
	public function test_checkExtension_should_return_null_if_not_in_list_driver(): void
    {
		$reflection = new \ReflectionClass(get_class(new Connection));
		$method = $reflection->getMethod('checkExtension');
		$method->setAccessible(true);
		
        $this->assertEquals(null, $method->invokeArgs($reflection, ['invalid']));
	}
	
	public function test_invoke_class_should_return_exception_if_empty_array_passed(): void
    {
		$this->expectException(BadMethodCallException::class);
		(new Connection)([]);
	}

	public function test_invoke_class_should_return_exception_if_no_supported_driver_found(): void
    {
		$this->expectException(\Exception::class);
		(new Connection)(['driver' => 'invalid']);
	}
}