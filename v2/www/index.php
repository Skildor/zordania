<?php
/* redirection ici remplace le .htaccess de apache - pour lighttpd */
if(isset($_SERVER['HTTP_HOST']) and $_SERVER['HTTP_HOST'] == 'www.zordania.com'){
	$url = 'http://zordania.com' . $_SERVER['REQUEST_URI'];
	header('Status: 301 Moved Permanently', false, 301);
	header("Location: $url");
	exit();
}

define("_INDEX_",true);

require("../index.php");
?>
