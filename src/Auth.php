<?php

namespace rdx\netflix;

use GuzzleHttp\Cookie\CookieJar;

interface Auth {

	public function getCookieJar() : CookieJar;

	public function logIn(Client $client) : bool;

}
