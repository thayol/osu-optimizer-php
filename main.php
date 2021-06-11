<?php

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

// $home = "./index.php"; // if fully qualified path needed
$home = "./";

$settings_path = "session/settings.json";
$settings = new optimizer_settings($settings_path);
$lib = new osu_library();

$display = "start";

if (isset($_GET["settings"]) || empty($settings->get_osu_path()))
{
	$display = "start";
}
else if (isset($_GET["cleanup"]))
{
	$display = "cleanup";
}
else if (!empty($_GET["notice"]))
{
	$display = "notice";
	$notice = $_GET["notice"];
}
else if (!empty($_GET["warn"]) && !empty($_GET["forward"]))
{
	$display = "warn";
	$warn_forward = $_GET["forward"];
	$warn_backward = "./";
	$warn = $_GET["warn"];
}
else if ($lib->is_loaded())
{
	$display = "main";
}
else if (!empty($settings->get_osu_path())) // might be useless
{
	$display = "warn";
	$warn_forward = "./?scan";
	$warn_backward = "./?settings";
	$warn = "missing_db";
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
		redirect($home);
	}

	if (isset($_GET["scan"]))
	{
		$lib->scan_library($settings->get_osu_path());
		$lib->save_db();
		redirect($home);
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
		redirect($home);
	}

	if (isset($_GET["nosb"]))
	{
		@optimizer::remove_storyboards($lib);
		redirect($home);
	}

	if (isset($_GET["novid"]))
	{
		@optimizer::remove_videos($lib);
		redirect($home);
	}

	if (isset($_GET["noskin"]))
	{
		@optimizer::remove_skins($lib);
		redirect($home);
	}

	if (isset($_GET["nohit"]))
	{
		@optimizer::remove_hitsounds($lib);
		redirect($home);
	}

	if (isset($_GET["purify"]))
	{
		@optimizer::remove_other($lib);
		redirect($home);
	}

	if (isset($_GET["nuke"]))
	{
		@optimizer::full_nuke($lib);
		redirect($home);
	}

	$options = array(
		[ "./?settings", "Settings", "Go back to the setup/settings screen.", "Settings" ],
		[ "./?scan", "Scan", "Only scan for changes.", "Scan" ],
		[ "./?rescan", "Force rescan", "Fully rescan the library. <i>(cached)</i>", "Rescan", "Slow" ],
		[ "./?blacken", "Remove backgrounds", "Replace the background files with 1x1 black images. So that osu! stays CAAAALLLLLLMMMMMMMM! And so that you can play without using the \"Background Dim\" setting." ],
		[ "./?novid", "Remove videos", "" ],
		[ "./?nosb", "Remove storyboards", "" ],
		[ "./?noskin", "Remove beatmap skins", "Does not remove hitsounds &amp; storyboard elements.", null, "Slow" ],
		[ "./?nohit", "Remove custom hitsounds", "Does not remove storyboard elements.", null, "Slow" ],
		[ "./?purify", "Remove junk files", "Remove everything that isn't referenced in .osu or .osb files.", null, "Very slow" ],
		[ "./?nuke", "NUKE", "Remove everything that isn't .osu or a referenced audio/background file.<br /><br /><b>Note:</b> old/bad maps might lose vital elements!", "NUKE" ],
		[ "./?warn=repack&forward=" . urlencode("./?repack&all"), "Repack all", "Repack all maps to .osz files. Note: you should not share exported maps; always use official osu! links.", "Repack ALL", "EXTREMELY slow" ],
		[ "./?cleanup", "Clean up", "Reset your settings, delete unused files, or clear cache. This does not touch your osu! folder. Useful after exporting many maps individually.", "Choose what to clean up" ],
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
	$osu_root = $lib->get_osu_root();
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
else if ($display == "cleanup")
{
	$did_cleanup = false;
	
	if (isset($_GET["cleanup_all"])) // artificial get injections
	{
		$_GET["cleanup_repacker"] = 1;
		$_GET["cleanup_cache"] = 1;
		$_GET["cleanup_settings"] = 1;
		$_GET["cleanup_db"] = 1;
	}
	
	if (isset($_GET["cleanup_repacker"]))
	{
		$did_cleanup = true;
		@optimizer::cleanup_dir("session/osz");
	}
	
	if (isset($_GET["cleanup_cache"]))
	{
		$did_cleanup = true;
		@optimizer::cleanup_dir("session/cache", true);
	}
	
	if (isset($_GET["cleanup_settings"]))
	{
		$did_cleanup = true;
		$file = $settings->get_settings_path();
		if (file_exists($file)) unlink($file);
	}
	
	if (isset($_GET["cleanup_db"]))
	{
		$did_cleanup = true;
		$file = $lib->get_db_file_location();
		if (file_exists($file)) unlink($file);
	}
	
	$options = array(
		[ "./?cleanup&cleanup_repacker", "Clean up repacker", "Delete the repacked .osz files. Moved, copied or \"downloaded\" files will not be affected." ],
		[ "./?cleanup&cleanup_cache", "Clear cache", "Clear the beatmap cache. This should eliminate possible parsing problems, but it will make your next scan take a long time." ],
		[ "./?cleanup&cleanup_db", "Reset database", "A roundabout way to force a rescan...", null, "Force rescan" ],
		[ "./?cleanup&cleanup_settings", "Reset settings", "You will have to re-enter your osu! directory!" ],
		[ "./?cleanup&cleanup_all", "Full reset", "Fully reset this program and its settings." ],
	);
	
	if ($did_cleanup)
	{
		$options = array(
			[ "./", "Cleanup done!", null, "OK", null, "A long loading screen may follow depending on what you cleaned." ],
		);
	}
	
	$te->set_block_template("CONTENT", "ACTIONS");
	
	foreach ($options as $option)
	{
		$link = $option[0] ?? "#";
		$name = $option[1] ?? "Unnamed action";
		$description = $option[2] ?? "";
		$button = $option[3] ?? "Clean!";
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
}
else if ($display == "warn")
{
	$warn_upper = strtoupper($warn);
	$te->set_block("WARN_FORWARD_LINK", $warn_forward);
	$te->set_block("WARN_BACKWARD_LINK", $warn_backward);
	$te->set_block_template("WARN_CONTENT", "WARN_{$warn_upper}");
	$te->set_block_template("CONTENT", "WARN");
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