<?php
ini_set('memory_limit', '512M');
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

echo "Getting titles...";
$title = array();
$query = 'SELECT * FROM `titles` WHERE (NOT `title` IS NULL)'; 
$result = mysqli_query($link, $query);
while ($data = mysqli_fetch_assoc($result))
{
	$title[$data['tt']] = array(
		'title' => $data['title'],
		'decade' => null,
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
mysqli_free_result($result);
echo count($title) . "\n";

echo "Getting people...";
$people = array();
$query = 'SELECT * FROM `names` WHERE (NOT `name` IS NULL)';
$result = mysqli_query($link, $query);
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
echo count($people) . "\n";

echo "Getting tt2nm...";
$tt2nm = array();
$query = 'SELECT * FROM `tt2nm`';
$result = mysqli_query($link, $query);
while ($data = mysqli_fetch_assoc($result))
{
	if (!array_key_exists($data['tt'], $tt2nm))
	{
		$tt2nm[$data['tt']] = array();
	}
	$tt2nm[$data['tt']][] = $data['nm'];
}
mysqli_free_result($result);
echo count($tt2nm) . "\n";

$last_percent = "";
$titles_index = 0;
$titles_total = count($title);
echo "Doing work...";
$titles_to_remove = array();
$list_of_decades = array();
$decades_dead_or_alive = array();
foreach ($title as $key => $array)
{
	$titles_index++;
	// progress, cause work is impatient
	$percent = intval(($titles_index / $titles_total) * 100);
	if ($percent != $last_percent)
	{
		echo "%";
		$last_percent = $percent;
	}

	// run the title through a regex. If we can extract a 4-digit year from it, we've got a decade
	$title_name = $array['title'];
	if (preg_match('/\((\s*\d+\s*)\)/', $title_name, $matches) === 1)
	{
		$year = trim($matches[1]);
		$decade = substr($year, 0, 3) . '0';
		$title[$key]['decade'] = $decade;
		$list_of_decades[] = $decade;
		if (!array_key_exists($decade, $decades_dead_or_alive))
		{
			$decades_dead_or_alive[$decade] = array(
				'dead' => 0,
				'alive' => 0,
			);
		}
	}
	else
	{
		$titles_to_remove[] = $key;
	}

	// Lets count people!
	if (array_key_exists($key, $tt2nm))
	{
		$peeps = $tt2nm[$key];
		$title[$key]['cast-count'] = count($peeps);
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
				$decades_dead_or_alive[$title[$key]['decade']]['alive'] = $decades_dead_or_alive[$title[$key]['decade']]['alive'] + 1;
			}
			else
			{
				$decades_dead_or_alive[$title[$key]['decade']]['dead'] = $decades_dead_or_alive[$title[$key]['decade']]['dead'] + 1;
			}
		}
	}
	else
	{
		$titles_to_remove[] = $key;
	}
}
$titles_to_remove = array_unique($titles_to_remove);
$list_of_decades = array_unique($list_of_decades);
sort($list_of_decades);
// cull the titles_to_remove
foreach ($titles_to_remove as $nuke)
{
	unset($title[$nuke]);
}
echo ' ' . count($title) . "\n";

echo 'Decades: ' . implode(',', $list_of_decades) . "\n";
ksort($decades_dead_or_alive);
print_r($decades_dead_or_alive);

die();
$i = 5;
foreach ($title as $k => $v)
{
	echo "$k\n";
	print_r($v);
	$i--;
	if ($i == 0) { break; }
}