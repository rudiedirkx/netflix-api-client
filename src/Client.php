<?php

namespace rdx\netflix;

use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\RedirectMiddleware;
use rdx\jsdom\Node;

class Client {

	protected Auth $auth;
	protected Guzzle $guzzle;

	public ?string $accountEmail = null;

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

	public function getMyList() : array {
		$videos = $this->getRawMyList();
		if (!$videos) return [];

		return array_map(function(array $video) {
			$summary = $video['itemSummary']['value'];
			if (isset($summary['seasonCount'], $summary['episodeCount'])) {
				return new TitleInfo($summary['id'], $summary['title'], seasons: $summary['seasonCount'], episodes: $summary['episodeCount']);
			}

			return new TitleInfo($summary['id'], $summary['title']);
		}, $videos);
	}

	protected function getRawMyList() : ?array {
		$data = $this->getFalcorCache("https://www.netflix.com/browse/my-list", 'mylist');
		if (!is_array($data)) return null;

		$videos = [];
		foreach ($data['mylist'] as $i => $item) {
			if (is_numeric($i) && ($item['$type'] ?? '') === 'ref' && ($item['value'][0] ?? '') === 'videos') {
				$videos[] = $data['videos'][ $item['value'][1] ];
			}
		}

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
		$rsp = $this->guzzle->get($url);
		$html = (string) $rsp->getBody();
		$doc = Node::create($html);

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
		$rsp = $this->guzzle->get('https://www.netflix.com/YourAccount');

		$redirects = $rsp->getHeader(RedirectMiddleware::HISTORY_HEADER);
		if (count($redirects)) {
			return false;
		}

		$html = (string) $rsp->getBody();
		if (strpos($html, '"account-email"') === false) {
			return false;
		}

		$doc = Node::create($html);

		$email = $doc->query('[data-uia="account-email"]');
		if (!$email) {
			return false;
		}
		$email = $email->textContent;

		$profiles = $doc->queryAll('.profile-hub h3');
		if (!count($profiles)) {
			return false;
		}
		$profiles = array_map(fn($el) => $el->textContent, $profiles);

		$this->accountEmail = $email;

		return true;
	}

}
