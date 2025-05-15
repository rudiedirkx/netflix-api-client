<?php

namespace rdx\netflix;

use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\RedirectMiddleware;
use rdx\jsdom\Node;

class Client {

	protected Auth $auth;
	protected Guzzle $guzzle;

	public ?string $accountEmail = null;
	public array $profiles;
	public array $myList;

	public array $_requests = [];

	public function __construct(Auth $auth) {
		$this->auth = $auth;

		$this->guzzle = new Guzzle([
			'http_errors' => false,
			'cookies' => $auth->getCookieJar(),
			'allow_redirects' => [
				'track_redirects' => true,
			] + RedirectMiddleware::$defaultSettings,
		]);
	}

	public function getProfiles() : array {
		if (isset($this->profiles)) return $this->profiles;

		$data = $this->getFalcorCache("https://www.netflix.com/browse/my-list", 'profiles');

		$profiles = [];
		foreach ($data['profiles'] as $info) {
			$id = $info['summary']['value']['guid'];
			$name = $info['summary']['value']['profileName'];
			$profiles[$id] = $name;
		}

		return $this->profiles = $profiles;
	}

	public function chooseProfile(string $id) : void {
		$profiles = $this->getProfiles();
		if (!isset($profiles[$id])) {
			$matches = array_filter($profiles, fn($name) => $name == $id);
			if (count($matches) != 1) {
				throw new RuntimeException("Invalid profile: $id");
			}
			$id = key($matches);
		}
// dump($id);

		$rsp = $this->get('https://www.netflix.com/SwitchProfile?next=/browse/my-list&tkn=' . urlencode($id));

		// $html = (string) $rsp->getBody();
		// $doc = Node::create($html);
		// $this->myList = $this->extractMyList($doc);
	}

	public function getMyList() : array {
		if (isset($this->myList)) return $this->myList;

		$rsp = $this->get('https://www.netflix.com/browse/my-list');
		$html = (string) $rsp->getBody();
		$doc = Node::create($html);
		return $this->myList = $this->extractMyList($doc);
	}

	protected function extractMyList(Node $doc) : array {
		$data = $this->extractFalcorCache($doc, 'mylist');
// dump($data);

		$videos = [];
		foreach ($data['mylist'] as $i => $item) {
			if (is_numeric($i) && ($item['$type'] ?? '') === 'ref' && ($item['value'][0] ?? '') === 'videos') {
				$videos[] = $data['videos'][ $item['value'][1] ];
			}
		}

		return array_map(function(array $video) {
			$summary = $video['itemSummary']['value'];
			if (isset($summary['seasonCount'], $summary['episodeCount'])) {
				return new TitleInfo($summary['id'], $summary['title'], seasons: $summary['seasonCount'], episodes: $summary['episodeCount']);
			}

			return new TitleInfo($summary['id'], $summary['title']);
		}, $videos);

		return $videos;
	}

	public function getTitleInfo(int $id) : ?TitleInfo {
		$data = $this->getRawTitleInfo($id);
		if (!$data) return null;

		$title = $data[$id];
		$info = $title['jawSummary']['value'];
		$name = $info['title'];

		$currentVideoId = $title['current']['value'][1];
		if ($currentVideoId == $id) {
			$runtime = $title['runtime']['value'];
			$bookmarkPosition = $title['bookmarkPosition']['value'];
			return new TitleInfo($id, $name, runtime: $runtime, seen: $bookmarkPosition);
		}

		$video = $data[$currentVideoId];
		$runtime = $video['runtime']['value'];
		$bookmarkPosition = $video['bookmarkPosition']['value'];
		$summary = $video['summary']['value'];

		$episode = new EpisodeInfo($currentVideoId, $summary['season'], $summary['episode'], runtime: $runtime, seen: $bookmarkPosition);
		return new TitleInfo($id, $name, currentEpisode: $episode);
	}

	protected function getRawTitleInfo(int $id) : ?array {
		$data = $this->getFalcorCache("https://www.netflix.com/title/$id", 'videos');
		if (is_array($data) && isset($data['videos'][$id])) {
			return $data['videos'];
		}

		return null;
	}

	protected function getFalcorCache(string $url, string $test) : ?array {
		$rsp = $this->get($url);
		$html = (string) $rsp->getBody();
// file_put_contents(__DIR__ . "/../$test.html", $html);
		$doc = Node::create($html);
		return $this->extractFalcorCache($doc, $test);
	}

	protected function extractFalcorCache(Node $doc, string $test) : ?array {
		$scripts = $doc->queryAll('script');
		foreach ($scripts as $el) {
			$script = $el->textContent;
			if (strpos($script, 'netflix.falcorCache') !== false) {
				$start = strpos($script, '{"');
				$json = trim(substr($script, $start), ' ;');
				$json = preg_replace_callback('#\\\\x([0-9A-F]{2})#', function($m) {
					return urldecode('%' . $m[1]);
				}, $json);
				$data = json_decode($json, true);
				if (is_array($data) && isset($data['profiles'], $data[$test])) {
					return $data;
				}

				return null;
			}
		}

		return null;
	}

	public function logIn() : bool {
		return $this->auth->logIn($this) && $this->checkSession();
	}

	protected function checkSession() : bool {
		$rsp = $this->get('https://www.netflix.com/account/security');

		$redirects = $rsp->getHeader(RedirectMiddleware::HISTORY_HEADER);
		if (count($redirects)) {
			return false;
		}

		$html = (string) $rsp->getBody();
		if (!preg_match('#"profileEmailAddress": *("[^"]+"),#', $html, $match)) {
			return false;
		}

		$email = json_decode(strtr($match[1], ['\\x40' => '@']));

		$this->accountEmail = $email;

		return true;
	}

	public function get(string $url) : Response {
		$t = hrtime(true);
		try {
			$rsp = $this->guzzle->get($url);
		}
		finally {
			$t = (hrtime(true) - $t) / 1e9;
			$this->_requests[] = ['GET', $url, $t];
		}

		return $rsp;
	}

}
