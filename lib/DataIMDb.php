<?php

// namespace mjheick;

class DataIMDb
{
	private $IMDb_access_methods = array(
		'website', // www.imdb.com
		'mobile_website', // m.imdb.com
		'api' // ?
	);

	private $IMDb_access_type = 'mobile_website';

	public function __construct($access_type = 'mobile_website')
	{
		$this->setAccessMethod($access_type);
	}

	public function setAccessMethod($access_type)
	{
		if (!in_array($access_type, $this->IMDb_access_methods))
		{
			throw new Exception('invalid access method [' . $access_type . ']. need (' . join(',', $this->IMDb_access_methods) . ')');
		}
		$this->IMDb_access_type = $access_type;
	}

	// https://www.imdb.com/name/nm2091145/
	// Alan Rickman -> nm0000614
	// Sophie Turner (Iv) -> nm3849842
	// Emma Watson (II) -> nm0914612
	// David Fennessy -> nm5171734
	public function getName($nm)
	{
		$data = array();
		switch ($this->IMDb_access_type)
		{
			case 'website':
				$page = $this->getWebpage('https://www.imdb.com/name/' . $nm . '/');
				break;
			case 'mobile_website':
				$page = $this->getWebpage('https://m.imdb.com/name/' . $nm . '/');
				$data = $this->parseMobileWebpage_Name($page);
				break;
			case 'api':
				$this->unimplemented();
				break;
			default:
				throw new Exception('invalid access type [' . $this->IMDb_access_type . ']');
				break;
		}
		return $data;
	}

	// https://www.imdb.com/title/tt1844624/
	public function getTitle($tt)
	{
		$data = array();
		switch ($this->IMDb_access_type)
		{
			case 'website':
				$page = $this->getWebpage('https://www.imdb.com/title/' . $tt . '/');
				break;
			case 'mobile_website': // https://m.imdb.com/title/tt1454016/fullcredits/cast
				$page = $this->getWebpage('https://m.imdb.com/title/' . $tt . '/fullcredits/cast');
				$data = $this->parseMobileWebpage_Title($page);
				break;
			case 'api':
				$this->unimplemented();
				break;
			default:
				throw new Exception('invalid access type [' . $this->IMDb_access_type . ']');
		}
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
		$data = array();
		// Actors Name: <section id="name-overview">\s+<h1>\s+([\w\d\s]+)[<smal>]*(\(\w+\))*[<\/smal>]*
		$data['name'] = null;
		if (preg_match('/<section id="name-overview">\s+<h1>\s+([\w\d\s]+)[<smal>]*(\(\w+\))*[<\/smal>]*/', $page, $matches) === 1)
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
		$data['born'] = null;
		if (preg_match('/<time datetime="(\d+-\d+-\d+)" itemprop="birthDate">/', $page, $matches) === 1)
		{
			date_default_timezone_set("UTC");
			$dob = $matches[1];
			$data['born'] = gmdate('Y-m-d', strtotime($dob));

		}

		// Date of death: <time datetime="(\d+-\d+-\d+)" itemprop="deathDate">
		$data['died'] = null;
		if (preg_match('/<time datetime="(\d+-\d+-\d+)" itemprop="deathDate">/', $page, $matches) === 1)
		{
			date_default_timezone_set("UTC");
			$dob = $matches[1];
			$data['died'] = gmdate('Y-m-d', strtotime($dob));

		}

		// mugshot: <img id="name-poster"[\s\r\n]?(?:[\w\d\s\r\n"\-=]+)src="([\w\d:/\.=\-@,]+)"[\s\r\n]?(?:data-src-x2="([\w\d:/\.=\-@,]+)")*
		$data['pic'] = null;
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

		// all titles on this bio page: tt\d+
		$data['titles'] = null;
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

	private function parseMobileWebpage_Title($page)
	{
		//echo $page;
		$data = array();

		$data['title'] = null;
		$data['date'] = null;
		// Lets extract this from the title: <title>([\w\d\s:\-]+)\s+\(([\w\d\s]+)\)\s+-\s+Cast\s+-\s+IMDb<\/title>
		if (preg_match('/<title>([\w\d\s:\-\.]+)\s+\(([\w\d\s]+)\)\s+-\s+Cast\s+-\s+IMDb<\/title>/', $page, $matches) === 1)
		{
			$data['title'] = $matches[1];
			$data['date'] = $matches[2];
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
