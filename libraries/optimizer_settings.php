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
	
	public function load() : void
	{
		$json = utils::load_json($this->path);
		
		$this->osu_path = $json["osu_path"] ?? "";
	}
	
	public function save() : void
	{
		$json = [ "osu_path" => $this->osu_path ];
		
		file_put_contents($this->path, json_encode($json));
	}
	
	public function set_osu_path($path) : void
	{
		if (file_exists($path))
		{
			$this->osu_path = utils::to_unix_slashes_without_trail($path);
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