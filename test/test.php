<?php

require 'inc.bootstrap.php';

var_dump($loggedIn = $client->logIn());
if (!$loggedIn) {
	echo "Can't log in.\n";
	exit(1);
}

// print_r($client->getTitleInfo(80021955)); // Better Call Saul
// print_r($client->getTitleInfo(81435414)); // Chernobyl 1986
// print_r($client->getTitleInfo(81086133)); // Our Great National Parks
// print_r($client->getTitleInfo(81040344)); // Squid Game

print_r($client->getMyList());
