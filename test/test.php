<?php

require __DIR__ . '/inc.bootstrap.php';

dump($loggedIn = $client->logIn());
if (!$loggedIn) {
	echo "Can't log in.\n";
	exit(1);
}

// dump($client->getTitleInfo(80021955)); // Better Call Saul
// dump($client->getTitleInfo(81435414)); // Chernobyl 1986
// dump($client->getTitleInfo(81086133)); // Our Great National Parks
// dump($client->getTitleInfo(81040344)); // Squid Game

dump($client->getProfiles());

$profile = rand(0, 1) ? 'Must see' : 'Can see';
dump($profile);
$client->chooseProfile($profile);

dump($client->getMyList());

dump($client->_requests);
