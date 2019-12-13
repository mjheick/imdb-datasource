<?php

// namespace mjheick;

class DataIMDb
{
	// https://www.imdb.com/name/nm2091145/
	// Alan Rickman -> nm0000614
	// Sophie Turner (Iv) -> nm3849842
	// Emma Watson (II) -> nm0914612
	// David Fennessy -> nm5171734
	public function getName($nm)
	{
		$data = [];
		$page = $this->getWebpage('https://m.imdb.com/name/' . $nm . '/');
		$data = $this->parseMobileWebpage_Name($page);
		return $data;
	}

	// https://www.imdb.com/title/tt1844624/
	// https://m.imdb.com/title/tt1454016/fullcredits/cast
	public function getTitle($tt)
	{
		$data = array();
		$page = $this->getWebpage('https://www.imdb.com/title/' . $tt . '/');
		$page_data = $this->parseWebpage_Title($page);
		$page = $this->getWebpage('https://m.imdb.com/title/' . $tt . '/fullcredits/cast');
		$credit_data = $this->parseMobileWebpage_Title($page);

		$data = $page_data + $credit_data; // old school merge

		return $data;
	}

	private function getWebpage($url)
	{
		$ch = curl_init();
		curl_setopt_array($ch, array(
			CURLOPT_AUTOREFERER => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_HEADER => false,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_CONNECTTIMEOUT => 5,
			CURLOPT_MAXREDIRS => 30,
			CURLOPT_SSL_VERIFYHOST => 0,
			CURLOPT_TIMEOUT => 10,
			CURLOPT_URL => $url,
			CURLOPT_USERAGENT => 'curlAgent 0.0.1-1',
			CURLOPT_HTTPHEADER => array(
				'X-Derpy: Doodles',
			),
		));
		$data = curl_exec($ch);
		curl_close($ch);
		return $data;
	}

	private function parseMobileWebpage_Name($page)
	{
		date_default_timezone_set("UTC");
		$data = array(
			'name' => null,
			'born' => null,
			'died' => null,
			'pic' => null,
			'titles' => null,
		);
		// Firstly, we're gonna look for a specific javascript block that contains JSON to fill in blanks
		// We need to use this as the best source of truth if possible
		// mime-type: application/ld+json
		if (preg_match('/<script type="application\/ld\+json">([\x{0000}-\x{ffff}]*?)<\/script>/u', $page, $matches) === 1)
		{
			$json = json_decode($matches[1], true);
			$data['name'] = array_key_exists('name', $json) ? $json['name'] : null;
			$data['born'] = array_key_exists('birthDate', $json) ? gmdate('Y-m-d', strtotime($json['birthDate'])) : null;
			$data['died'] = array_key_exists('deathDate', $json) ? gmdate('Y-m-d', strtotime($json['deathDate'])) : null;
			$data['pic'] = array_key_exists('image', $json) ? $json['image'] : 'https://m.media-amazon.com/images/G/01/imdb/images/nopicture/medium/name-2135195744._CB499558849_.png';
			$data['ld-json'] = serialize($json);
		}

		// attempt to brute force this puppy out
		if (!array_key_exists('ld-json', $data))
		{
			// Actors Name: <section id="name-overview">\s+<h1>\s+([\w\d\s]+)[<smal>]*(\(\w+\))*[<\/smal>]*
			if (preg_match('/<section id="name-overview">\s+<h1>\s+([\x{0020}-\x{003b}\x{003d}-\x{ffff}]+)[<smal>]*(\(\w+\))*[<\/smal>]*/u', $page, $matches) === 1)
			{
				if (isset($matches[1]))
				{
					$data['name'] = trim($matches[1]);
					if (isset($matches[2]))
					{
						$data['name'] .= ' ' . trim($matches[2]);
					}
				}
			}

			// Date of birth: <time datetime="(\d+-\d+-\d+)" itemprop="birthDate">
			if (preg_match('/<time datetime="(\d+-\d+-\d+)" itemprop="birthDate">/', $page, $matches) === 1)
			{
				
				$dob = $matches[1];
				$data['born'] = gmdate('Y-m-d', strtotime($dob));

			}

			// Date of death: <time datetime="(\d+-\d+-\d+)" itemprop="deathDate">
			if (preg_match('/<time datetime="(\d+-\d+-\d+)" itemprop="deathDate">/', $page, $matches) === 1)
			{
				date_default_timezone_set("UTC");
				$dob = $matches[1];
				$data['died'] = gmdate('Y-m-d', strtotime($dob));

			}

			// mugshot: <img id="name-poster"[\s\r\n]?(?:[\w\d\s\r\n"\-=]+)src="([\w\d:/\.=\-@,]+)"[\s\r\n]?(?:data-src-x2="([\w\d:/\.=\-@,]+)")*
			if (preg_match('/<img id="name-poster"[\s\r\n]?(?:[\w\d\s\r\n"\-=]+)src="([\w\d:\/\.=\-@,]+)"[\s\r\n]?(?:data-src-x2="([\w\d:\/\.=\-@,]+)")*/', $page, $matches) === 1)
			{
				if (isset($matches[2]))
				{
					$data['pic'] = $matches[2];
				}
				else
				{
					$data['pic'] = $matches[1];
				}
			}
		}

		$data['titles'] = null;
		// all titles on this bio page: tt\d+
		if (preg_match_all('/tt\d+/', $page, $matches) > 0)
		{
			// we need everything in $matches[0]
			$title_array = $matches[0];
			$title_array = array_unique($title_array);
			sort($title_array);
			$data['titles'] = $title_array;
		}
		return $data;
	}

	private function parseWebpage_Title($page)
	{
		$data = array(
			'main-title' => null,
			'main-rating' => null,
			'main-rating-count' => null,
		);

		// Firstly, we're gonna look for a specific javascript block that contains JSON to fill in blanks
		// We need to use this as the best source of truth if possible
		// mime-type: application/ld+json
		if (preg_match('/<script type="application\/ld\+json">([\x{0000}-\x{ffff}]*?)<\/script>/u', $page, $matches) === 1)
		{
			$json = json_decode($matches[1], true);
			$data['main-title'] = array_key_exists('name', $json) ? $json['name'] : null;
			$data['main-rating'] = array_key_exists('contentRating', $json) ? $json['contentRating'] : null;
			$data['main-rating-count'] = array_key_exists('aggregateRating', $json) ? $json['aggregateRating']['ratingCount'] : 0;
			$data['ld-json'] = serialize($json);
		}

		// attempt to brute force this puppy out
		if (!array_key_exists('ld-json', $data))
		{
			$data['no-ld-json'] = true;
		}
		return $data;
	}

	private function parseMobileWebpage_Title($page)
	{
		$data = array(
			'title' => null,
			'cast' => null,
		);

		if (preg_match('/<title>([\x{0020}-\x{ffff}]+)\s+\-\s+Cast/u', $page, $matches) === 1)
		{
			$data['title'] = trim($matches[1]);
		}

		$data['cast'] = null;
		// all cast on this bio page. There should be no other actors
		if (preg_match_all('/nm\d+/', $page, $matches) > 0)
		{
			// we need everything in $matches[0]
			$name_array = $matches[0];
			$name_array = array_unique($name_array);
			sort($name_array);
			$data['cast'] = $name_array;
		}
		return $data;
	}

	private function unimplemented()
	{
		throw new Exception('function not implemented');
	}
}
