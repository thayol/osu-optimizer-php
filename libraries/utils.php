<?php
class utils
{
	public static function globsafe(string $path) : string
	{
		$path = str_replace('[', '\[', $path);
		$path = str_replace(']', '\]', $path);
		$path = str_replace('\[', '[[]', $path);
		$path = str_replace('\]', '[]]', $path);
		return $path;
	}
	
	public static function recursive_zip_map($zip, $root, $path)
	{
		$files = glob($path . "/*");
		foreach ($files as $file)
		{
			if (is_dir($file))
			{
				self::recursive_zip_map($zip, $root, $file);
			}
			else
			{
				$relative = str_replace(array("@-".$root."/", "@-".$root), "", "@-".$file);
				$zip->addFile($file, $relative);
			}
		}
	}
}