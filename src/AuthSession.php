<?php

namespace rdx\netflix;

use GuzzleHttp\Cookie\CookieJar;

class AuthSession implements Auth {

	protected CookieJar $cookies;

	public function __construct( string $NetflixId ) {
		$this->cookies = new CookieJar(false, [
			[
				'Domain' => '.netflix.com',
				'Name' => 'NetflixId',
				'Value' => $NetflixId,
			],
		]);
	}

	public function getCookieJar() : CookieJar {
		return $this->cookies;
	}

	public function logIn(Client $client) : bool {
		return true;
	}

}
