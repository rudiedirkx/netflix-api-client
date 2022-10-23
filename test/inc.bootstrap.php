<?php

use rdx\netflix\AuthSession;
use rdx\netflix\AuthWeb;
use rdx\netflix\Client;

require 'vendor/autoload.php';
require 'env.php';

// $client = new Client(new AuthWeb(NETFLIX_USER, NETFLIX_PASS));
$client = new Client(new AuthSession(NETFLIX_SESSION_NETFLIX_ID));
