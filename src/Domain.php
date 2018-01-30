<?php

namespace rdx\combelldns;

class Domain {

	public $id;
	public $name;
	public $records = [];

	public function __construct( $id, $name ) {
		$this->id = $id;
		$this->name = strtolower($name);
	}

}
