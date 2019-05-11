<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Swolley\Database\DBFactory;
use Swolley\Database\Exceptions\QueryException;
use Swolley\Database\Exceptions\BadMethodCallException;
use Swolley\Database\Exceptions\UnexpectedValueException;

final class DBFactoryTest extends TestCase
{
	///////////////////////////////// UNIT ////////////////////////////////////////////////
	public function test_checkExtension_should_return_exception_if_empty_string_is_passed(): void
  	{
		$this->expectException(BadMethodCallException::class);
		$reflection = new \ReflectionClass(get_class(new DBFactory));
		$method = $reflection->getMethod('checkExtension');
		$method->setAccessible(true);

		$method->invokeArgs($reflection, ['']);
	}
	
	public function test_checkExtension_should_return_null_if_not_in_list_driver(): void
    {
		$reflection = new \ReflectionClass(get_class(new DBFactory));
		$method = $reflection->getMethod('checkExtension');
		$method->setAccessible(true);
		
        $this->assertEquals(null, $method->invokeArgs($reflection, ['invalid']));
	}
	
	public function test_invoke_class_should_return_exception_if_empty_array_passed(): void
    {
		$this->expectException(BadMethodCallException::class);
		(new DBFactory)([]);
	}

	public function test_invoke_class_should_return_exception_if_no_supported_driver_found(): void
    {
		$this->expectException(\Exception::class);
		(new DBFactory)(['driver' => 'invalid']);
	}

	/*public function test_invoke_class_should_return_iConnectable_instance_if_connection_established(): void
	{
		$connection = (new DBFactory)('');
	}*/
}