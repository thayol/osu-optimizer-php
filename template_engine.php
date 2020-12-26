<?php
class template_engine
{
	public static $base_block = "BASE";
	
	public $blocks = array();
	public $templates = array();
	
	private $block_pattern = '/\w*/';
	private $block_pattern_chars = '[\w,_,-]*';
	private $block_pattern_prefix = '{{ ';
	private $block_pattern_suffix = ' }}';
	private $source_files = array();
	private $template_folder = "./templates/";
	
	private function init_templates(array $source_files) : void
	{
		foreach ($source_files as $name => $source)
		{
			$this->templates[$name] = file_get_contents($source);
		}
	}
	
	private function discover_templates(string $template_folder) : void
	{
		foreach (glob($template_folder."*") as $file)
		{
			$info = pathinfo($file);
			$template_id = strtoupper(str_replace("-", "_", $info["filename"]));
			$this->source_files[$template_id] = $file;
		}
	}
	
	public function __construct()
	{
		$this->block_pattern = '/' . $this->block_pattern_prefix . $this->block_pattern_chars . $this->block_pattern_suffix . '/';
		$this->discover_templates($this->template_folder);
		$this->init_templates($this->source_files);
		$this->new();
	}
	
	public function new() : void
	{
		$this->blocks = array();
		foreach ($this->templates as $key => $template)
		{
			$this->blocks[$key] = $template;
		}
		// $this->blocks[self::$base_block] = $this->templates[self::$base_block];
	}
	
	public function get_block_names() : array
	{
		$all_matches = array();
		foreach ($this->blocks as $block)
		{
			$matches = array();
			preg_match_all($this->block_pattern, $block, $matches);
			$all_matches = array_merge($all_matches, $matches[0]);
		}
		
		$block_names = array();
		foreach (array_unique($all_matches) as $match)
		{
			$block_name = str_replace([$this->block_pattern_prefix, $this->block_pattern_suffix], "", $match);
			$block_names[$block_name] = array_key_exists($block_name, $this->blocks) ? true : false;
		}
		return $block_names;
	}
	
	public function set_block_template(string $block, string $template) : void
	{
		$this->blocks[$block] = $this->templates[$template];
	}
	
	public function set_block(string $block, string $content) : void
	{
		$this->blocks[$block] = $content;
	}
	
	public function append_block(string $block, string $content) : void
	{
		if (empty($this->blocks[$block])) $this->blocks[$block] = "";
		$this->blocks[$block] .= $content;
	}
	
	public function append_block_template(string $block, string $template) : void
	{
		if (empty($this->blocks[$block])) $this->blocks[$block] = "";
		$this->blocks[$block] .= $this->templates[$template];
	}
	
	public function append_built_block(string $block, string $block_name) : void
	{
		$this->append_block($block, $this->build_block($block_name));
	}
	
	public function append_argumented_block(string $block, string $block_name, array $args) : void
	{
		$this->append_block($block, $this->build_argumented_block($block_name, $args));
	}
	
	public function build_argumented_block(string $block, array $args) : string
	{
		foreach ($args as $key => $value) $this->set_block($key, $value);
		return $this->build_block($block);
	}
	
	public function build_block(string $block) : string
	{
		$matches = array();
		$html = $this->blocks[$block];
		
		$from = array();
		$to = array();
		foreach ($this->get_block_names() as $block_name => $has_block)
		{
			$from[] = $this->block_pattern_prefix . $block_name . $this->block_pattern_suffix;
			if ($has_block) $to[] = $this->blocks[$block_name];
			else $to[] = "";
		}
		while (preg_match_all($this->block_pattern, $html) > 0)
		{
			$html = str_replace($from, $to, $html);
		}
		
		return $html;
	}
	
	public function get_raw_html() : string
	{
		return $this->build_block(self::$base_block);
	}
	
	public function get_html() : string
	{
		$strip = [ "\t" ];
		return str_replace($strip, "", $this->get_raw_html());
	}
}