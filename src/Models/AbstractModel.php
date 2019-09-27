<?php
namespace Swolley\YardBird\Models;

abstract class AbstractModel
{
	public function __set($key, $value) {
		$setter = 'set' . ucwords($key);
		if(property_exists($this, $key) && method_exists($this, $setter)) {
			$this->$setter($value);
		}
	}
}