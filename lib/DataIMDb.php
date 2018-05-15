<?php

// namespace mjheick;

class DataIMDb
{
	private $IMDb_access_methods = [
		'website', // www.imdb.com
		'mobile_website', // m.imdb.com
		'api' // ?
	];

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
	public function getName($nm)
	{
	}

	// https://www.imdb.com/title/tt1844624/
	public function getTitle($tt)
	{
	}
}
