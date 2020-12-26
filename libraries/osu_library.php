<?php
class osu_library
{
	private static $song_library_folder = "Songs";
	private static $db_file_location = "session/optimizer_data.json";
	
	private $db;
	
	public function __construct(string $db_file = NULL)
	{
		if ($db_file === NULL) $db_file = self::$db_file_location;
		$this->load_db($db_file);
	}
	
	public function scan_library(string $root) : void
	{
		$root = str_ireplace("\\", "/", $root); // fuck windows backslash
		$root = rtrim($root, "/"); // remove trailing slash(es)
		
		$this->set_root($root); // update db entry
		
		foreach(glob($this->get_library_folder() . "/*", GLOB_ONLYDIR) as $folder)
		{
			$this->scan_add_folder($folder);
		}
	}
	
	public function rescan_library(string $root) : void
	{
		$this->clear_library_cache();
		$this->scan_library($root);
	}
	
	private function clear_library_cache() : void
	{
		unset($this->db["library"]);
	}
	
	private function scan_add_folder(string $folder) : void
	{
		$key = basename($folder);
		
		$difficulties = array();
		
		$osu_glob = glob(utils::globsafe($folder) . "/*.osu");
		// if (count($osu_glob) < 1) return; // nothing to do here...
		
		foreach ($osu_glob as $osu_file)
		{
			if (isset($this->db["library"][$key][$osu_file]["hash"]))
			{
				if ($this->db["library"][$key][$osu_file]["hash"] == hash_file("md5", $osu_file)) continue;
				else unset($this->db["library"][$key][$osu_file]); // recalculate
			}
			
			$diff = $this->scan_parse_osu_file($osu_file);
			$diff["key"] = basename($osu_file);
			$diff["path"] = $osu_file;
			
			$difficulties[basename($osu_file)] = $diff;
		}
		
		foreach (glob(utils::globsafe($folder) . "/*.osb") as $osb_file)
		{
			if (isset($this->db["library"][$key][$osb_file]["hash"]))
			{
				if ($this->db["library"][$key][$osb_file]["hash"] == hash_file("md5", $osb_file)) continue;
				else unset($this->db["library"][$key][$osb_file]); // recalculate
			}
			
			$diff = $this->scan_parse_osb_file($osb_file);
			$diff["key"] = basename($osb_file);
			$diff["path"] = $osb_file;
			
			$difficulties[basename($osb_file)] = $diff;
		}
		
		$temp = explode(" ", basename($folder))[0];
		if (is_numeric($temp))
		{
			$set_id = $temp;
		}
		else
		{
			$set_id = "";
		}
		
		$entry = array(
			"key" => $key,
			"path" => $folder,
			"id" => $set_id,
			"difficulties" => $difficulties,
		);
		
		if (!isset($this->db["library"])) $this->db["library"] = array();
		$this->db["library"][$key] = $entry;
	}
	
	private function scan_parse_osu_file(string $osu_file) : array
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
			
			if (strpos($line, "[") === 0 && strpos($line, "]") === (strlen($line)-1))
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
			
			
			if (strpos($line, "//") === 0) // there were commented files that broke my script
			{
			}
			else if ($section_type == "key-value pairs")
			{
				$delimiter_position = strpos($line, $delimiter);
				
				$value = substr($line, $delimiter_position + strlen($delimiter_position));
				$osu[$current_section][substr($line, 0, $delimiter_position)] = $value;
			}
			else if ($section_type == "lists")
			{
				$list = explode($delimiter, $line);
				
				// group events by type and start time
				if ($current_section == "Events")
				{
					if (strpos($line, " ") === 0) continue; // skip storyboard details lines
					
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
	
	private function scan_parse_osb_file(string $osb_file) : array
	{
		$time_start = microtime(true);
		$file = file($osb_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
		
		$storyboard = array();
		$current_section = "UnofficialComments";
		foreach ($file as $key => $line)
		{
			if (strpos($line, "[") === 0 && strpos($line, "]") === (strlen($line)-1))
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
	
	
	public function get_root() : string
	{
		return $this->db["root"];
	}
	
	public function set_root(string $root) : void
	{
		$this->db["root"] = $root;
	}
	
	public function save_db() : void
	{
		$raw_json = json_encode($this->db); // re-encode the db
		file_put_contents($this->get_db_file_location(), $raw_json); // save to the file
	}
	
	public function load_db(string $db_file) : void
	{
		if (file_exists($db_file))
		{
			// load json db to the db array
			$raw_json = file_get_contents($db_file);
			$this->db = json_decode($raw_json, true);
		}
		else
		{
			$this->db = array(); // empty db
		}
		
		$this->set_db_file_location($db_file); // override the loaded location
	}
	
	public function set_db_file_location(string $db_file) : void
	{
		$this->db["db_file"] = $db_file;
	}
	
	public function get_db_file_location() : string
	{
		if (empty($this->db["db_file"]))
		{
			$this->set_db_file_location(self::$db_file_location);
			return self::$db_file_location;
		}
		else
		{
			return $this->db["db_file"];
		}
	}
	
	public function get_library_folder() : string
	{
		return $this->get_root() . "/" . self::$song_library_folder;
	}
	
	public function get_library() : array
	{
		return $this->db["library"] ?? array(); // todo: filter white/black list
	}
	
	public function get_full_library() : array
	{
		return $this->db["library"];
	}
	
	public function get_folders() : array
	{
		$db = $this->get_library();
		
		$folders = array();
		foreach ($db as $beatmapset)
		{
			$backgrounds[] = $beatmapset["path"];
		}
		
		$backgrounds = array_unique($backgrounds);
		
		return $backgrounds;
	}
	
	public function get_backgrounds() : array
	{
		$db = $this->get_library();
		
		$backgrounds = array();
		foreach ($db as $beatmapset)
		{
			foreach($beatmapset["difficulties"] as $beatmap)
			{
				if (!empty($beatmap["background"]))
				{
					$backgrounds[] = $beatmapset["path"] . "/" . $beatmap["background"];
				}
			}
		}
		
		$backgrounds = array_unique($backgrounds);
		
		return $backgrounds;
	}
	
	public function get_videos() : array
	{
		$db = $this->get_library();
		
		$videos = array();
		foreach ($db as $beatmapset)
		{
			foreach($beatmapset["difficulties"] as $beatmap)
			{
				if (!empty($beatmap["video"]))
				{
					$videos[] = $beatmapset["path"] . "/" . $beatmap["video"];
				}
			}
		}
		
		$videos = array_unique($videos);
		
		return $videos;
	}
	
	public function get_osu_files() : array
	{
		$db = $this->get_library();
		
		$osu_files = array();
		foreach ($db as $beatmapset)
		{
			foreach($beatmapset["difficulties"] as $beatmap)
			{
				if (!empty($beatmap["format"]) && $beatmap["format"] != "storyboard")
				{
					$osu_files[] = $beatmap["path"];
				}
			}
		}
		
		$osu_files = array_unique($osu_files);
		
		return $osu_files;
	}
	
	public function get_storyboard_files() : array
	{
		$db = $this->get_library();
		
		$storyboard_files = array();
		foreach ($db as $beatmapset)
		{
			foreach($beatmapset["difficulties"] as $beatmap)
			{
				if (!empty($beatmap["format"]) && $beatmap["format"] == "storyboard")
				{
					$storyboard_files[] = $beatmap["path"];
				}
			}
		}
		
		$storyboard_files = array_unique($storyboard_files);
		
		return $storyboard_files;
	}
	
	public function get_storyboards() : array
	{
		$db = $this->get_library();
		
		$storyboards = array();
		foreach ($db as $beatmapset)
		{
			foreach($beatmapset["difficulties"] as $beatmap)
			{
				foreach ($beatmap["storyboard"] ?? array() as $storyelement)
				{
					$storyboards[] = $beatmapset["path"] . "/" . $storyelement;
				}
			}
		}
		
		$storyboards = array_unique($storyboards);
		
		return $storyboards;
	}
	
	public function get_audiofiles() : array
	{
		$db = $this->get_library();
		
		$audiofiles = array();
		foreach ($db as $beatmapset)
		{
			foreach($beatmapset["difficulties"] as $beatmap)
			{
				if (!empty($beatmap["audio"]))
				{
					$audiofiles[] = $beatmapset["path"] . "/" . $beatmap["audio"];
				}
			}
		}
		
		$audiofiles = array_unique($audiofiles);
		
		return $audiofiles;
	}
}