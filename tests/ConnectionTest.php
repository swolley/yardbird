<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Swolley\YardBird\Connection;
use Swolley\YardBird\Exceptions\QueryException;
use Swolley\YardBird\Exceptions\BadMethodCallException;
use Swolley\YardBird\Exceptions\UnexpectedValueException;
use Swolley\YardBird\Exceptions\ConnectionException;

/**
 * @covers Swolley\YardBird\Connection
 */
final class ConnectionTest extends TestCase
{
	///////////////////////////////// UNIT ////////////////////////////////////////////////
	/**
	 * @covers Swolley\YardBird\Connection::checkExtension
	 * @group unit
	 */
	public function test_checkExtension_should_return_null_if_not_in_list_driver(): void
	{
		$reflection = new \ReflectionClass(get_class(new Connection));
		$method = $reflection->getMethod('checkExtension');
		$method->setAccessible(true);

		$this->assertEquals(null, $method->invokeArgs($reflection, ['invalid']));
	}

	public function test_checkExtension_should_return_parsed_driver_if_available():void
	{
		$reflection = new \ReflectionClass(get_class(new Connection));
		$method = $reflection->getMethod('checkExtension');
		$method->setAccessible(true);

		$this->assertEquals('mongo', $method->invokeArgs($reflection, ['mongodb']));
		$this->assertEquals('pdo', $method->invokeArgs($reflection, ['mysql']));
		$this->assertEquals('mysqli', $method->invokeArgs($reflection, ['mysqli']));
		$this->assertEquals('oci', $method->invokeArgs($reflection, ['oci8']));
	}

	/**
	 * @covers Swolley\YardBird\Connection::__invoke
	 * @group unit
	 */
	public function test_invoke_class_should_return_exception_if_missing_parameters(): void
	{
		$this->expectException(BadMethodCallException::class);
		(new Connection)([ 'user' => null ]);
	}

	/**
	 * @covers Swolley\YardBird\Connection::__invoke
	 * @group unit
	 */
	public function test_invoke_class_should_return_exception_if_no_supported_driver_found(): void
	{
		$this->expectException(\Exception::class);
		(new Connection)(['driver' => 'invalid']);
	}

	/**
	 * @covers Swolley\YardBird\Connection::__invoke
	 * @group unit
	 */
	public function test_invoke_class_should_return_exception_if_cant_connect(): void
	{
		$this->expectException(ConnectionException::class);
		(new Connection)(['driver' => 'mysql', 'host' => '127.0.0.1', 'user' => 'username', 'password' => 'userpassword', 'dbName' => 'name']);
	}
}
