<?php
//require_once('../lib/DataIMDb.php');
require_once('/home/matt/github/imdb-datasource/lib/DataIMDb.php');

$names_start = "100";
$names_length = "50";
$titles_start = "100";
$titles_length = "50";
$database_freshness = "INTERVAL 6 MONTH";

$imdb = new DataIMDb();

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

// Cull invalid entries
if (mt_rand(0, 1000) == 100) {
	$query = "DELETE FROM `names` WHERE LENGTH(`nm`)<9";
	mysqli_query($link, $query);
	$query = "DELETE FROM `titles` WHERE LENGTH(`tt`)<9";
	mysqli_query($link, $query);
	$query = "DELETE FROM `tt2nm` WHERE LENGTH(`nm`)<9 OR LENGTH(`tt`)<9";
	mysqli_query($link, $query);
}

// get 10 people that we need to update
$query = "SELECT `nm` FROM `names` WHERE `last_update`<DATE_SUB(NOW(), ${database_freshness}) ORDER BY `nm`,`last_update` ASC LIMIT ${names_start}, ${names_length}";
$result = mysqli_query($link, $query);
while ($data = mysqli_fetch_assoc($result))
{
	// ask IMDb about $nm
	$nm = $data['nm'];
	$data = $imdb->getName($nm);
	
	// run an update 
	$update_fields = array();
	if (!is_null($data['name']))
	{
		$update_fields[] = '`name`="' . mysqli_real_escape_string($link, $data['name']) . '"';
	}
	if (!is_null($data['born']))
	{
		$update_fields[] = '`born`="' . mysqli_real_escape_string($link, $data['born']) . '"';
	}
	if (!is_null($data['died']))
	{
		$update_fields[] = '`died`="' . mysqli_real_escape_string($link, $data['died']) . '"';
	}
	if (!is_null($data['pic']))
	{
		$update_fields[] = '`pic`="' . mysqli_real_escape_string($link, $data['pic']) . '"';
	}
	if (count($update_fields) > 0)
	{
		$update_fields[] = '`last_update`=NOW()';
		$update_fields_query = implode(',', $update_fields);
		$update_query = 'UPDATE `names` SET ' . $update_fields_query . ' WHERE `nm`="' . $nm . '" LIMIT 1';
		mysqli_query($link, $update_query);
		echo "$update_query\n";
	}

	// lets see if there were any titles from this user that we can insert
	// tt is a PK, so we can't really insert duplicates
	if (!is_null($data['titles']))
	{
		$titles_query_array = array();
		foreach ($data['titles'] as $title)
		{
			$titles_query = "INSERT INTO `titles` (`tt`, `inserted`) VALUES " . '("' . mysqli_real_escape_string($link, $title) . '", NOW())';
			mysqli_query($link, $titles_query);
			echo "$titles_query\n";
		}
	}
}
mysqli_free_result($result);

// get titles that we need to get people from
$query = "SELECT `tt` FROM `titles` WHERE `last_update`<DATE_SUB(NOW(), ${database_freshness}) ORDER BY `tt`,`last_update` ASC LIMIT ${titles_start}, ${titles_length}";
$result = mysqli_query($link, $query);
while ($data = mysqli_fetch_assoc($result))
{
	// ask IMDb about $tt
	$tt = $data['tt'];
	$data = $imdb->getTitle($tt);

	// run an update 
	$update_fields = array();
	if (!is_null($data['title']))
	{
		$update_fields[] = '`title`="' . mysqli_real_escape_string($link, $data['title']) . '"';
	}
	if (count($update_fields) > 0)
	{
		$update_fields[] = '`last_update`=NOW()';
		$update_fields_query = implode(',', $update_fields);
		$update_query = 'UPDATE `titles` SET ' . $update_fields_query . ' WHERE `tt`="' . $tt . '" LIMIT 1';
		mysqli_query($link, $update_query);
		echo "$update_query\n";
	}

	// We have all the cast of this, so lets put that into tt2nm
	// there's a unique key that prevents duplicate entries
	if (!is_null($data['cast']))
	{
		$cast_query_array = array();
		$names_insert_array = array();
		foreach ($data['cast'] as $cast)
		{
			$cast_query = "INSERT INTO `tt2nm` (`tt`, `nm`) VALUES " . '("' . mysqli_real_escape_string($link, $tt) . '", "' . mysqli_real_escape_string($link, $cast) . '")';
			mysqli_query($link, $cast_query);
			echo "$cast_query\n";

			$names_query = "INSERT INTO `names` (`nm`, `inserted`) VALUES " . '("' . mysqli_real_escape_string($link, $cast) . '", NOW())';
			mysqli_query($link, $names_query);
			echo "$names_query\n";
		}
	}
}
mysqli_free_result($result);
