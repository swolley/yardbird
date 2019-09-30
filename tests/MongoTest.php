<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Swolley\YardBird\Drivers\Mongo;
use Swolley\YardBird\Exceptions\QueryException;
use Swolley\YardBird\Exceptions\ConnectionException;
use Swolley\YardBird\Exceptions\BadMethodCallException;
use Swolley\YardBird\Exceptions\UnexpectedValueException;
use Swolley\YardBird\Interfaces\IConnectable;

/**
 * @covers Swolley\YardBird\Drivers\Mongo
 */
final class MongoTest extends TestCase
{

	///////////////////////////////// UNIT ////////////////////////////////////////////////
	/**
     * @codeCoverageIgnore
	 * @group unit
     */
	public function test_Mongo_should_implements_IConnectable(): void
	{
		$reflection = new \ReflectionClass(Mongo::class);
		$this->assertTrue($reflection->implementsInterface(IConnectable::class));
	}

	/**
	 * @covers Swolley\YardBird\Drivers\Mongo::validateConnectionParams
	 * @group unit
	 */
	public function test_validateConnectionParams_should_return_exception_if_no_valid_parameters_passed(): void
    {
		$this->expectException(BadMethodCallException::class);
		$reflection = new \ReflectionClass(Mongo::class);
		$method = $reflection->getMethod('validateConnectionParams');
		$method->setAccessible(true);
		$method->invokeArgs($reflection, [[ 'host' => '', 'user' => null, 'password' => null ]]);
	}

	/**
	 * @covers Swolley\YardBird\Drivers\Mongo::validateConnectionParams
	 * @group unit
	 */
	public function test_validateConnectionParams_should_return_array_if_correct_value_passed(): array
    {
		$reflection = new \ReflectionClass(Mongo::class);
		$method = $reflection->getMethod('validateConnectionParams');
		$method->setAccessible(true);
		$expected = [ 'driver' => 'mongodb', 'dbName' => 'name', 'host' => '127.0.0.1', 'user' => 'username', 'password' => 'userpassword', 'port' => 27017 ];
		$result = $method->invokeArgs($reflection, [[ 'driver' => 'mongodb', 'dbName' => 'name', 'host' => '127.0.0.1', 'user' => 'username', 'password' => 'userpassword' ]]);
		$this->assertEquals($result, $expected);

		return $result;
	}

	/**
	 * @covers Swolley\YardBird\Drivers\Mongo::__construct
	 * @depends test_validateConnectionParams_should_return_array_if_correct_value_passed
	 * @group unit
	 */
	public function test_constructor_should_throw_exception_if_cant_establish_connection($params): void
	{
		$this->expectException(ConnectionException::class);
		new Mongo($params);
	}
	
}