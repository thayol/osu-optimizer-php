<?php
class optimizer
{
	public static function is_default_hitsound(string $path) : bool
	{
		$string = strtolower(basename($path));
		$pattern = '(taiko-)?(drum|normal|soft|nightcore)-((hit|slider)(normal|clap|finish|whistle|tick|slide))\d*\.([wW][aA][vV]|[mM][pP]3|[oO][gG][gG])';
		return mb_ereg_match($pattern, $string);
	}
	
	public static function is_skinnable_sound(string $path) : bool
	{
		$string = strtolower(basename($path));
		// take your time to appreciate this extremely performance penalty inducing line, then look for a skin image one too
		$pattern = '((heartbeat|seeya|welcome|key-(confirm|delete|movement|press)|back-button-(click|hover)|check-(on|off)|click-(close|short|short-confirm)|(menu|pause)(click|back|hit|-(back|charts|direct|edit|exit|freeplay|multiplayer|options|play|continue|retry|loop))?(-(click|hover))?|select-(expand|difficulty)|shutter|sliderbar|whoosh|match-(confirm|join|leave|(not)?ready|start)|metronomelow|count(\d+s)?|gos|readys|comboburst|combobreak|failsound|section(pass|fail)|applause|spinner(spin|bonus))|(taiko-)?(drum|normal|soft|nightcore)-((hit|slider)(normal|clap|finish|whistle|tick|slide)))-?\d*\.([wW][aA][vV]|[mM][pP]3|[oO][gG][gG])';
		return mb_ereg_match($pattern, $string);
	}
	
	public static function is_skinnable_image(string $path) : bool
	{
		$string = strtolower(basename($path));
		// take a look at the sound version too
		$pattern = '(mania-|taiko-?)?((key|note)(\d+|[sS])[dDhHlLtT]?|stage-(right|left|bottom|light|hint)|warningarrow|pippidon(clear|fail|idle|kiai)|bigcircle|drum-(inner|outer)|roll-(middle|end)|bar-(left|right)(-glow)?|mode-(osu|taiko|fruits|mania)(-med|-small)?|menu-(osu|background|snow|back|button-background)|button-(left|middle|right)|cursor(middle|trail|-(smoke|ripple))?|welcome_text|playfield|selection-(selectoptions(-over)?|mod-(novideo|autoplay|cinema|doubletime|easy|fadein|flashlight|halftime|hardrock|hidden|key(\d+|coop)|mirror|nightcore|nofail|perfect|random|relax2?|spunout|suddendeath|target|freemodallowed|touchdevice)|(mod[es]|random|options)(-over)?|tab)|options-offset-tick|play-(skip|unranked|warningarrow)|arrow-(pause|warning)|masking-border|multi-skipped|section-(fail|pass)|count|go|ready|hit|inputoverlay-(background|key)|pause-(overlay|back|continue|replay|retry)|scorebar-(bg|colou?r|ki|kidanger2?|marker)|score(entry)?(-(percent|x))?|ranking-([xXsSaAbBcCdD][hH]?|back|accuracy|graph|maxcombo|panel|perfect|title|replay|retry|winner)(-small)?|fail-background|volume-bg|comboburst(-(fruits|mania))?|default|(approach|hit)circle(select)?|followpoint|lighting[lLnN]?|slider((start|end)circle|(score)?point|followcircle|b(-(nd|spec))?|-(fail|flower-group))?|reversearrow|spinner-(approachcircle|rpm|clear|spin|background|circle|metre|osu|glow|bottom|top|middle2?|warning)|particle|fruit-(catcher-(idle|fail|kiai)|ryuuta|pear|grapes|apple|orange|bananas|drop)|star|coin|fps(-fps)?)(-?overlay)?(-?(\d+|comma|dot)[kg]?)*(@2x)?\.([pP][nN][gG]|[jJ][pP][eE]?[gG])(-effect)?';
		return mb_ereg_match($pattern, $string);
	}
	
	public static function is_skinnable_other(string $path) : bool
	{
		$string = strtolower(basename($path));
		if (strtolower($string) == "skin.ini") return true;
		if (mb_stripos($string, "k.ini") !== false) return true; // legacy mania ini
		return false;
	}
	
	public static function is_skinnable(string $path) : bool
	{
		return self::is_skinnable_sound($path) ||
			self::is_skinnable_image($path) ||
			self::is_skinnable_other($path);
	}
	
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
			if (file_exists($file)) unlink($file);
		}
	}
	
	public static function remove_storyboards(osu_library $library) : void
	{
		// exclude vital files if they are used in the storyboard
		$storyboards_safe = $library->get_storyboards();
		$storyboards_safe = array_diff($storyboards_safe, $library->get_backgrounds());
		$storyboards_safe = array_diff($storyboards_safe, $library->get_audiofiles());
		
		foreach ($storyboards_safe as $file)
		{
			if (file_exists($file)) unlink($file);
		}
		
		foreach ($library->get_osb_files() as $file)
		{
			if (file_exists($file)) unlink($file);
		}
	}
	
	private static function build_removand_sublist(array &$queue, string $folder, bool $single_level = false)
	{
		foreach (glob(utils::globsafe($folder) . "/*") as $file)
		{
			if (is_dir($file))
			{
				if (!$single_level) self::build_removand_sublist($queue, $file, false); // recursion
			}
			else
			{
				$queue[] = $file;
			}
		}
	}
	
	public static function build_removand_list(osu_library $library, bool $single_level = false) : array
	{
		$queue = array();
		
		$times_c = array();
		$time_1 = microtime(true);
		foreach ($library->get_folders() as $folder)
		{
			$time_start = microtime(true);
			self::build_removand_sublist($queue, $folder, $single_level);
			$time_end = microtime(true);
			$times_c[$folder] = $time_end - $time_start;
		}
		$time_2 = microtime(true);
		
		arsort($times_c);
		$times_c = array_splice($times_c, 0, 100);
		
		$time_3 = $time_2-$time_1;
		echo "{$time_3}<br />";
		echo "<pre>";
		print_r($times_c);
		echo "</pre>";
		
		return $queue;
	}
	
	public static function build_excluded_list(osu_library $library) : array
	{
		$background_files = $library->get_backgrounds();
		$video_files = $library->get_videos();
		$audio_files = $library->get_audiofiles();
		
		$storyboard_files = $library->get_storyboards();
		$hitsound_files = $library->get_hitsounds();
		
		// $osu_files = $library->get_osu_files();
		// $osb_files = $library->get_osb_files();
		$physical_excluded = $library->get_parsed_files();
		
		$essential_excluded = array_merge($background_files, $video_files, $audio_files);
		// $physical_excluded = array_merge($osb_files, $osu_files);
		$other_excluded = array_merge($storyboard_files, $hitsound_files);
		
		$excluded = array_merge($essential_excluded, $physical_excluded, $other_excluded);
		
		return $excluded;
	}
	
	public static function cut_extension(string $path) : string
	{
		$directory = pathinfo($path, PATHINFO_DIRNAME) ?? "";
		if (!empty($directory)) $directory .= "/"; // append slash if set
		
		return $directory . (pathinfo($path, PATHINFO_FILENAME) ?? "");
	}
	
	// because peppy thinks file extensions are wildcards:
	// you can have image.mp3 in storyboards in jfif
	// and you can have ogg vorbis hitsounds in mysound.wav.png.whatever
	public static function array_diff_ver_peppy(array &$files, array &$exclusions) : array
	{
		// osu! is case insensitive
		$files_lowercase = array();
		foreach ($files as $key => $value)
		{
			$files_lowercase[$key] = self::cut_extension(mb_strtolower($value));
		}
		
		$exclusions_lowercase = array();
		foreach ($exclusions as $key => $value)
		{
			$exclusions_lowercase[$key] = self::cut_extension(mb_strtolower($value));
		}
		
		// return values from the original, but only the ones that did not get removed
		return array_intersect_key($files, array_diff($files_lowercase, $exclusions_lowercase));
	}
	
	public static function remove_skins(osu_library $library) : void
	{
		$removand = self::build_removand_list($library, true);
		$exclusions = self::build_excluded_list($library);
		
		$junk_files = self::array_diff_ver_peppy($removand, $exclusions);
		
		foreach ($junk_files as $file)
		{
			if (self::is_skinnable_image($file) || self::is_skinnable_other($file))
			{
				// if (file_exists($file)) unlink($file);
				echo $file . "<br />";
			}
		}
		exit(0); // testing purposes
	}
	
	public static function remove_hitsounds(osu_library $library) : void
	{
		$removand = self::build_single_level_removand_list($library, true);
		$exclusions = self::build_excluded_list($library);
		
		$junk_files = self::array_diff_ver_peppy($removand, $exclusions);
		
		foreach ($junk_files as $file)
		{
			if (self::is_skinnable_sound($file))
			{
				if (file_exists($file)) unlink($file);
			}
		}
	}
	
	public static function remove_other(osu_library $library) : void
	{
		$removand = self::build_removand_list($library);
		$exclusions = self::build_excluded_list($library);
		
		$junk_files = self::array_diff_ver_peppy($removand, $exclusions);
		
		foreach ($junk_files as $file)
		{
			if (self::is_skinnable($file)) continue; // ignore default hitsounds
			// if (file_exists($file)) unlink($file);
			echo $file . "<br />";
		}
		exit(0); // testing purposes
	}
}