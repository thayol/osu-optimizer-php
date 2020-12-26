<?php
// todo: whitelist / blacklist
// todo: repack osz file


// imports
require_once "libraries/osu_library.php";
require_once "libraries/optimizer.php";
require_once "libraries/utils.php";
require_once "temp/dump.php";

$lib = new osu_library();

$display = "start";

if ($lib->is_loaded())
{
	$display = "main";
}

function redirect($path)
{
	header('Location: ' . $path);
	exit(0); // TERMINATE CURRENT SCRIPT!
}

if (isset($_GET["rescan"]))
{
	$lib->scan_library(json_decode(file_get_contents("session/settings.json"), true)["osu_folder"]);
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
dump($lib, "lib");
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
		$proc_time += $map["parsing_time"] ?? 0;
	}
}
echo "<h3>Parse time: " . $proc_time . " seconds</h3>";
echo "<h3>Scan time: " . $lib->get_scan_time() . " seconds</h3>";
// foreach ($lib->get_library() as $mapset)
// {
	// echo '<div class="beatmapset">';
	// echo '<h2>Beatmapset: ';
	// if (!empty($mapset["id"])) echo $mapset["id"];
	// else echo '???';
	// echo '</h2>';
	// foreach ($mapset["difficulties"] as $beatmap)
	// {
		// $beatmap["format-2"] = mb_substr($beatmap["format"] ?? "v1", 1);
		// if (is_numeric(mb_substr($beatmap["format"] ?? "v1", 1)))
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