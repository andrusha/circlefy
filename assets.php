<?php

	include "views/scripts/packages.php";

	$cache      = false;
	$cachedir   = dirname(__FILE__) . '/cache';
	$cssdir     = dirname(__FILE__) . '/views/styles';
	$jsdir      = dirname(__FILE__) . '/views/scripts';
	$compress   = (!empty($_GET['yui'])) ? json_decode($_GET['yui']) : false;
	$compressor = dirname(__FILE__) .'/views/scripts/yuicompressor-2.4.2.jar';

	// Determine the directory and type we should use
	if (empty($_GET['type'])) {
		header ("HTTP/1.0 404 Not Found");
		exit();
	}
	
	switch ($_GET['type']) {
		case 'css':
			$base = realpath($cssdir);
			$extension = '.css';
			break;
		case 'javascript':
			$base = realpath($jsdir);
			$extension = '.js';
			break;
		default:
			header ("HTTP/1.0 503 Not Implemented");
			exit;
	};

	$type = $_GET['type'];
	if (!empty($_GET['package'])) {
		$elements = $packages[$_GET['package']];
		$files = implode(',', $elements);
	} else {
		$files = $_GET['files'];
		$elements = explode(',', $files);
	}
	
	// Determine last modification date of the files
	$lastmodified = 0;
	foreach($elements as $element){
		$path = realpath($base . '/' . $element . $extension);

		if (($type == 'javascript' && substr($path, -3) != '.js') || 
			($type == 'css' && substr($path, -4) != '.css')) {
			header ("HTTP/1.0 403 Forbidden");
			exit;	
		}
	
		if (substr($path, 0, strlen($base)) != $base || !file_exists($path)) {
			header ("HTTP/1.0 404 Not Found");
			exit;
		}
		
		$lastmodified = max($lastmodified, filemtime($path));
	}
	
	// Send Etag hash
	$hash = $lastmodified . '-' . md5($files . ($compress ? 'yui' : ''));
	header ("Etag: \"" . $hash . "\"");
	
	if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && 
		stripslashes($_SERVER['HTTP_IF_NONE_MATCH']) == '"' . $hash . '"') 
	{
		// Return visit and no modifications, so do not send anything
		header ("HTTP/1.0 304 Not Modified");
		header ('Content-Length: 0');
	} 
	else 
	{
		// First time visit or files were modified
		if ($cache) 
		{
			// Determine supported compression method
			$httpEncoding = !empty($_SERVER['HTTP_ACCEPT_ENCODING']) ? $_SERVER['HTTP_ACCEPT_ENCODING'] : '';
			$gzip = strstr($httpEncoding, 'gzip');
			$deflate = strstr($httpEncoding, 'deflate');
	
			// Determine used compression method
			$encoding = $gzip ? 'gzip' : ($deflate ? 'deflate' : 'none');
	
			// Check for buggy versions of Internet Explorer
			if (!strstr($_SERVER['HTTP_USER_AGENT'], 'Opera') && 
				preg_match('/^Mozilla\/4\.0 \(compatible; MSIE ([0-9]\.[0-9])/i', $_SERVER['HTTP_USER_AGENT'], $matches)) {
				$version = floatval($matches[1]);
				
				if ($version < 6)
					$encoding = 'none';
					
				if ($version == 6 && !strstr($_SERVER['HTTP_USER_AGENT'], 'EV1')) 
					$encoding = 'none';
			}
			
			// Try the cache first to see if the combined files were already generated
			$cachefile = 'cache-' . $hash . '.' . $type . ($encoding != 'none' ? '.' . $encoding : '');
			
			if (file_exists($cachedir . '/' . $cachefile)) {
				if ($fp = fopen($cachedir . '/' . $cachefile, 'rb')) {

					if ($encoding != 'none') {
						header ("Content-Encoding: " . $encoding);
					}
				
					header ("Content-Type: text/" . $type);
					header ("Content-Length: " . filesize($cachedir . '/' . $cachefile));
		
					fpassthru($fp);
					fclose($fp);
					exit;
				}
			}
		}
	
		// Get contents of the files
		$contents = '';
		reset($elements);
		
		foreach ($elements as $element){
			$path = realpath($base . '/' . $element . $extension);
			if ($compress){
				$command = 'java -jar '. $compressor .' --preserve-semi -v --line-break 150 --charset UTF-8 --type js '.$path.'';
				exec($command, $out, $err);
				$contents .= join($out, PHP_EOL);
			} else {
				$contents .= file_get_contents($path) . "\n\n";
			}
		}
	
		// Send Content-Type
		header ("Content-Type: text/" . $type);
		
		if (isset($encoding) && $encoding != 'none') 
		{
			// Send compressed contents
			$contents = gzencode($contents, 9, $gzip ? FORCE_GZIP : FORCE_DEFLATE);
			header ("Content-Encoding: " . $encoding);
			header ('Content-Length: ' . strlen($contents));
			echo $contents;
		} 
		else 
		{
			// Send regular contents
			header ('Content-Length: ' . strlen($contents));
			echo $contents;
		}

		// Store cache
		if ($cache) {
			if ($fp = fopen($cachedir . '/' . $cachefile, 'wb')) {
				fwrite($fp, $contents);
				fclose($fp);
			}
		}
	}	
