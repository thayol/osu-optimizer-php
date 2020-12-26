<?php
class optimizer
{
	public static function blacken_image(string $file) : void
	{
		$black_png = "./resources/black.png";
		$black_jpg = "./resources/black.jpg";
		
		$ext = strtolower(pathinfo($file)["extension"]);
		
		if ($ext == "jpg" || $ext == "jpeg")
		{
			copy($black_jpg, $file);
		}
		else
		{
			copy($black_png, $file);
		}
	}
	
	public static function blacken_backgrounds(osu_library $library) : void
	{
		foreach ($library->get_backgrounds() as $file) self::blacken_image($file);
	}
	
	public static function remove_videos(osu_library $library)
	{
		foreach ($library->get_videos() as $file)
		{
			unlink($file);
		}
	}
	
	public static function remove_storyboards(osu_library $library) : void
	{
		foreach ($library->get_storyboards() as $file)
		{
			unlink($file);
		}
		
		foreach ($library->get_osb_files() as $file)
		{
			unlink($file);
		}
		
		$empty = self::build_empty_dir_list($library);
		foreach ($empty as $folder)
		{
			rmdir($folder);
		}
	}
	
	private static function build_removand_sublist(string $folder) : array
	{
		$queue = array();
		
		foreach (glob(utils::globsafe($folder) . "/*") as $file)
		{
			if (is_dir($file))
			{
				$queue = array_merge($queue, self::build_removand_sublist($file)); // recursion
			}
			else
			{
				$queue[] = strtolower($file);
			}
		}
		
		return $queue;
	}
	
	public static function build_removand_list(osu_library $library) : array
	{
		$queue = array();
		foreach ($library->get_folders() as $folder)
		{
			$queue = array_merge($queue, self::build_removand_sublist($folder));
		}
		return $queue;
	}
	
	public static function build_empty_dir_sublist(string $folder) : array
	{
		$queue = array();
		
		$glob = glob(utils::globsafe($folder) . "/*");
		if (empty($glob))
		{
			$queue[] = strtolower($file);
		}
		else
		{
			foreach ($glob as $file)
			{
				if (is_dir($file))
				{
					$queue = array_merge($queue, self::build_empty_dir_sublist($file));
				}
			}
		}
		
		return $queue;
	}
	
	public static function build_empty_dir_list(osu_library $library) : array
	{
		$queue = array();
		foreach ($library->get_folders() as $folder)
		{
			$queue = array_merge($queue, self::build_empty_dir_sublist($folder));
		}
		return $queue;
	}
	
	public static function build_excluded_list(osu_library $library) : array
	{
		$bg = $library->get_backgrounds();
		$vid = $library->get_videos();
		$sb = $library->get_storyboards();
		$a = $library->get_audiofiles();
		$sbf = $library->get_osb_files();
		$osf = $library->get_osu_files();
		$excluded = array_merge($bg, $vid, $sb, $a, $sbf, $osf);
		
		$lowercase = array();
		foreach ($excluded as $key => $value)
		{
			$lowercase[$key] = strtolower($value);
		}
		
		return $lowercase;
	}
	
	public static function remove_other(osu_library $library) : void
	{
		// $time_start = microtime(true);
		
		$removand = self::build_removand_list($library);
		$exclusions = self::build_excluded_list($library);
		$final = array_diff($removand, $exclusions);
		
		foreach ($final as $file)
		{
			unlink($file);
		}
		
		$empty = self::build_empty_dir_list($library);
		foreach ($empty as $folder)
		{
			rmdir($folder);
		}
		
		// $time_end = microtime(true);
		// $time = $time_end - $time_start;
		// echo " in {$time} seconds.";
	}
}