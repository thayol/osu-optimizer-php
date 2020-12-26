<?php
class osu_old_parser
{
	public static function scan_parse_osu_file(string $osu_file) : array
	{
		$time_start = microtime(true);
		$file = file($osu_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
		// osu files can get big, but why not load the full thing :3
		
		if (stripos($file[0], "osu file format") === false) return [ "error..." ];
		
		// some files had "ZERO WIDTH NO-BREAK SPACE" characters...
		$format = explode("osu file format ", $file[0])[1];
		unset($file[0]); // no longer needed
		
		$osu = array("Format" => $format);
		$current_section = "UnofficialComments";
		$osu[$current_section] = array();
		$storyboard = array();
		foreach ($file as $key => $line)
		{
			
			if (mb_strpos($line, "[") === 0 && mb_strpos($line, "]") === (strlen($line)-1))
			{
				$current_section = str_replace([ "[", "]" ], "", $line);
				if (!isset($osu[$current_section]))
				{
					$osu[$current_section] = array();
				}
				
				// peppy is retarded so i have to do this...
				switch ($current_section) {
					case "General":
					case "Editor":
						$section_type = "key-value pairs";
						$delimiter = ": ";
						break;
					case "Metadata":
					case "Difficulty":
						$section_type = "key-value pairs";
						$delimiter = ":"; // notice the missing space
						break;
					case "Colours":
						$section_type = "key-value pairs";
						$delimiter = " : "; // WHY WOULD YOU DO THIS IF YOU ALREADY HAVE TWO TYPES OF KEY-VALUE PAIRS???????????????????
						break;
					case "Events":
					case "TimingPoints":
					case "HitObjects":
						$section_type = "lists"; // yes, listS because one list per line
						$delimiter = ",";
						break;
					default:
						$section_type = "unknown";
				}
				
				continue;
			}
			
			
			// only parse the ones needed
			switch ($current_section) {
				case "General":
				case "Metadata":
				case "Difficulty":
				case "Events":
					$skip = false;
					break;
				default:
					$skip = true;
			}
			
			if ($skip) continue;
			
			
			if (mb_strpos($line, "//") === 0) // there were commented files that broke my script
			{
			}
			else if ($section_type == "key-value pairs")
			{
				$delimiter_position = mb_strpos($line, $delimiter);
				
				$value = mb_substr($line, $delimiter_position + strlen($delimiter_position));
				$osu[$current_section][mb_substr($line, 0, $delimiter_position)] = $value;
			}
			else if ($section_type == "lists")
			{
				$list = explode($delimiter, $line);
				
				// group events by type and start time
				if ($current_section == "Events")
				{
					if (mb_strpos($line, " ") === 0) continue; // skip storyboard details lines
					
					// event types: https://github.com/ppy/osu/blob/master/osu.Game/Beatmaps/Legacy/LegacyEventType.cs
					$list[0] = str_replace(
						[ "Background", "Video", "Break", "Colour", "Sprite", "Sample", "Animation" ],
						[ "0",          "1",     "2",     "3",      "4",      "5",      "6" ],
						$list[0]
					);
					
					if ($list[0] == "5" || $list[0] == "4")
					{
						$storyboard[] = trim(str_replace("\\", "/", $list[3]), "\"");
					}
					
					if ($list[0] == "6")
					{
						$story_base = pathinfo(trim(str_replace("\\", "/", $list[3]), "\""));
						if (empty($story_base["extension"])) $ext = "";
						else $ext = "." . $story_base["extension"];
						if (empty($story_base["dirname"])) $dir = "";
						else $dir = $story_base["dirname"] . "/";
						
						for ($i = 0; $i < intval($list[6]); $i++)
						{
							$storyboard[] = $dir . $story_base["filename"] . $i . $ext;
						}
					}
					
					if (!isset($osu[$current_section][$list[0]]))
					{
						$osu[$current_section][$list[0]] = array();
					}
					
					if (!isset($osu[$current_section][$list[0]][$list[1]]))
					{
						$osu[$current_section][$list[0]][$list[1]] = array();
					}
					
					$osu[$current_section][$list[0]][$list[1]][] = $list;
				}
				else
				{
					$osu[$current_section][] = $list;
				}
			}
			else
			{
				$osu[$current_section][] = $line; // just dump the unknown...
			}
		}
		unset($file); // remove the memory leak
		
		
		
		// return $osu;
		
		$set_id = $osu["Metadata"]["BeatmapSetID"] ?? false;
		if ($set_id === false)
		{
			$temp = explode(" ", basename(dirname($osu_file)))[0];
			if (is_numeric($temp))
			{
				$set_id = $temp;
			}
			else
			{
				$set_id = "";
			}
		}
		
		$background = str_replace("\\", "/", trim($osu["Events"][0][0][0][2] ?? "", "\""));
		$audio = str_replace("\\", "/", trim($osu["General"]["AudioFilename"] ?? "", "\""));
		$video = str_replace("\\", "/", trim($osu["Events"][1][array_key_first($osu["Events"][1] ?? array())][0][2] ?? "", "\""));
		$storyboard = array_unique($storyboard);
		
		$map_id = intval($osu["Metadata"]["BeatmapID"] ?? 0);
		if ($map_id < 1) $map_id = "";
		$return = array(
			"format" => $osu["Format"] ?? "",
			"title" => $osu["Metadata"]["Title"] ?? "",
			"artist" => $osu["Metadata"]["Artist"] ?? "",
			"mapper" => $osu["Metadata"]["Creator"] ?? "",
			"difficulty" => $osu["Metadata"]["Version"] ?? "",
			"tags" => $osu["Metadata"]["Tags"] ?? "",
			"background" => $background,
			"audio" => $audio,
			"video" => $video,
			"storyboard" => $storyboard,
			"id" => $map_id,
			"set_id" => $set_id,
		);
		
		$time_end = microtime(true);
		$time = $time_end - $time_start;
		$return["process_time"] = $time;
		$return["hash"] = hash_file("md5", $osu_file);
		
		return $return;
	}
	
	public static function scan_parse_osb_file(string $osb_file) : array
	{
		$time_start = microtime(true);
		$file = file($osb_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
		
		$storyboard = array();
		$current_section = "UnofficialComments";
		foreach ($file as $key => $line)
		{
			if (mb_strpos($line, "[") === 0 && mb_strpos($line, "]") === (strlen($line)-1))
			{
				$current_section = str_replace([ "[", "]" ], "", $line);
				continue;
			}
			
			if (!($current_section == "Events")) continue; // skip rest
			
			$list = explode(",", $line);
			
			$list[0] = str_replace(
				[ "Background", "Video", "Break", "Colour", "Sprite", "Sample", "Animation" ],
				[ "0",          "1",     "2",     "3",      "4",      "5",      "6" ],
				$list[0]
			);
			
			if ($list[0] == "5" || $list[0] == "4")
			{
				$storyboard[] = trim(str_replace("\\", "/", $list[3]), "\"");
			}
			
			if ($list[0] == "6")
			{
				$story_base = pathinfo(trim(str_replace("\\", "/", $list[3]), "\""));
				if (empty($story_base["extension"])) $ext = "";
				else $ext = "." . $story_base["extension"];
				if (empty($story_base["dirname"])) $dir = "";
				else $dir = $story_base["dirname"] . "/";
				
				for ($i = 0; $i < intval($list[6]); $i++)
				{
					$storyboard[] = $dir . $story_base["filename"] . $i . $ext;
				}
			}
				
			$temp = explode(" ", basename(dirname($osb_file)))[0];
			if (is_numeric($temp))
			{
				$set_id = $temp;
			}
			else
			{
				$set_id = "";
			}
		}
		
		$storyboard = array_unique($storyboard);
		
		$return = array(
			"format" => "storyboard",
			"storyboard" => $storyboard,
			"set_id" => $set_id,
		);
		
		$time_end = microtime(true);
		$time = $time_end - $time_start;
		$return["process_time"] = $time;
		$return["hash"] = hash_file("md5", $osb_file);
		
		return $return;
	}
}