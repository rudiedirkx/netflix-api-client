<?php

namespace rdx\netflix;

class TitleInfo {

	public function __construct(
		public int $id,
		public string $name,
		public ?int $runtime = null,
		public ?int $seen = null,
		public ?int $seasons = null,
		public ?int $episodes = null,
		public ?EpisodeInfo $currentEpisode = null,
	) {}

}
