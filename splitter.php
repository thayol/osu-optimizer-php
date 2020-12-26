<?php
require "libraries/osu_library.php";

$page = $_GET["page"];
$page = intval($page);
if ($page < 1) $page = 1;
$pagesize = 100;

$lib = new osu_library();
$library = $lib->get_library();
$library_size = count($library);

$maxpage = ceil($library_size/$pagesize);
if ($page > $maxpage) $page = $maxpage;

$startindex = ($page - 1) * $pagesize;

$start = file_get_contents("resources/start.html");
$start = str_replace("{{ STYLE }}", file_get_contents("resources/style.css"), $start);
echo $start;

$partial_library = array_slice($library, $startindex, $pagesize);

$previous = $page - 1;
if ($previous < 1) $previous = 1;
$next = $page + 1;
if ($next > $maxpage) $next = $maxpage;

echo '<h2>Page ' . $page . '/' . $maxpage . ' of osu! songs</h2>';
echo '<a href="./splitter.php?page=' . $previous . '">[Previous]</a> ';
echo '<a href="./splitter.php?page=' . $next . '">[Next]</a> ';
echo "<pre>";
// print_r($partial_library);
// foreach ($partial_library as $key => $value)
// {
	// $firstdiff = $value["difficulties"][array_key_first($value["difficulties"])];
	// echo $firstdiff["artist"] . " - " . $firstdiff["title"] . " (" . count($value["difficulties"]) . " difficulties)\n";
// }
$proc_times = array();
foreach ($library as $key => $set)
{
	$tiempo = 0;
	foreach ($set["difficulties"] as $map)
	{
		$tiempo += $map["process_time"];
	}
	$proc_times[$key] = $tiempo;
}

arsort($proc_times);

foreach ($proc_times as $key => $tiempo)
{
	$value = $library[$key];
	$firstdiff = $value["difficulties"][array_key_first($value["difficulties"])];
	echo str_pad(round($tiempo, 5), 7, "0") . "s " . $value["id"] . ": " . $firstdiff["artist"] . " - " . $firstdiff["title"] . " (" . count($value["difficulties"]) . " difficulties)\n";
}
echo "</pre>";