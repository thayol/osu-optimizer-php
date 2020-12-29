<?php
class optimizer_settings
{
	public $path;
	
	private $osu_path = "";
	
	public function __construct(string $path)
	{
		$this->path = $path;
		if (file_exists($path))
		{
			$this->load();
		}
	}
	
	public function load()
	{
		$raw = file_get_contents($this->path);
		$json = json_decode($raw, true);
		
		$this->osu_path = $json["osu_path"] ?? "";
	}
	
	public function save()
	{
		$json = array();
		$json["osu_path"] = $this->osu_path;
		
		$raw = json_encode($json);
		file_put_contents($this->path, $raw);
	}
	
	public function set_osu_path($path) : void
	{
		$sanitized = str_replace("\\", "/", $path);
		$sanitized = rtrim($sanitized, "/");
		if (file_exists($path))
		{
			$this->osu_path = $sanitized;
		}
	}
	
	public function get_osu_path() : string
	{
		return $this->osu_path;
	}
	
	public function get_settings_path() : string
	{
		return $this->path;
	}
}