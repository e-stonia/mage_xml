<?php

if (isset($_POST)) print_r($_POST);
else echo 'get';
exit;


$cat = array();
$file_array = file("i.txt");
foreach ($file_array AS $str) 
{
	$str = trim($str);
	preg_match("#^(.+?)\s(.+?)$#si", $str, $matches);
	if (isset($matches[2])) 
	{
		$cat[] = array('url' => strtolower(trim($matches[1])), 'title' => trim($matches[2]) );
		//echo '<pre>'; print_r($matches);echo '</pre>';
	}
}

require_once 'app/Mage.php';
Mage::app('default');

foreach ($cat AS $str)
{

	$category = Mage::getModel('catalog/category');
	$category->setStoreId(0);
	
	$general['name'] = $str['title'];
	$general['path'] = "1/2";
	$general['display_mode'] = "PRODUCTS";
	$general['is_active'] = 1;
	$general['is_anchor'] = 0;
	$general['url_key'] = $str['url'];

	$category->addData($general);
	
	try {
		$category->save();
	}
	catch (Exception $e){
	}
	
	$category = null;
}

echo '<pre>'; print_r($cat);echo '</pre>';

