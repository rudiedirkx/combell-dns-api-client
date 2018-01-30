<?php

namespace rdx\combelldns;

class DnsRecord {

	public $id;
	public $name;
	public $type;
	public $value;
	public $ttl;

	public function __construct( $id, $name, $type, $value, $ttl ) {
		$this->id = $id;
		$this->name = strtolower($name);
		$this->type = strtolower($type);
		$this->value = $value;
		$this->ttl = $ttl;
	}

}
