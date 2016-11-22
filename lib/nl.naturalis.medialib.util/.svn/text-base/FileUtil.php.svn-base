<?php

namespace nl\naturalis\medialib\util;

class FileUtil {
	const DS = DIRECTORY_SEPARATOR;


	public static function createPath()
	{
		$args = func_get_args();
		$path = rtrim($args[0], DIRECTORY_SEPARATOR);
		for($i = 1; $i < func_num_args(); ++$i) {
			$path .= DIRECTORY_SEPARATOR . $args[$i];
		}
		return $path;
	}


	public static function isEmptyDir($path)
	{
		if(!is_dir($path)) {
			throw new \Exception('Not a directory: ' . $path);
		}
		if(!is_readable($path)) {
			return false;
		}
		$handle = @opendir($path);
		if($handle === false) {
			$errInfo = error_get_last();
			throw new \Exception('Error reading directory ' . $path . '(' . $errInfo['message'] . ')');
		}
		$fileCount = 0;
		while(readdir($handle) && ++$fileCount !== 3)
			;
		closedir($handle);
		return $fileCount !== 3;
	}


	public static function getExtension($path, $toLowerCase = true)
	{
		$dotPos = strrpos($path, '.');
		if($dotPos === false) {
			return '';
		}
		if($toLowerCase) {
			return strtolower(substr($path, $dotPos + 1));
		}
		return substr($path, $dotPos + 1);
	}


	/**
	 * Returns a file's base name without the last extension.
	 * /var/www/functions.inc.php => functions.inc
	 *
	 * @param string $path        	
	 */
	public static function basename($path)
	{
		$name = basename($path);
		$dotPos = strrpos($name, '.');
		if($dotPos === false) {
			return $name;
		}
		return substr($name, 0, $dotPos);
	}


	public static function deleteRecursive($path, $verbose = false)
	{
		if(is_file($path)) {
			if($verbose) {
				echo "\nDeleting [F] $path";
			}
			self::unlink($path);
			return;
		}
		if(is_link($path)) {
			if($verbose) {
				echo "\nDeleting [S] $path";
			}
			self::unlink($path);
			return;
		}
		if(!is_dir($path)) {
			throw new \Exception('Don\'t know how to delete ' . $path);
		}
		foreach(self::scandir($path) as $file) {
			if($file === "." || $file === "..") {
				continue;
			}
			$p = $path . DIRECTORY_SEPARATOR . $file;
			self::deleteRecursive($p, $verbose);
		}
		if($verbose) {
			echo "\nDeleting [D] $path";
		}
		self::rmdir($path);
	}


	public static function deleteRecursiveUnder($path, $verbose = false)
	{
		foreach(self::scandir($path) as $file) {
			if($file === "." || $file === "..") {
				continue;
			}
			$p = $path . DIRECTORY_SEPARATOR . $file;
			self::deleteRecursive($p, $verbose);
		}
	}


	public static function moveRecursive($source, $target, $fileTypes = null)
	{
		if(is_file($source)) {
			$move = true;
			if(is_array($fileTypes) && count($fileTypes) !== 0) {
				$ext = strtolower(self::getExtension($source));
				if(!in_array($ext, $fileTypes)) {
					$move = false;
				}
			}
			if($move && !@rename($source, $target)) {
				throw new \Exception("Could not move $source to $target");
			}
		}
		else {
			if(!is_dir($target) && !mkdir($target)) {
				throw new \Exception("Could not create directory $target");
			}
			foreach(self::scandir($source) as $file) {
				if($file === "." || $file === "..") {
					continue;
				}
				$p0 = $source . DIRECTORY_SEPARATOR . $file;
				$p1 = $target . DIRECTORY_SEPARATOR . $file;
				self::moveRecursive($p0, $p1, $fileTypes);
			}
		}
	}


	public static function moveRecursiveUnder($source, $target, $fileTypes = null)
	{
		foreach(self::scandir($source) as $file) {
			if($file == "." || $file == "..") {
				continue;
			}
			$p0 = $source . DIRECTORY_SEPARATOR . $file;
			$p1 = $target . DIRECTORY_SEPARATOR . $file;
			self::moveRecursive($p0, $p1, $fileTypes);
		}
	}


	public static function copyRecursive($source, $target, $fileTypes = null)
	{
		if(is_file($source)) {
			$go = true;
			if(is_array($fileTypes) && count($fileTypes) !== 0) {
				$ext = strtolower(self::getExtension($source));
				if(!in_array($ext, $fileTypes)) {
					$go = false;
				}
			}
			if($go) {
				self::copy($source, $target);
			}
		}
		else if(is_dir($source)) {
			if(!is_dir($target) && !@mkdir($target)) {
				throw new \Exception("Could not create directory $target");
			}
			foreach(self::scandir($source) as $file) {
				if($file == "." || $file == "..") {
					continue;
				}
				$p0 = $source . DIRECTORY_SEPARATOR . $file;
				$p1 = $target . DIRECTORY_SEPARATOR . $file;
				self::copyRecursive($p0, $p1, $fileTypes);
			}
		}
		else {
			echo "\nDon't know how to copy $source";
		}
	}


	public static function copyRecursiveUnder($source, $target, $fileTypes = null)
	{
		foreach(self::scandir($source) as $file) {
			if($file == "." || $file == "..") {
				continue;
			}
			$p0 = $source . DIRECTORY_SEPARATOR . $file;
			$p1 = $target . DIRECTORY_SEPARATOR . $file;
			self::copyRecursive($p0, $p1, $fileTypes);
		}
	}


	public static function unlink($file, $ignore = true)
	{
		if(is_link($file) || is_file($file)) {
			if(!@unlink($file)) {
				$errInfo = error_get_last();
				throw new \Exception("Could not delete file $file: " . $errInfo['message']);
			}
		}
		else if(is_dir($file)) {
			if(count(self::scandir($file)) === 2) {
				if(!@rmdir($file)) {
					$errInfo = error_get_last();
					throw new \Exception("Could not delete directory $file: " . $errInfo['message']);
				}
			}
		}
	}


	public static function rmdir($dir)
	{
		if(is_link($dir)) {
			self::unlink($dir);
			return;
		}
		if(!is_dir($dir)) {
			throw new \Exception("Not a directory: \"$dir\"");
		}
		if(count(self::scandir($dir)) !== 2) {
			throw new \Exception("Directory not empty: \"$dir\"");
		}
		if(!@rmdir($dir)) {
			$errInfo = error_get_last();
			throw new \Exception("Could not delete directory \"$dir\": " . $errInfo['message']);
		}
	}


	public static function mkdir($baseDir, $subDir = null, $failIfExists = true)
	{
		if($subDir !== null) {
			if(!is_dir($baseDir)) {
				throw new \Exception("Not a directory: $baseDir");
			}
			$baseDir = rtrim($baseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $subDir;
		}
		if(is_dir($baseDir) || is_link($baseDir)) {
			if($failIfExists) {
				throw new \Exception("Could not create directory $baseDir: directory already exists");
			}
			return $baseDir;
		}
		if(!@mkdir($baseDir)) {
			$errInfo = error_get_last();
			throw new \Exception("Could not create directory $baseDir: " . $errInfo['message']);
		}
		return $baseDir;
	}


	public static function copy($source, $target)
	{
		if(!@copy($source, $target)) {
			$errInfo = error_get_last();
			throw new \Exception("Could not copy $source to $target: " . $errInfo['message']);
		}
	}


	public static function symlink($real, $symbolic)
	{
		if(!@symlink($real, $symbolic)) {
			$errInfo = error_get_last();
			throw new \Exception("Could not create symbolic link ($symbolic) to $real: " . $errInfo['message']);
		}
	}


	public static function rename($source, $target)
	{
		if(!@rename($source, $target)) {
			$errInfo = error_get_last();
			throw new \Exception("Could not rename/move $source to $target: " . $errInfo['message']);
		}
	}


	public static function scandir($dir)
	{
		if(!is_dir($dir)) {
			throw new \Exception("Not a directory: $dir");
		}
		$files = @scandir($dir);
		if($files === false) {
			throw new \Exception("Unable to read from directory $dir");
		}
		return $files;
	}

}
