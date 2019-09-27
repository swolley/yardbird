<?php

/*nbpartista*/
final class Nbpartista extends Swolley\YardBird\Models\AbstractModel { 

	/*id smallint(6)*/
	private $id; 
	public function getId() { return $this->id; }
	public function setId(int $value ) { if(mb_strlen((string)$value) <= 6) $this->id = $value;  }

	/*name varchar(255)*/
	private $name; 
	public function getName() { return $this->name; }
	public function setName(string $value ) { if(mb_strlen($value) <= 255) $this->name = $value;  }

	/*bioen varchar(255)*/
	private $bioen; 
	public function getBioen() { return $this->bioen; }
	public function setBioen(string $value ) { if(mb_strlen($value) <= 255) $this->bioen = $value;  }

	/*bioit varchar(255)*/
	private $bioit; 
	public function getBioit() { return $this->bioit; }
	public function setBioit(string $value ) { if(mb_strlen($value) <= 255) $this->bioit = $value;  }

	/*idsoundcloud varchar(255)*/
	private $idsoundcloud; 
	public function getIdsoundcloud() { return $this->idsoundcloud; }
	public function setIdsoundcloud(?string $value = null) { if(mb_strlen($value) <= 255 || $value === null) $this->idsoundcloud = $value;  }

	/*lineupen varchar(255)*/
	private $lineupen; 
	public function getLineupen() { return $this->lineupen; }
	public function setLineupen(string $value ) { if(mb_strlen($value) <= 255) $this->lineupen = $value;  }

	/*lineupit varchar(255)*/
	private $lineupit; 
	public function getLineupit() { return $this->lineupit; }
	public function setLineupit(string $value ) { if(mb_strlen($value) <= 255) $this->lineupit = $value;  }

	/*links varchar(255)*/
	private $links; 
	public function getLinks() { return $this->links; }
	public function setLinks(?string $value = null) { if(mb_strlen($value) <= 255 || $value === null) $this->links = $value;  }

	/*isRoster tinyint(4)*/
	private $isRoster; 
	public function getIsRoster() { return $this->isRoster; }
	public function setIsRoster(?int $value = null) { if(mb_strlen((string)$value) <= 4 || $value === null) $this->isRoster = $value;  }

	/*idstate tinyint(4)*/
	private $idstate; 
	public function getIdstate() { return $this->idstate; }
	public function setIdstate(?int $value = null) { if(mb_strlen((string)$value) <= 4 || $value === null) $this->idstate = $value;  }

	/*createdAt datetime*/
	private $createdAt; 
	public function getCreatedAt() { return $this->createdAt; }
	public function setCreatedAt( $value ) { if($value instanceof DateTime) $this->createdAt = $value; else $this->createdAt = new DateTime($value); }

	/*updatedAt datetime*/
	private $updatedAt; 
	public function getUpdatedAt() { return $this->updatedAt; }
	public function setUpdatedAt( $value ) { if($value instanceof DateTime) $this->updatedAt = $value; else $this->updatedAt = new DateTime($value); }

	/*nbpstatoId int(11)*/
	private $nbpstatoId; 
	public function getNbpstatoId() { return $this->nbpstatoId; }
	public function setNbpstatoId(?int $value = null) { if(mb_strlen((string)$value) <= 11 || $value === null) $this->nbpstatoId = $value;  }

}