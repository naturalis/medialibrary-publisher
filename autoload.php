<?php
$APP_DIR = realpath(__DIR__);
$USED_MEDIALIB_PACKAGES = array(
		'publisher',
		'util'
);

spl_autoload_register(function ($class)
{
	if(loadMedialibClass($class)) {
		return;
	}	
	if(loadExternalClass($class)) {
		return;
	}	
	if($class === 'PHPMailer') {
		include __DIR__ . '/lib/PHPMailer_5.2.4/class.phpmailer.php';
		return;
	}	
	throw new \Exception("Class not found: $class");
});


function loadMedialibClass($class)
{
	global $APP_DIR, $USED_MEDIALIB_PACKAGES;
	$rootPackage = 'nl\naturalis\medialib';
	foreach($USED_MEDIALIB_PACKAGES as $name) {
		$package = $rootPackage . '\\' . $name;
		if(!startsWith($class, $package)) {
			continue;
		}
		// The directory supposed to contain the classes belonging
		// to this package
		$packageDir = str_replace('\\', '.', $package);
		$subPackage = substr($class, strlen($package) + 1);
		$relativePath = str_replace('\\', DIRECTORY_SEPARATOR, $subPackage);
		$file = path($APP_DIR, 'lib', $packageDir, $relativePath) . '.php';
		if(is_file($file)) {
			include $file;
			return true;
		}
		throw new \Exception("Media Library class $class not found at expected location ($file)");
	}
	return false;
}


function loadExternalClass($class)
{
	global $APP_DIR;
	$relativePath = str_replace('\\', DIRECTORY_SEPARATOR, $class);
	$file = path($APP_DIR, 'lib', $relativePath) . '.php';
	if(is_file($file)) {
		include $file;
		return true;
	}
	return false;
}


function path()
{
	return implode(DIRECTORY_SEPARATOR, func_get_args());
}


function startsWith($what, $with)
{
	return strpos($what, $with) === 0;
}
