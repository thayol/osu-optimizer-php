<?php
class osu_cacher
{
	public static $hash_function = "md5";
	// public static $hash_function = "crc32";
	
	private $root;
	private $cache_root;
	
	public function __construct(string $root, string $cache_root)
	{
		$this->root = $root;
		$this->cache_root = $cache_root;
		if (!file_exists($cache_root)) mkdir($cache_root, 0777, true);
	}
	
	public function get_root() : string
	{
		return $this->root;
	}
	
	public function get_cache_root() : string
	{
		return $this->cache_root;
	}
	
	public function get_cached_path(string $path) : string
	{
		return str_replace($this->root, $this->cache_root, $path);
	}
	
	public function is_cached(string $path) : bool
	{
		return file_exists($this->get_cached_path($path));
	}
	
	public function get_cache(string $path, $hash=false)// : array|bool // see you again in php8
	{
		if (!$this->is_cached($path)) return false;

		$raw = file_get_contents($this->get_cached_path($path));
		$json = json_decode($raw, true);
		
		if (empty($json)) return false; // cache had empty save
		
		if ($hash !== false && $hash != ($json["hash"] ?? false)) return false; // hash check failed
		
		return $json;
	}
	
	public function set_cache(string $path, array $content) : void
	{
		$cache_path = $this->get_cached_path($path);
		$cache_dir = dirname($cache_path);
		if (!file_exists($cache_dir)) mkdir($cache_dir, 0777, true);
		
		if (!isset($content["hash"])) $content["hash"] = hash_file(self::$hash_function, $path);
		
		$encoded = json_encode($content);
		file_put_contents($cache_path, $encoded);
	}
}