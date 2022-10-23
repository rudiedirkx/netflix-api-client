<?php

namespace rdx\netflix;

class EpisodeInfo {

	public function __construct(
		public int $id,
		public int $season,
		public int $episode,
		public ?int $runtime = null,
		public ?int $seen = null,
	) {}

}
