<?php

/**
 * Spoon Library
 *
 * This source file is part of the Spoon Library. More information,
 * documentation and tutorials can be found @ http://www.spoon-library.be
 *
 * @package		spoon
 * @subpackage	file
 *
 *
 * @author		Davy Hellemans <davy@spoon-library.be>
 * @author 		Tijs Verkoyen <tijs@spoon-library.be>
 * @author		Dave Lens <dave@spoon-library.be>
 * @since		0.1.1
 */

/** SpoonFileException */
require_once 'spoon/file/exception.php';

/** SpoonDirectory */
require_once 'spoon/directory/directory.php';

/** SpoonFilter class */
require_once 'spoon/filter/filter.php';


/**
 * This class provides a wide range of methods to be used on
 * files.
 *
 * @package		spoon
 * @subpackage	file
 *
 *
 * @author		Davy Hellemans <davy@spoon-library.be>
 * @author		Tijs Verkoyen <tijs@spoon-library.be>
 * @since		0.1.1
 */
class SpoonFile
{
	/**
	 * Deletes a file.
	 *
	 * @return	bool				True if the file was deleted, false if not.
	 * @param	string $filename	Full path (including filename) of the file that should be deleted.
	 */
	public static function delete($filename)
	{
		return @unlink((string) $filename);
	}


	/**
	 * Download a file from a public URL.
	 *
	 * @return	bool						True if the file was downloaded, false if not.
	 * @param	string $sourceURL			The URL of the file to download.
	 * @param	string $destinationPath		The path where the file should be downloaded to.
	 * @param	bool[optional] $overwrite	In case the destinationPath already exists, should we overwrite this file?
	 */
	public static function download($sourceURL, $destinationPath, $overwrite = true)
	{
		// check if curl is available
		if(!function_exists('curl_init')) throw new SpoonFileException('This method requires cURL (http://php.net/curl), it seems like the extension isn\'t installed.');

		// redefine
		$sourceURL = (string) $sourceURL;
		$destinationPath = (string) $destinationPath;
		$overwrite = (bool) $overwrite;

		// validate if the file already exists
		if(!$overwrite && self::exists($destinationPath)) return false;

		// open file handler
		$fileHandle = @fopen($destinationPath, 'w');

		// validate filehandle
		if($fileHandle === false) return false;

		$options = array(CURLOPT_URL => $sourceURL,
						 CURLOPT_FILE => $fileHandle,
						 CURLOPT_HEADER => false);

		// init curl
		$curl = curl_init();

		// set options
		curl_setopt_array($curl, $options);

		// execute the call
		curl_exec($curl);

		// get errornumber
		$errorNumber = curl_errno($curl);
		$errorMessage = curl_error($curl);
		$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

		// close
		curl_close($curl);
		fclose($fileHandle);

		// validate the errornumber
		if($errorNumber != 0) throw new SpoonFileException($errorMessage);
		if($httpCode != 200) throw new SpoonFileException('The file "'. $sourceURL .'" isn\'t available for download.');

		// return
		return true;
	}


	/**
	 * Does this file exist.
	 *
	 * @return	bool				True if the file exists, false if not.
	 * @param	string $filename	The full path of the file to check for existance.
	 */
	public static function exists($filename)
	{
		return (@file_exists((string) $filename) && is_file((string) $filename));
	}


	/**
	 * Fetch the extension for a filename.
	 *
	 * @return	string						The extension.
	 * @param	string $filename			The full path of the file.
	 * @param	bool[optional] $lowercase	Should the extension be returned in lowercase or in its original form.
	 */
	public static function getExtension($filename, $lowercase = true)
	{
		// init var
		$filename = ($lowercase) ? strtolower((string) $filename) : (string) $filename;

		// fetch extension
		$aExtension = explode('.', $filename);

		// count the chunks
		$count = count($aExtension);

		// has an extension
		if($count != 0) return $aExtension[$count - 1];

		// no extension
		return '';
	}


	/**
	 * Fetch the content from a file or URL.
	 *
	 * @return	string				The content.
	 * @param	string $filename	The path or URL to the file. URLs will only work if fopen-wrappers are enabled.
	 */
	public static function getContent($filename)
	{
		return @file_get_contents((string) $filename);
	}


	/**
	 * Fetch the information about a file.
	 *
	 * @return	array				An array that contains a lot of information about the file.
	 * @param	string $filename	The path of the file.
	 */
	public static function getInfo($filename)
	{
		// redefine
		$filename = (string) $filename;

		// init var
		$units = array('B', 'KB', 'MB', 'GB', 'TB', 'PB');

		// fetch pathinfo
		$pathInfo = pathinfo($filename);

		// build details array
		$file = array();
		$file['basename'] = $pathInfo['basename'];
		$file['extension'] = self::getExtension($filename);
		$file['name'] = substr($file['basename'], 0, strlen($file['basename']) - strlen($file['extension']) -1);
		$file['size'] = @filesize($filename);
		$file['is_executable'] = @is_executable($filename);
		$file['is_readable'] = @is_readable($filename);
		$file['is_writable'] = @is_writable($filename);
		$file['modification_date'] = @filemtime($filename);
		$file['path'] = $pathInfo['dirname'];
		$file['permissions'] = @fileperms($filename);

		// calculate human readable size
		$size = $file['size'];
		$mod = 1024;
		for($i = 0; $size > $mod; $i++) $size /= $mod;
		$file['human_readable_size'] = round($size, 2) .' '. $units[$i];

		// clear cache
		@clearstatcache();

		// cough it up
		return $file;
	}


	/**
	 * Retrieves a list of files within a directory.
	 *
	 * @return	array								An array containing a list of files in the given directory.
	 * @param	string $path						The path to the directory.
	 * @param	string[optional] $includeRegexp		A regular expresion that filters the files that should be included in the list.
	 */
	public static function getList($path, $includeRegexp = null)
	{
		// redefine arguments
		$path = (string) $path;

		// validate regex
		if($includeRegexp !== null)
		{
			// redefine
			$includeRegexp = (string) $includeRegexp;

			// validate
			if(!SpoonFilter::isValidRegexp($includeRegexp)) throw new SpoonFileException('Invalid regular expression ('. $includeRegexp .')');
		}

		// define list
		$files = array();

		// directory exists
		if(SpoonDirectory::exists($path))
		{
			// attempt to open directory
			if($directory = @opendir($path))
			{
				// start reading
				while((($file = readdir($directory)) !== false))
				{
					// no '.' and '..' and it's a file
					if(($file != '.') && ($file != '..') && is_file($path .'/'. $file))
					{
						// is there a include-pattern?
						if($includeRegexp !== null)
						{
							// init var
							$matches = array();

							// is this a match?
							if(preg_match($includeRegexp, $file, $matches) != 0) $files[] = $file;
						}

						// no excludes defined
						else $files[] = $file;
					}
				}
			}

			// close directory
			@closedir($directory);
		}

		// directory doesn't exist or a problem occured
		return $files;
	}


	/**
	 * Move/rename a directory/file.
	 *
	 * @return	bool						True if the file was moved or renamed, false if not.
	 * @param	string $source				Path of the source file.
	 * @param	string $destination			Path of the destination.
	 * @param 	bool[optional] $overwrite	Should an existing file be overwritten?
	 * @param	int[optional] $chmod		Chmod mode that should be applied on the file/directory.
	 */
	public static function move($source, $destination, $overwrite = true, $chmod = 0777)
	{
		// this is just an alias for SpoonDirectory::move
		return SpoonDirectory::move($source, $destination, $overwrite, $chmod);
	}


	/**
	 * Writes a string to a file.
	 *
	 * @return	bool						True if the content was written, false if not.
	 * @param	string $filename			The path of the file.
	 * @param	string $content				The content that should be written.
	 * @param	bool[optional] $createFile	Should the file be created if it doesn't exists?
	 * @param	bool[optional] $append		Should the content be appended if the file already exists?
	 * @param	int[optional] $chmod		Mode that should be applied on the file.
	 */
	public static function setContent($filename, $content, $createFile = true, $append = false, $chmod = 0777)
	{
		// redefine vars
		$filename = (string) $filename;
		$content = (string) $content;
		$createFile = (bool) $createFile;
		$append = (bool) $append;

		// file may not be created, but it doesn't exist either
		if(!$createFile && self::exists($filename)) throw new SpoonFileException('The file "'. $filename .'" doesn\'t exist');

		// create directory recursively if needed
		SpoonDirectory::create(dirname($filename), $chmod, true);

		// create file & open for writing
		$handler = ($append) ? @fopen($filename, 'a') : @fopen($filename, 'w');

		// something went wrong
		if($handler === false) throw new SpoonFileException('The file "'. $filename .'" could not be created. Check if PHP has enough permissions.');

		// write to file
		$write = @fwrite($handler, $content);

		// validate write
		if($write === false) throw new SpoonFileException('The file "'. $filename .'" could not be written to. Check if PHP has enough permissions.');

		// close the file
		@fclose($handler);

		// chmod file
		@chmod($filename, $chmod);

		// status
		return true;
	}
}

?>