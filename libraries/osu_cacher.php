<?php
class osu_cacher
{
	private $root;
	private $cache_root;
	
	public function __construct(string $root, string $cache_root)
	{
		$this->root = $root;
		$this->cache_root = $cache_root;
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
	
	public function get_cached(string $path, $hash=false)// : array|bool // see you again in php8
	{
		if (!is_cached($path)) return false;
		
		$raw = file_get_contents($this->get_cached_path($path));
		$json = json_decode($raw, true);
		
		if ($hash !== false && $hash != $json["hash"]) return false; // hash check failed
		
		return $json;
	}
	
	public function set_cache(string $path, array $content) : void
	{
		$encoded = json_encode($content);
		file_put_contents($this->get_cached_path($path), $encoded);
	}
}