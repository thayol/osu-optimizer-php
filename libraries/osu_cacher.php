<?php
require_once "libraries/utils.php";

class osu_cacher
{
	public static $hash_function = "md5"; // crc32 was another option, but md5 produced better filesizes 
	
	private $original_root;
	private $cache_root;
	
	public function __construct(string $original_root, string $cache_root)
	{
		$this->original_root = $original_root;
		$this->cache_root = $cache_root;
		utils::make_directory($cache_root);
	}
	
	public function get_original_root() : string
	{
		return $this->original_root;
	}
	
	public function get_cache_root() : string
	{
		return $this->cache_root;
	}
	
	public function get_cached_path(string $path) : string
	{
		return str_replace($this->get_original_root(), $this->cache_root, $path) . ".json";
	}
	
	public function is_cached(string $path) : bool
	{
		return file_exists($this->get_cached_path($path));
	}
	
	public static function is_hash_invalid(array $json, string $hash) : bool
	{
		if (empty($hash)) return false; // empty means hash check has to be skipped
		if (empty($json["hash"])) return true; // json without a hash is invalid
		
		return $json["hash"] == $hash;
	}
	
	public function get_cache(string $path, string $hash="")// : array|bool // see you again in php8
	{
		if (!$this->is_cached($path)) return false;

		$json = utils::load_json($this->get_cached_path($path));
		
		if (empty($json)) return false; // cache had empty save
		if (self::is_hash_invalid($json, $hash)) return false;
		
		return $json;
	}
	
	public static function set_hash_if_not_present(array &$json, string $path) : void
	{
		if (!isset($json["hash"])) $json["hash"] = hash_file(self::$hash_function, $path);
	}
	
	public function set_cache(string $path, array $content) : void
	{
		$cache_path = $this->get_cached_path($path);
		self::set_hash_if_not_present($content, $path);
		
		utils::make_directory(dirname($cache_path));
		file_put_contents($cache_path, json_encode($content));
	}
}