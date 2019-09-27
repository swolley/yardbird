<?php

namespace Swolley\YardBird\Models;

abstract class AbstractModel implements \JsonSerializable
{
	public function __set($key, $value)
	{
		$setter = 'set' . ucwords($key);
		if (method_exists($this, $setter)) {
			$this->$setter($value);
		}
	}

	public function __get($key)
	{
		$getter = 'get' . ucwords($key);
		return method_exists($this, $getter) ? $this->$getter() : null;
	}

	public function jsonSerialize()
	{
		$public = [];
		$reflection = new \ReflectionClass($this);
		foreach ($reflection->getProperties() as $property) {
			$property->setAccessible(true);
			$public[$property->getName()] = $property->getValue($this);
		}
		return $public;
	}
}
