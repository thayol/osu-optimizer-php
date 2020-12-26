<?php
require_once "libraries/osu_parser.php";
require_once "libraries/osu_cacher.php";

class osu_library
{
	private static $song_library_folder = "Songs";
	private static $db_file_location = "session/optimizer_data.json";
	private static $cache_root = "session/cache";
	
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
		
		$this->clear_library();
		$glob = glob($this->get_library_folder() . "/*", GLOB_ONLYDIR);
		
		natsort($glob); // i like wasting your processing power
		foreach($glob as $folder)
		{
			$this->scan_add_folder($folder);
		}
	}
	
	public function rescan_library(string $root) : void
	{
		$this->clear_library();
		$this->scan_library($root);
	}
	
	private function clear_library() : void
	{
		unset($this->db["library"]);
	}
	
	private function scan_add_folder(string $folder) : void
	{
		$key = basename($folder);
		
		$difficulties = array();
		
		$osu_glob = glob(utils::globsafe($folder) . "/*.osu");
		if (count($osu_glob) < 1) return; // nothing to do here...
		$osb_glob = glob(utils::globsafe($folder) . "/*.osb");
		$glob = array_merge($osu_glob, $osb_glob);
		
		$cacher = new osu_cacher($this->get_library_folder(), self::$cache_root);
		$parser = new osu_parser($cacher);
		
		foreach ($glob as $file)
		{
			$difficulty = $parser->parse_osu_file_format($file);
			$difficulty["key"] = basename($file);
			$difficulty["path"] = $file;
			
			$difficulties[basename($file)] = $difficulty;
		}
		
		$temp = explode(" ", basename($folder))[0];
		$set_id = is_numeric($temp) ? $temp : "";
		
		$entry = array(
			"key" => $key,
			"path" => $folder,
			"id" => $set_id,
			"difficulties" => $difficulties,
		);
		
		// if (!isset($this->db["library"])) $this->db["library"] = array();
		
		// init empty
		if (!isset($this->db["library"])) $this->db["library"] = array();
		
		$this->db["library"][$key] = $entry;
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