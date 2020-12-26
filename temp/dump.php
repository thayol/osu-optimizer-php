<?php
function dump($var, $title=NULL, $use_textarea=false)
{
	echo '<pre style="text-align:left;background-color:#666666;color:#EEEEEE;padding:20px;border:1px solid #444444;border-radius:5px;">';
	echo '<details>';
	echo '<summary style="padding:20px;margin:-20px;">variable dump';
	if ($title !== NULL)
	{
		echo ' of ' . $title;
	}
	echo '</summary>';
	
	if ($use_textarea)
	{
		echo '<textarea style="margin-top:30px; width:100%; height:300px; resize: vertical;">';
	}
	else
	{
		echo '<div style="margin-top:30px;">';
	}
	
	print_r($var); // actual dump
	
	if ($use_textarea)
	{
		echo '</textarea>';
	}
	else
	{
		echo '</div>';
	}
	
	echo '</details>';
	echo '</pre>';
}