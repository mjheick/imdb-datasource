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
	public function getName($nm)
	{
		$data = array();
		switch ($this->IMDb_access_type)
		{
			case 'website':
				break;
			case 'mobile_website':
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
				break;
			case 'mobile_website':
				break;
			case 'api':
				$this->unimplemented();
				break;
			default:
				throw new Exception('invalid access type [' . $this->IMDb_access_type . ']');
		}
		return $data;
	}

	private function unimplemented()
	{
		throw new Exception('function not implemented');
	}
}
