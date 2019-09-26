<?php
namespace Swolley\YardBird;

use Swolley\YardBird\Interfaces\IConnectable;
use Swolley\YardBird\Exceptions\ConnectionException;

final class Pool implements \Countable
{
	/** @var array $connections */
	private $connections = [];

	public function add(string $name, array $connectionParameters, bool $debugMode = false): bool
	{
		if(array_key_exists($name, $this->connections)) throw new ConnectionException("$name connction already exists");
		
		if($connections[$name] = (new Connection)($connectionParameters)) {
			return true;
		}

		return false;
	}

	public function __get(string $connectionName)
	{
		return array_key_exists($connectionName, $this->connections) ? $this->connections[$connectionName] : null;
	}
}