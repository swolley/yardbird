<?php
namespace Swolley\YardBird\Results;

use Swolley\YardBird\Connection;
use Swolley\YardBird\Interfaces\AbstractResult;
use MongoDB\Driver\Cursor;
use MongoDB\Model\BSONDocument;

class MongoResult extends AbstractResult
{
	private $_queryType;

	public function __construct(Cursor $stmt, string $queryType = null,  int $insertedId = null)
	{
		parent::__construct($stmt, $insertedId);
		$this->_queryType = $queryType;
	}

	public function fetch(int $fetchMode = Connection::FETCH_ASSOC, $fetchModeParam = 0, array $fetchPropsLateParams = []): array
	{
		$needs_manual_casting = false;
		switch ($fetchMode) {
			case Connection::FETCH_ASSOC:
				$this->_sth->setTypeMap(['root' => 'array', 'document' => 'array', 'array' => 'array']);
				break;
			case Connection::FETCH_OBJ:
				$this->_sth->setTypeMap(['root' => 'object', 'document' => 'object', 'array' => 'array']);
				break;
			case Connection::FETCH_CLASS:
				if (is_a($fetchModeParam, BSONDocument::class, true)) {
					$this->_sth->setTypeMap(['root' => $fetchModeParam, 'document' => $fetchModeParam, 'array' => 'array']);
				} else {
					$this->_sth->setTypeMap(['root' => 'object', 'document' => 'object', 'array' => 'array']);
					$needs_manual_casting = true;
				}
				break;
			default:
				throw new MongoException\CommandException('Can\'t fetch. Only Object or Associative Array mode accepted');
		}

		$list = $this->_sth->toArray();
		if ($needs_manual_casting) {
			$list = array_map(function ($item) use ($fetchModeParam) {
				return self::objectToObject($item, $fetchModeParam);
			}, $list);
		}
		return $list;
	}

	public function count(): int
	{
		try {
			switch($this->_queryType) {
				case 'insert':
					return $this->_sth->getInsertedCount();
				case 'update':
					return $this->_sth->getModifiedCount();
				case 'delete':
					return $this->_sth->getDeletedCount();		
				default: 
					return $this->_sth->count();
			}
		} catch(\Exception $ex) {
			throw new BadMethodCallException($ex->getMessage(), $ex->getCode());
		}
	}

	private static function objectToObject($instance, $className)
	{
		return unserialize(sprintf(
			'O:%d:"%s"%s',
			strlen($className),
			$className,
			strstr(strstr(serialize($instance), '"'), ':')
		));
	}
}