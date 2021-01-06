<?php
require_once "libraries/osu_cacher.php";

class osu_parser
{
	private $cacher;
	
	public function __construct(osu_cacher $cacher)
	{
		$this->cacher = $cacher;
	}
	
	public static function convert_event_type(string $input) : string
	{
		// reconverting everything to the legacy notation
		// event types enum from lazer:
		// https://github.com/ppy/osu/blob/master/osu.Game/Beatmaps/Legacy/LegacyEventType.cs
		return str_replace(
			[ "Background", "Video", "Break", "Colour", "Sprite", "Sample", "Animation" ],
			[          "0",     "1",     "2",      "3",      "4",      "5",         "6" ],
			$input
		);
	}
	
	public static function reverse_event_type(string $input) : string
	{
		return str_replace(
			[          "0",     "1",     "2",      "3",      "4",      "5",         "6" ],
			[ "Background", "Video", "Break", "Colour", "Sprite", "Sample", "Animation" ],
			$input
		);
	}
	
	// fix backslash and double quotes
	public static function fix_filename(string $filename) : string
	{
		return trim(utils::to_unix_slashes($filename), "\"");
	}
	
	public static function file_ver_peppy(string $path)// : array|bool // see you in php8
	{
		if (!file_exists($path)) return false;
		$file = file_get_contents($path); // read normally
		if (empty($file)) return false;
		
		$bom = "\xef\xbb\xbf"; // the BOM character
		$file = str_replace($bom, "", $file); // removing BOM
		$file = str_replace("\r\n", "\n", $file); // win CRLF to unix LF
		$file = str_replace("\r", "\n", $file); // old mac CR to unix LF
		$lines = explode("\n", $file); // split using LF
		$lines = array_filter($lines); // remove empty lines
		$lines = array_values($lines); // reindex array
		
		return $lines;
	}
	
	public function parse_osu_file_format(string $path, bool $skip_cache = false)// : array|bool // see you again in php8
	{
		if (!file_exists($path)) return false;
		
		if (!$skip_cache)
		{
			$hash = hash_file(osu_cacher::$hash_function, $path);
			
			$cached = $this->cacher->get_cache($path, $hash);
			if ($cached !== false) return $cached;
		}
		
		$time_start = microtime(true); // measure parsing time
		
		$file = self::file_ver_peppy($path);
		if ($file === false) return false;
		
		$parsed = array();
		
		$osu_file_format_declaration = "osu file format ";
		if (stripos($file[0], $osu_file_format_declaration) !== false)
		{
			// random characters were at beginning of the .osu files...
			$format = $osu_file_format_declaration . explode($osu_file_format_declaration, $file[0])[1];
			unset($file[0]); // no longer needed
		}
		else if (pathinfo($path, PATHINFO_EXTENSION) == "osb")
		{
			$format = "storyboard";
		}
		else
		{
			$format = "unknown";
		}
		
		$parsed["format"] = $format;
		
		
		$current_section = false;
		$section_type = false;
		$delimiter = false;
		$variables = [ "keys" => array(), "values" => array()]; // variables for osb files $key=value pairs
		$needed_sections = [ "General", "Metadata", "Difficulty", "Variables", "Events", "HitObjects" ];
		foreach ($file as $key => $line)
		{
			if (mb_strpos($line, "[") === 0 && mb_strpos($line, "]") === (strlen($line)-1))
			{
				list($current_section, $section_type, $delimiter) = self::parse_osu_file_section_header($line, $needed_sections);
				
				continue; // this line has been analyzed
			}
			
			if (mb_strpos($line, "//") === 0 || // the editor puts hella lot of comments in the files
				$section_type === false)
			{
			}
			else if ($section_type == "key-value pairs")
			{
				// init empty array
				if (!isset($parsed[$current_section])) $parsed[$current_section] = array();
				
				$delimiter_position = mb_strpos($line, $delimiter);
				
				// old maps had random lines and lines with different delimiters (why?)
				if ($delimiter_position !== false)
				{
					$kv_key = mb_substr($line, 0, $delimiter_position);
					$kv_value = mb_substr($line, $delimiter_position + strlen($delimiter));
					
					if ($current_section == "Variables")
					{
						$variables["keys"][$kv_key] = $kv_key;
						$variables["values"][$kv_key] = $kv_value;
					}
					else
					{
						// after some thinking, keeping the original names was a good idea
						$parsed[$current_section][$kv_key] = $kv_value;	
					}
				}
			}
			else if ($section_type == "lists")
			{
				$list = explode($delimiter, $line);
				if ($current_section == "Events") // saving the whole thing would take up too much space
				{
					if (mb_strpos($line, " ") === 0 || mb_strpos($line, "_") === 0) continue; // skip storyboard details lines
					
					list($event_type, $source_files) = self::gather_source_files($list, $variables);
					
					if ($event_type === false)
					{
					}
					else if ($event_type == "Background")
					{
						$parsed["background"] = $source_files[0] ?? "";
					}
					else if ($event_type == "Video")
					{
						$parsed["video"] = $source_files[0] ?? "";
					}
					else
					{
						// init empty array
						if (!isset($parsed["storyboard"])) $parsed["storyboard"] = array();
						
						// add the elements to the storyboard
						foreach ($source_files as $source_file)
						{
							$parsed["storyboard"][] = $source_file;
						}
					}
				}
				else if ($current_section == "HitObjects") // saving the whole thing would take up too much space v2
				{
					$last = array_key_last($list);
					if (!empty($list[$last]) && mb_strpos($list[$last], ":") !== false)
					{
						$hitSample = explode(":", $list[$last]);
						$last_sample = array_key_last($hitSample);
						if (!empty($hitSample[$last_sample]))
						{
							$filename = $hitSample[$last_sample];
							$filename = self::fix_filename($filename);
							
							// some maps leave out the extensions
							// (and some maps have .wav filess pointing to .ogg files........)
							if (empty(pathinfo($filename, PATHINFO_EXTENSION)))
							{
								$filename = $filename . ".wav"; // just to signal it's an "audio file"
							}
							
							// init empty array
							if (!isset($parsed["hitsounds"])) $parsed["hitsounds"] = array();
							
							// add the element to the hitsounds
							$parsed["hitsounds"][] = $filename;
						}
					}
				}
				else
				{
					// init empty array
					if (!isset($parsed[$current_section])) $parsed[$current_section] = array();
					
					// just dump the non-events...
					// 
					// at the point of writing this
					// comment, this section will
					// never get used...
					$parsed[$current_section][] = $line;
				}
			}
		}
		unset($file); // remove the memory leak
		
		// storyboards/hitsounds are overloaded with dupes (renumber to make json export to arrays)
		if (!empty($parsed["storyboard"])) $parsed["storyboard"] = array_values(array_unique($parsed["storyboard"]));
		if (!empty($parsed["hitsounds"])) $parsed["hitsounds"] = array_values(array_unique($parsed["hitsounds"]));
		
		$time_end = microtime(true);
		$parsing_time = $time_end - $time_start;
		$parsed["parsing_time"] = $parsing_time;
		if (!empty($hash)) $parsed["hash"] = $hash;
		
		if (!$skip_cache)
		{
			$this->cacher->set_cache($path, $parsed);
		}
		
		return $parsed;
	}
	
	public static function gather_source_files(array $list, array $variables) : array
	{
		$list[0] = self::convert_event_type($list[0]);
		$event_type = $list[0];
		
		if (!in_array($event_type, [ "0", "1", "4", "5", "6" ]))
		{
			return array(false, false); // events that shouldn't be processed
		}
		
		$source_file = false;
		if ($event_type == "0" || $event_type == "1")
		{
			$source_file = $list[2];
		}
		else if ($event_type == "4" || $event_type == "5" || $event_type == "6")
		{
			$source_file = $list[3];
		}
		
		
		// use osb variables
		if (mb_strpos($source_file, "$") !== false)
		{
			$source_file = str_replace($variables["keys"], $variables["values"], $source_file);
		}
		
		if ($source_file !== false) $source_file = self::fix_filename($source_file);
		
		// fix leading dot slash
		if (mb_strpos($source_file, "./") === 0) $source_file = mb_substr($source_file, 2);
		
		if ($event_type == "6")
		{
			$extension = pathinfo($source_file, PATHINFO_EXTENSION) ?? "";
			$extension = !empty($extension) ? "." . $extension : ""; // r-append dot if set
			
			$directory = pathinfo($source_file, PATHINFO_DIRNAME) ?? "";
			$directory = !empty($directory) ? $directory. "/" : ""; // append slash if set
			
			$filename = pathinfo($source_file, PATHINFO_FILENAME);
			
			$frames = intval($list[6]);
			$source_files = array();
			for ($i = 0; $i < $frames; $i++) // fill the array
			{
				$resource_file = $directory . $filename . $i . $extension;
				$source_files[] = $resource_file;
			}
		}
		else
		{
			$source_files = array($source_file); // pack the single-source resources into an array
		}
		
		// artificially add a "good" extension
		foreach ($source_files as $source_key => $source_file)
		{
			// some maps leave out the extensions...
			if (empty(pathinfo($source_file, PATHINFO_EXTENSION)))
			{
				if ($event_type == 5)
				{
					$source_files[$source_key] = $source_file . ".wav"; // just to signal it's an "audio file"
				}
				else
				{
					$source_files[$source_key] = $source_file . ".png"; // just to signal it's an "image file"
				}
			}
		}
		
		return array(self::reverse_event_type($event_type), $source_files);
	}
	
	// works according to the osu file format v14 specifications
	public static function parse_osu_file_section_header(string $line, array $needed_sections = array()) : array
	{
		$current_section = str_replace([ "[", "]" ], "", $line);
		
		if (!in_array($current_section, $needed_sections))
		{
			// short circuit whitelist
			return array(false, false, false);
		}
		
		// peppy is retarded so i have to do this...
		$section_type = false;
		$delimiter = false;
		switch ($current_section)
		{
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
			case "Variables":
				$section_type = "key-value pairs";
				$delimiter = "=";
				break;
			case "Events":
			case "TimingPoints":
			case "HitObjects":
				$section_type = "lists"; // yes, listS because one list per line
				$delimiter = ",";
				break;
		}
		
		return array($current_section, $section_type, $delimiter);
	}
}