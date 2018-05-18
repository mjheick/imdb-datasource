<?php
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

?><!DOCTYPE html>
<html>
	<head>
	</head>
	<body>
<h2>IMDb Stats</h2>
<div>

<pre>
<?php
$query = 'SELECT COUNT(*) AS `cnt` FROM `names`';
$result = mysqli_query($link, $query);
$data = mysqli_fetch_assoc($result);
echo 'Total Names: ' . $data['cnt'] . '</br>';
mysqli_free_result($result);

$query = 'SELECT COUNT(*) AS `cnt` FROM `names` WHERE NOT `name` IS NULL';
$result = mysqli_query($link, $query);
$data = mysqli_fetch_assoc($result);
echo 'Named entries: ' . $data['cnt'] . '</br>';
mysqli_free_result($result);

$query = 'SELECT COUNT(*) AS `cnt` FROM `names` WHERE NOT `name` IS NULL AND `died` != \'0000-00-00\'';
$result = mysqli_query($link, $query);
$data = mysqli_fetch_assoc($result);
echo 'Dead people: ' . $data['cnt'] . '</br>';
mysqli_free_result($result);

$query = 'SELECT COUNT(*) AS `cnt` FROM `titles`';
$result = mysqli_query($link, $query);
$data = mysqli_fetch_assoc($result);
echo 'Total Titles: ' . $data['cnt'] . '</br>';
mysqli_free_result($result);

$query = 'SELECT COUNT(*) AS `cnt` FROM `titles` WHERE NOT `title` IS NULL';
$result = mysqli_query($link, $query);
$data = mysqli_fetch_assoc($result);
echo 'Named Titles: ' . $data['cnt'] . '</br>';
mysqli_free_result($result);

// ( has to be escaped like \\(, but in php \\ needs to be escaped so \\\\(
$query = 'SELECT COUNT(*) AS `cnt` FROM `titles` WHERE `title` REGEXP \'\\\\([0-9]+\\\\)\'';
$result = mysqli_query($link, $query);
$data = mysqli_fetch_assoc($result);
echo 'Named "Movies": ' . $data['cnt'] . '</br>';
mysqli_free_result($result);

$query = 'SELECT `nm`,`name` FROM `names` ORDER BY `last_update` DESC LIMIT 1';
$result = mysqli_query($link, $query);
$data = mysqli_fetch_assoc($result);
echo 'Recent Name: ' . $data['name'] . ' <a href="https://www.imdb.com/name/' . $data['nm'] . '/" target="_blank">' . $data['nm'] . '</a></br>';
mysqli_free_result($result);

$query = 'SELECT `tt`,`title` FROM `titles` ORDER BY `last_update` DESC LIMIT 1';
$result = mysqli_query($link, $query);
$data = mysqli_fetch_assoc($result);
echo 'Recent Title: ' . $data['title'] . ' <a href="https://www.imdb.com/title/' . $data['tt'] . '/" target="_blank">' . $data['tt'] . '</a></br>';
mysqli_free_result($result);

?>
</pre>
</div>
	</body>
</html>