<?php
// todo: whitelist / blacklist
// todo: repack osz file
set_time_limit(300000);

require "libraries/osu_library.php";
require "libraries/optimizer.php";
require "libraries/utils.php";
require "temp/dump.php";

$lib = new osu_library();

$root = "S:/test";

function redirect($path)
{
	header('Location: ' . $path);
	exit(0); // TERMINATE CURRENT SCRIPT!
}

if (isset($_GET["rescan"]))
{
	$lib->scan_library($root);
	$lib->save_db();
	redirect("./");
}

if (isset($_GET["blacken"]))
{
	@optimizer::blacken_backgrounds($lib);
	redirect("./");
}

if (isset($_GET["nosb"]))
{
	@optimizer::remove_storyboards($lib);
	redirect("./");
}

if (isset($_GET["novid"]))
{
	@optimizer::remove_videos($lib);
	redirect("./");
}

if (isset($_GET["purify"]))
{
	@optimizer::remove_other($lib);
	redirect("./");
}

$start = file_get_contents("resources/start.html");
$start = str_replace("{{ STYLE }}", file_get_contents("resources/style.css"), $start);
echo $start;
// dump($lib, "lib");
echo '<a href="./?rescan">[Rescan]</a> ';
echo '<a href="./?blacken">[Blacken]</a> ';
echo '<a href="./?nosb">[NoSB]</a> ';
echo '<a href="./?novid">[Novid]</a> ';
echo '<a href="./?purify">[Purify]</a> ';
echo '<br /><br /><br /><a href="./splitter.php?page=1">[Explore]</a> ';
echo "<h2>" . count($lib->get_library()) . " mapsets loaded.</h2>";
echo "<h3>osu! folder: " . $lib->get_root() . "</h3>";

$proc_time = 0;
foreach ($lib->get_library() as $set)
{
	foreach ($set["difficulties"] as $map)
	{
		$proc_time += $map["process_time"];
	}
}
echo "<h3>Process time: " . $proc_time . " seconds</h3>";
// foreach ($lib->get_library() as $mapset)
// {
	// echo '<div class="beatmapset">';
	// echo '<h2>Beatmapset: ';
	// if (!empty($mapset["id"])) echo $mapset["id"];
	// else echo '???';
	// echo '</h2>';
	// foreach ($mapset["difficulties"] as $beatmap)
	// {
		// $beatmap["format-2"] = substr($beatmap["format"] ?? "v1", 1);
		// if (is_numeric(substr($beatmap["format"] ?? "v1", 1)))
		// {
			// echo '<div class="beatmap">';
			// echo '<h3>Title: ' . $beatmap["title"];
			// if (!empty($beatmap["id"])) echo '<br />ID: ' . $beatmap["id"];
			// echo '<br />Artist: ' . $beatmap["artist"];
			// echo '<br />Mapper: ' . $beatmap["mapper"];
			// echo '<br />Format: ' . $beatmap["format"];
			// echo '</h3>';
			// echo '<img class="small-background" src="./proxy.php?path=' . urlencode($mapset["path"] . "/" . $beatmap["background"]) . '" />';
			// echo '</div>';
		// }
		// else
		// {
			// echo '<div class="beatmap">';
			// echo '<h3>Extra: ' . $beatmap["format"];
			// echo '</h3>';
			// echo '</div>';
		// }
	// }
	// echo '</div>';
// }