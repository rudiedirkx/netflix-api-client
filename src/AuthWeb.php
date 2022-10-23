<?php

namespace rdx\netflix;

use GuzzleHttp\Cookie\CookieJar;

class AuthWeb implements Auth {

	protected string $user;
	protected string $pass;
	protected CookieJar $cookies;

	public function __construct( string $user, string $pass ) {
		$this->user = $user;
		$this->pass = $pass;

		$this->cookies = new CookieJar();
	}

	public function getCookieJar() : CookieJar {
		return $this->cookies;
	}

	public function logIn(Client $client) : bool {
		$rsp = $this->guzzle->get('https://www.netflix.com/login');
print_r($rsp);
// print_r($this->auth->cookies);
		$html = (string) $rsp->getBody();
// echo "$html\n";
		$doc = Node::create($html);

		$url = $rsp->getHeader(RedirectMiddleware::HISTORY_HEADER);
		$url = end($url);
var_dump($url);

		$form = $doc->query('form.login-form');
		$values = $form->getFormValues();
		$data = [
			'email' => $this->auth->user,
			'password' => $this->auth->pass,
		] + $values;
print_r($data);

		$rsp = $this->guzzle->post($url, [
			'form_data' => $data,
		]);
// print_r($rsp);

		$rsp = $this->guzzle->get('https://www.netflix.com/YourAccount');
print_r($rsp);

		return false;
	}

}
