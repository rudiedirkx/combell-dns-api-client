<?php

namespace rdx\combelldns;

use GuzzleHttp\Cookie\CookieJar;

class WebAuth {

	public $cookies;
	public $base = '';
	public $user = '';
	public $pass = '';

	/**
	 * Dependency constructor
	 */
	public function __construct( $base, $user, $pass ) {
		$this->cookies = $this->getCookieJar();
		$this->base = rtrim($base, '/') . '/';
		$this->user = $user;
		$this->pass = $pass;
	}

	/**
	 *
	 */
	protected function getCookieJar() {
		return new CookieJar;
	}

}
