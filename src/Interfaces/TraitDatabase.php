<?php
namespace Swolley\YardBird\Interfaces;

trait TraitDatabase
{
	protected $_driver = '';

	public function getType(): string
	{
		return $this->_driver;
	}
}