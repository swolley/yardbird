<?php
declare(strict_types=1);

namespace Swolley\YardBird;

use Swolley\YardBird\Interfaces\IConnectable;
/*use Swolley\YardBird\Interfaces\ICrudable;*/
use Swolley\YardBird\Utils\Utils;
use Swolley\YardBird\Exceptions\ConnectionException;

final class Pool /*extends ICrudable*//* implements \Countable*/
{
	/** 
	 * @var array $connections connections list
	 **/
	private $connections = [];

	public function add(string $name, array $connectionParameters, bool $debugMode = false): Pool
	{
		foreach ($this->connections as $connection_name => $connection) {
			if ($connection_name === $name || $connection->getHash() === Utils::hash($connectionParameters)) throw new ConnectionException("The connection already exists");
		}

		$this->connections[$name] = (new Connection)($connectionParameters, $debugMode);

		return $this;
	}

	public function remove(string $name): bool
	{
		if (array_key_exists($name, $this->connections)) {
			unset($this->connections[$name]);
			return true;
		}

		return false;
	}

	public function list(): array
	{
		$list = [];
		foreach ($this->connections as $name => $connection) {
			$list[$name] = ['driver' => $connection->getType(), 'host' => $connection->getHost(), 'dbName' => $connection->getDbName()];
		}

		return $list;
	}

	public function count(): int
	{
		return count($this->connections);
	}

	public function __get(string $connectionName): ?IConnectable
	{
		return array_key_exists($connectionName, $this->connections) ? $this->connections[$connectionName] : null;
	}
}
