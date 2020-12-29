<?php
// todo: whitelist / blacklist
// todo: dupe checker
// todo: stardiff deleter
// todo: mode deleter

// git does not like empty folders
if (!file_exists("session")) mkdir("session");
if (!file_exists("session/cache")) mkdir("session/cache");
if (!file_exists("session/osz")) mkdir("session/osz");


// imports
require_once "libraries/osu_library.php";
require_once "libraries/optimizer.php";
require_once "libraries/optimizer_settings.php";
require_once "libraries/utils.php";
require_once "libraries/template_engine.php";
require_once "temp/dump.php";


$settings_path = "session/settings.json";
$settings = new optimizer_settings($settings_path);
$lib = new osu_library();

$display = "start";

if (isset($_GET["settings"]) || empty($settings->get_osu_path()))
{
	// $display = "start";
}
else if (!empty($_GET["notice"]))
{
	$display = "notice";
	$notice = $_GET["notice"];
}
else if (!empty($_GET["warn"]) && !empty($_GET["forward"]))
{
	$display = "warn";
	$forward = $_GET["forward"];
	$warn = $_GET["warn"];
}
else if ($lib->is_loaded())
{
	$display = "main";
}

function redirect($path)
{
	header('Location: ' . $path);
	exit(0); // TERMINATE CURRENT SCRIPT!
}

if (!empty($settings->get_osu_path()))
{
	if (isset($_GET["rescan"]))
	{
		$lib->rescan_library($settings->get_osu_path());
		$lib->save_db();
		redirect("./");
	}

	if (isset($_GET["scan"]))
	{
		$lib->scan_library($settings->get_osu_path());
		$lib->save_db();
		redirect("./");
	}
}

$te = new template_engine();
if ($display == "main")
{
	if (isset($_GET["repack"]))
	{
		if (!empty($_GET["key"]))
		{
			$key = $_GET["key"];
			$dl = @optimizer::repack($lib, $key);
			redirect("./" . $dl);
		}
		else if (isset($_GET["all"]))
		{
			@optimizer::repack_all($lib);
			redirect("./?notice=repack");
		}
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

	if (isset($_GET["noskin"]))
	{
		@optimizer::remove_skins($lib);
		redirect("./");
	}

	if (isset($_GET["nohit"]))
	{
		@optimizer::remove_hitsounds($lib);
		redirect("./");
	}

	if (isset($_GET["purify"]))
	{
		@optimizer::remove_other($lib);
		redirect("./");
	}

	if (isset($_GET["nuke"]))
	{
		@optimizer::full_nuke($lib);
		redirect("./");
	}

	$options = array(
		[ "./?settings", "Settings", "Go back to the setup/settings screen." ],
		[ "./?scan", "Scan", "Only scan for changes.", "Scan" ],
		[ "./?rescan", "Force rescan", "Fully rescan the library. <i>(cached)</i>", "Rescan", "Slow" ],
		[ "./?blacken", "Remove backgrounds", "Replace the background files with 1x1 black images." ],
		[ "./?novid", "Remove videos", "" ],
		[ "./?nosb", "Remove storyboards", "" ],
		[ "./?noskin", "Remove beatmap skins", "Does not remove hitsounds &amp; storyboard elements.", null, "Slow" ],
		[ "./?nohit", "Remove custom hitsounds", "Does not remove storyboard elements.", null, "Slow" ],
		[ "./?purify", "Remove junk files", "Remove everything that isn't referenced in .osu or .osb files.", null, "Very slow" ],
		[ "./?nuke", "NUKE", "Remove everything that isn't .osu or a referenced audio/background file. Note: old/bad maps might lose vital elements!" ],
		[ "./?warn=repack&forward=" . urlencode("./?repack&all"), "Repack all", "Repack all maps to .osz files. Note: you should not share exported maps; always use official osu! links.", "Repack ALL", "EXTREMELY slow" ],
		[ "./splitter.php?page=1", "TBD", null, "Explore" ],
	);
	
	$parse_time = 0;
	foreach ($lib->get_library() as $set)
	{
		foreach ($set["difficulties"] as $map)
		{
			$parse_time += $map["parsing_time"] ?? 0;
		}
	}
	
	$mapset_count = count($lib->get_library());
	$osu_root = $lib->get_root();
	$parse_time = round($parse_time, 3);
	$scan_time = round($lib->get_scan_time(), 3);
	
	$te->set_block_template("CONTENT", "MAIN");
	$te->set_block("MAIN_MAPSET_COUNT", $mapset_count);
	$te->set_block("MAIN_FOLDER_LOCATION", $osu_root);
	$te->set_block("MAIN_PARSE_TIME", $parse_time);
	$te->set_block("MAIN_SCAN_TIME", $scan_time);
	
	foreach ($options as $option)
	{
		$link = $option[0] ?? "#";
		$name = $option[1] ?? "Unnamed action";
		$description = $option[2] ?? "";
		$button = $option[3] ?? "Do it!";
		$name_note = $option[4] ?? "";
		$link_note = $option[5] ?? "";
		
		if (!empty($name_note)) $name_note = "({$name_note})";
		if (!empty($link_note)) $link_note = "({$link_note})";
		
		$te->append_argumented_block("MAIN_OPTIONS", "MAIN_OPTION", [
			"MAIN_OPTION_LINK" => $link,
			"MAIN_OPTION_NAME" => $name,
			"MAIN_OPTION_DESCRIPTION" => $description,
			"MAIN_OPTION_NAME_NOTE" => $name_note,
			"MAIN_OPTION_LINK_NOTE" => $link_note,
			"MAIN_OPTION_BUTTON" => $button,
		]);
	}
	
	// dump($lib, "lib");
}
else if ($display == "notice")
{
	$notice_upper = strtoupper($notice);
	$te->set_block_template("CONTENT", "NOTICE_{$notice_upper}");
}
else if ($display == "warn")
{
	$warn_upper = strtoupper($warn);
	$te->set_block("WARN_FORWARD_LINK", $forward);
	$te->set_block_template("CONTENT", "WARN_{$warn_upper}");
}
else if ($display == "start")
{
	if (!empty($_POST["osu_folder"]))
	{
		$settings->set_osu_path($_POST["osu_folder"]);
		$settings->save();
		redirect("./?scan");
	}
	
	$te->set_block_template("CONTENT", "SETTINGS");
	if (!empty($settings->get_osu_path()))
	{
		$te->set_block_template("SETTINGS_BACK", "SETTINGS_BACK_SOURCE");
	}
	if (!$lib->is_loaded())
	{
		$te->set_block_template("SETTINGS_FIRSTRUN", "SETTINGS_FIRSTRUN_SOURCE");
	}
}
	
echo $te->get_html();