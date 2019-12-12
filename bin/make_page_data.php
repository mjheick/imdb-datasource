<?php
ini_set('memory_limit', '2048M');
 /**
 * MySQL Info
 */
$db_host = 'localhost';
$db_user = 'root';
$db_pass = 'password';
$db_database = 'IMDb';

// connect to database
$link = mysqli_connect($db_host, $db_user, $db_pass, $db_database);
if (!$link || mysqli_connect_errno())
{
	die('cannot connect to database [' . mysqli_connect_errno() . ']');
}

// my.cnf: max_allowed_packet under [mysqld]
// https://dev.mysql.com/doc/refman/8.0/en/packet-too-large.html
$query = "SHOW VARIABLES LIKE  'max_allowed_packet'";
$result = mysqli_query($link, $query);
$data = mysqli_fetch_assoc($result);
echo "Max MySQL Packet Size: " . $data['Value']. "\n\n";
mysqli_free_result($result);

// some variables
$decades = array(
	'1890',
	'1900',
	'1910',
	'1920',
	'1930',
	'1940',
	'1950',
	'1960',
	'1970',
	'1980',
	'1990',
	'2000',
	'2010',
);

$movie_cast_threshold = 30;

$decades_dead_or_alive = array();

foreach ($decades as $decade)
{
	echo '(time:' . time() . ',mem:' . memory_get_usage() . ') Starting decade ' . $decade. "\n";
	// Query to get all titles for a decade
	$title = array();
	$list_of_titles = array();
	$query = 'SELECT `tt` FROM `titles` WHERE `title` REGEXP \'\\\\(' . substr($decade, 0, 3) . '[0-9]\\\\)\'';
	$result = mysqli_query($link, $query);
	while ($data = mysqli_fetch_assoc($result))
	{
		$list_of_titles[] = $data['tt'];
		$title[$data['tt']] = array(
			//'title' => $data['title'],
			'decade' => $decade,
			'cast-count' => -1,
			'alive' => -1,
			'alive-people' => array(),
			'dead' => -1,
			'dead-people' => array(),
			'unknown' => 0,
			'percentage_alive' => -1,
			'percentage_dead' => -1,
		);
	}
	$title_list = implode("','", $list_of_titles);
	unset($list_of_titles);	
	mysqli_free_result($result);
	echo '(time:' . time() . ',mem:' . memory_get_usage() . ') title = ' . count($title). "\n";

	// get titles->names for all titles in $title_list
	// this query could break stuff
	$tt2nm = array();
	$list_of_names = array();
	$query = 'SELECT * FROM `tt2nm` WHERE `tt` IN (\'' . $title_list . '\')';
	echo '(time:' . time() . ',mem:' . memory_get_usage() . ') tt2nm query length = ' . strlen($query). "\n";
	$result = mysqli_query($link, $query);
	if ($result !== false)
	{
		while ($data = mysqli_fetch_assoc($result))
		{
			if (!array_key_exists($data['tt'], $tt2nm))
			{
				$tt2nm[$data['tt']] = array();
			}
			$tt2nm[$data['tt']][] = $data['nm'];
			$list_of_names[] = $data['nm'];
		}
		mysqli_free_result($result);
	}
	$list_of_names = array_unique($list_of_names);
	$name_list = implode("','", $list_of_names);
	unset($list_of_names);
	echo '(time:' . time() . ',mem:' . memory_get_usage() . ') tt2nm = ' . count($tt2nm). "\n";

	// get all names now
	$people = array();
	$query = 'SELECT * FROM `names` WHERE (NOT `name` IS NULL) AND (`nm` IN (\'' . $name_list . '\'))';
	echo '(time:' . time() . ',mem:' . memory_get_usage() . ') names query length = ' . strlen($query). "\n";
	$result = mysqli_query($link, $query);
	if ($result !== false)
	{
		while ($data = mysqli_fetch_assoc($result))
		{
			$people[$data['nm']] = array(
				'name' => $data['name'],
				'born' => $data['born'],
				'died' => $data['died'],
				'pic' => $data['pic'],
			);
		}
		mysqli_free_result($result);
	}
	echo '(time:' . time() . ',mem:' . memory_get_usage() . ') people = ' . count($people). "\n";

	$decades_dead_or_alive[$decade] = array('alive' => 0, 'dead' => 0);
	$last_percent = "";
	$titles_index = 0;
	$titles_total = count($title);
	echo '(time:' . time() . ',mem:' . memory_get_usage() . ') Doing Work' . "\n";

	$title_cull = array(); // if count(cast) < $movie_cast_threshold, then we cull
	foreach ($title as $key => $array)
	{
		$titles_index++;
		// progress, cause work is impatient
		$percent = intval(($titles_index / $titles_total) * 100);
		if ($percent != $last_percent)
		{
			echo $percent . "%[" . memory_get_usage() . "] ";
			$last_percent = $percent;
		}

		// run the title through a regex. If we can extract a 4-digit year from it, we've got a decade
		$decade = $array['decade'];

		// Lets count people!
		if (array_key_exists($key, $tt2nm))
		{
			$peeps = $tt2nm[$key];
			$title[$key]['cast-count'] = count($peeps);
			if ($title[$key]['cast-count'] < $movie_cast_threshold)
			{
				$title_cull[] = $key;
			}
			else
			{
				$unknown = 0;
				$alive = 0;
				$dead = 0;
				foreach ($peeps as $cast_member)
				{
					if (array_key_exists($cast_member, $people))
					{
						if ($people[$cast_member]['died'] == '0000-00-00')
						{
							$alive = $alive + 1;
							$title[$key]['alive-people'][] = $cast_member;
						}
						else
						{
							$dead = $dead + 1;
							$title[$key]['dead-people'][] = $cast_member;
						}
					}
					else
					{
						$unknown = $unknown + 1;
					}
				}
				$title[$key]['alive'] = $alive;
				$title[$key]['dead'] = $dead;
				$title[$key]['unknown'] = $unknown;

				// basic math
				if ($title[$key]['unknown'] == 0)
				{
					$title[$key]['percentage_alive'] = intval(($alive / count($peeps)) * 100);
					$title[$key]['percentage_dead'] = intval(($dead / count($peeps)) * 100);

					// add to decade table to determine which direction we go when displaying data
					if ($title[$key]['percentage_alive'] > $title[$key]['percentage_dead'])
					{
						$decades_dead_or_alive[$decade]['alive'] = $decades_dead_or_alive[$decade]['alive'] + 1;
					}
					else
					{
						$decades_dead_or_alive[$decade]['dead'] = $decades_dead_or_alive[$decade]['dead'] + 1;
					}
				}
			}
		}
	}
	foreach ($title_cull as $item)
	{
		unset($title[$item]);
	}
	echo "\n" . '(time:' . time() . ',mem:' . memory_get_usage() . ') fin' . "\n";
	unset($title);
	unset($tt2nm);
	unset($people);
}

print_r($decades_dead_or_alive);