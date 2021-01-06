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
	
	public function get_scan_time() : float
	{
		return floatval($this->db["scanning_time"] ?? 0);
	}
	
	public function scan_library(string $root) : void
	{
		$time_start = microtime(true); // measure scanning time
		$root = utils::to_unix_slashes_without_trail($root);
		
		// giving a different library should always cause a full rescan
		if ($this->get_osu_root() !== $root) 
		{
			$this->rescan_library($root);
		}
		
		$library = $this->get_library();
		$library_folder = $this->get_library_folder();
		foreach ($library as $key => $entry)
		{
			if (!file_exists($library_folder . "/" . $key))
			{
				$this->unset_library_key($key);
			};
		}
		
		$glob = glob(utils::globsafe($library_folder) . "/*", GLOB_ONLYDIR);
		
		natsort($glob); // i like wasting your processing power
		foreach($glob as $folder)
		{
			$key = basename($folder);
			if (isset($library[$key]))
			{
				$this->scan_check_folder($folder);
			}
			else
			{
				$this->scan_add_folder($folder);
			}
		}
		
		$time_end = microtime(true);
		$scanning_time = $time_end - $time_start;
		$this->db["scanning_time"] = $scanning_time;
	}
	
	public function rescan_library(string $root) : void
	{
		$this->clear_library();
		$this->set_root($root); // update db entry
		$this->scan_library($root);
	}
	
	private function clear_library() : void
	{
		unset($this->db["library"]);
	}
	
	private function scan_check_folder(string $folder) : void
	{
		$key = basename($folder);
		
		$osu_glob = glob(utils::globsafe($folder) . "/*.osu");
		$osb_glob = glob(utils::globsafe($folder) . "/*.osb");
		$glob = array_merge($osu_glob, $osb_glob);
		
		$cacher = new osu_cacher($this->get_library_folder(), self::$cache_root);
		
		$changed = false;
		if (count($osu_glob) < 1) 
		{
			$changed = true;
		}
		
		foreach ($glob as $file)
		{
			if ($cacher->get_cache($file) === false)
			{
				$changed = true;
			}
		}
		
		if ($changed)
		{
			$this->unset_library_key($key);
			$this->scan_add_folder($folder);
		}
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
			$difficulty_key = basename($file);
		
			$difficulty = $parser->parse_osu_file_format($file);
			
			$difficulty["key"] = $difficulty_key;
			$difficulty["path"] = $file;
				
			$difficulties[$difficulty_key] = $difficulty;
		}
		
		// look for a beatmapset id
		$set_id = false;
		foreach ($difficulties as $difficulty)
		{
			if (!empty($difficulty[0]["Metadata"]["BeatmapSetID"]))
			{
				$id = intval($difficulty[0]["Metadata"]["BeatmapSetID"]);
			}
		}
		
		if ($set_id === false)
		{
			$temp = explode(" ", basename($folder))[0];
			$set_id = is_numeric($temp) ? $temp : "";
		}
		
		if ($set_id < 1) $set_id = false; // some are 0, some are -1, allowing positive only
		
		$entry = array(
			"key" => $key,
			"path" => $folder,
			"id" => $set_id,
			"difficulties" => $difficulties,
		);
		
		$this->add_to_library($key, $entry);
	}
	
	
	public function add_to_library(string $key, array $entry) : void
	{
		// init empty
		if (!isset($this->db["library"])) $this->db["library"] = array();
		
		$this->db["library"][$key] = $entry;
	}
	
	public function unset_library_key(string $key) : void
	{
		if (isset($this->db["library"][$key])) unset($this->db["library"][$key]);
	}
	
	public function get_osu_root() : string
	{
		return $this->db["root"] ?? "";
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
		return $this->get_osu_root() . "/" . self::$song_library_folder;
	}
	
	public function get_library() : array
	{
		return $this->db["library"] ?? array(); // todo: filter white/black list
	}
	
	public function get_full_library() : array
	{
		return $this->db["library"] ?? array();
	}
	
	public function is_loaded() : bool
	{
		return !empty($this->get_full_library()); // not empty == loaded
	}
	
	public function get_folders() : array
	{
		$db = $this->get_library();
		
		$folders = array();
		foreach ($db as $beatmapset)
		{
			if (file_exists($beatmapset["path"]))
			{
				$folders[] = $beatmapset["path"];
			}
		}
		
		$folders = array_unique($folders);
		
		return $folders;
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
					$path = $beatmapset["path"] . "/" . $beatmap["background"];
					// if (file_exists($path))
					$backgrounds[] = $path;
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
					$path = $beatmapset["path"] . "/" . $beatmap["video"];
					// if (file_exists($path))
					$videos[] = $path;
				}
			}
		}
		
		$videos = array_unique($videos);
		
		return $videos;
	}
	
	public function get_parsed_files_with_details() : array
	{
		$db = $this->get_library();
		
		$files = array();
		foreach ($db as $beatmapset)
		{
			// warning: key isn't globally unique!!
			foreach($beatmapset["difficulties"] as $key => $beatmap)
			{
				if (!empty($beatmap["path"]) && !empty($beatmap["format"]))
				{
					$files[] = $beatmap;
				}
			}
		}
		
		return $files;
	}
	
	public function get_parsed_files() : array
	{
		$files = $this->get_parsed_files_with_details();
		$paths = array();
		foreach ($files as $file)
		{
			$paths[] = $file["path"];
		}
		
		return $paths;
	}
	
	public function get_osu_files() : array
	{
		$files = $this->get_parsed_files_with_details();
		
		$osu_file_format_declaration = "osu file format ";
		
		$osu_files = array();
		foreach ($files as $file)
		{
			if (mb_strpos($file["format"], $osu_file_format_declaration) !== false)
			{
				$osu_files[] = $file["path"];
			}
		}
		
		return $osu_files;
	}
	
	public function get_osb_files() : array
	{
		$files = $this->get_parsed_files_with_details();
		
		$osb_files = array();
		foreach ($files as $file)
		{
			if ($file["format"] == "storyboard")
			{
				$osb_files[] = $file["path"];
			}
		}
		
		return $osb_files;
	}
	
	public function get_broken_files() : array
	{
		$files = $this->get_parsed_files_with_details();
		
		$broken_files = array();
		foreach ($files as $file)
		{
			if ($beatmap["format"] == "unknown")
			{
				$broken_files[] = $beatmap["path"];
			}
		}
		
		return $broken_files;
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
					$path = $beatmapset["path"] . "/" . $storyelement;
					// if (file_exists($path)) 
					$storyboards[] = $path;
				}
			}
		}
		
		$storyboards = array_unique($storyboards);
		
		return $storyboards;
	}
	
	public function get_hitsounds() : array
	{
		$db = $this->get_library();
		
		$hitsounds = array();
		foreach ($db as $beatmapset)
		{
			foreach($beatmapset["difficulties"] as $beatmap)
			{
				foreach ($beatmap["hitsounds"] ?? array() as $hitsound)
				{
					$path = $beatmapset["path"] . "/" . $hitsound;
					// if (file_exists($path))
					$hitsounds[] = $path;
				}
			}
		}
		
		$hitsounds = array_unique($hitsounds);
		
		return $hitsounds;
	}
	
	public function get_audiofiles() : array
	{
		$db = $this->get_library();
		
		$audiofiles = array();
		foreach ($db as $beatmapset)
		{
			foreach($beatmapset["difficulties"] as $beatmap)
			{
				if (!empty($beatmap["General"]["AudioFilename"]))
				{
					$path = $beatmapset["path"] . "/" . $beatmap["General"]["AudioFilename"];
					// if (file_exists($path)) 
					$audiofiles[] = $path;
				}
			}
		}
		
		$audiofiles = array_unique($audiofiles);
		
		return $audiofiles;
	}
	
	public function get_missing_files() : array
	{
		$db = $this->get_library();
		
		$missing = array();
		foreach ($db as $beatmapset)
		{
			foreach($beatmapset["difficulties"] as $beatmap)
			{
				if (!empty($beatmap["General"]["AudioFilename"]))
				{
					$path = $beatmapset["path"] . "/" . $beatmap["General"]["AudioFilename"];
					if (!file_exists($path)) $missing[] = $path;
				}
				
				if (!empty($beatmap["background"]))
				{
					$path = $beatmapset["path"] . "/" . $beatmap["background"];
					if (!file_exists($path)) $missing[] = $path;
				}
				
				if (!empty($beatmap["video"]))
				{
					$path = $beatmapset["path"] . "/" . $beatmap["video"];
					if (!file_exists($path)) $missing[] = $path;
				}
				
				foreach ($beatmap["storyboard"] ?? array() as $storyelement)
				{
					$path = $beatmapset["path"] . "/" . $storyelement;
					if (!file_exists($path)) $missing[] = $path;
				}
				
				foreach ($beatmap["hitsounds"] ?? array() as $hitsound)
				{
					$path = $beatmapset["path"] . "/" . $hitsound;
					if (!file_exists($path)) $missing[] = $path;
				}
				
				if (!empty($beatmap["format"]))
				{
					if (!file_exists($beatmap["path"]))
					{
						$missing[] = $beatmap["path"];
					}
				}
			}
		}
		
		$missing = array_unique($missing);
		
		return $missing;
	}
}