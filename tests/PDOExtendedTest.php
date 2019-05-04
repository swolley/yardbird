<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Swolley\Database\Drivers\PDOExtended;
use Swolley\Database\Exceptions\QueryException;
use Swolley\Database\Exceptions\BadMethodCallException;
use Swolley\Database\Exceptions\UnexpectedValueException;

final class PDOExtendedTest extends TestCase
{
	///////////////////////////////// CONNECTION ////////////////////////////////////////////////
	public function test_validateConnectionParams_should_return_exception_if_no_valid_parameters_passed(): void
    {
		$this->expectException(BadMethodCallException::class);
		new PDOExtended([
			'driver' => 'mysql',
			'host' => '',
			'user' => null,
			'password' => null
		]);
	}

	public function test_validateConnectionParams_should_return_exception_if_missing_parameters(): void
    {
		$this->expectException(BadMethodCallException::class);
		new PDOExtended([
			'driver' => 'mysql'
		]);
	}

	public function test_validateConnectionParams_should_return_exception_if_driver_not_oci_and_dbName_empty(): void
    {
		$this->expectException(UnexpectedValueException::class);
		new PDOExtended([
			'driver' => 'mysql',
			'dbName' => '',
			'host' => '127.0.0.1',
			'user' => 'username',
			'password' => 'userpassword'
		]);
	}

	public function test_validateConnectionParams_should_return_exception_if_driver_is_oci_and_sid_or_serviceName_not_valid(): void
    {
		$this->expectException(UnexpectedValueException::class);
		new PDOExtended([
			'driver' => 'oci',
			'dbName' => '',
			'host' => '127.0.0.1',
			'user' => 'username',
			'password' => 'userpassword',
			'sid' => '',
			'serviceName' => ''
		]);
	}

	public function test_getOciString_should_return_exception_if_both_sid_and_serviceName_not_valid(): void
	{
		$this->expectException(BadMethodCallException::class);
		$reflection = new \ReflectionClass(PDOExtended::class);
		$method = $reflection->getMethod('getOciString');
		$method->setAccessible(true);

		$method->invokeArgs($reflection, [['sid' => null, 'serviceName' => null]]);
	}

	///////////////////////////////// CRUD ////////////////////////////////////////////////
	/*public function test_() {
		// Create a stub for the SomeClass class.
		$stub = $this->getMockBuilder(PDOExtended::class)
						->disableOriginalConstructor()
						->disableOriginalClone()
						->disableArgumentCloning()
						->disallowMockingUnknownTypes()
						->getMock();

		// Configure the stub.
		$stub->method('doSomething')
			->willReturn('foo');

		// Calling $stub->doSomething() will now return
		// 'foo'.
		$this->assertEquals('foo', $stub->doSomething());
	}*/
}