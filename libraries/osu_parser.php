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
	
	public function parse_osu_file_format(string $path, bool $skip_cache = false)// : array|bool // see you again in php8
	{
		if (!file_exists($path)) return false;
		
		if (!$skip_cache)
		{
			$cached = $this->cacher->get_cache($path, hash_file("md5", $path));
			if ($cached !== false) return $cached;
		}
		
		$time_start = microtime(true); // measure parsing time
		
		$file = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
		
		$parsed = array();
		
		// file format declaration
		if (stripos($file[0], "osu file format ") !== false)
		{
			// the ranker script had a bug where random "ZERO WIDTH NO-BREAK SPACE"
			// characters were at beginning of the .osu files
			$parsed["format"] = explode("osu file format ", $file[0])[1];
			unset($file[0]); // no longer needed
		}
		else if (pathinfo($path, PATHINFO_EXTENSION) == "osb")
		{
			$parsed["format"] = "storyboard";
		}
		
		$current_section = false;
		$section_type = false;
		$delimiter = false;
		$variables = [ "keys" => array(), "values" => array()]; // variables for osb files $key=value pairs
		$needed_sections = [ "General", "Metadata", "Difficulty", "Variables", "Events" ];
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
		
		// storyboards are overloaded with dupes (renumber to make json export to arrays)
		if (!empty($parsed["storyboard"])) $parsed["storyboard"] = array_values(array_unique($parsed["storyboard"]));
		
		$time_end = microtime(true);
		$parsing_time = $time_end - $time_start;
		$parsed["parsing_time"] = $parsing_time;
		
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
		
		// fix backslash and double quotes
		if ($source_file !== false) $source_file = trim(str_replace("\\", "/", $source_file), "\"");
		
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