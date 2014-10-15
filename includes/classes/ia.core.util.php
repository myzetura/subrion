<?php
/******************************************************************************
 *
 * Subrion - open source content management system
 * Copyright (C) 2014 Intelliants, LLC <http://www.intelliants.com>
 *
 * This file is part of Subrion.
 *
 * Subrion is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Subrion is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Subrion. If not, see <http://www.gnu.org/licenses/>.
 *
 *
 * @link http://www.subrion.org/
 *
 ******************************************************************************/

class iaUtil extends abstractUtil
{
	const JSON_SERVICES_FILE = 'Services_JSON.php';


	public static function jsonEncode($data)
	{
		if (function_exists('json_encode'))
		{
			return json_encode($data);
		}
		else
		{
			require_once IA_INCLUDES . 'utils' . IA_DS . self::JSON_SERVICES_FILE;
			$jsonServices = new Services_JSON();

			return $jsonServices->encode($data);
		}
	}

	public static function jsonDecode($data)
	{
		if (function_exists('json_decode'))
		{
			return json_decode($data, true);
		}
		else
		{
			require_once IA_INCLUDES . 'utils' . IA_DS . self::JSON_SERVICES_FILE;
			$jsonServices = new Services_JSON(SERVICES_JSON_LOOSE_TYPE);

			return $jsonServices->decode($data);
		}
	}

	public static function downloadRemoteContent($sourceUrl, $savePath)
	{
		if (extension_loaded('curl'))
		{
			$fh = fopen($savePath, 'w');

			$ch = curl_init($sourceUrl);
			curl_setopt($ch, CURLOPT_FILE, $fh);
			$result = curl_exec($ch);
			curl_close($ch);

			fclose($fh);

			return (bool)$result;
		}

		return false;
	}

	public static function getPageContent($url, $timeout = 10)
	{
		$result = null;
		$userAgent = 'Subrion CMS Bot';
		if (extension_loaded('curl'))
		{
			$ch = curl_init();
			curl_setopt_array($ch, array(
				CURLOPT_URL => $url,
				CURLOPT_HEADER => false,
				CURLOPT_REFERER => IA_CLEAR_URL,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_CONNECTTIMEOUT => (int)$timeout,
				CURLOPT_USERAGENT => $userAgent
			));

			$result = curl_exec($ch);

			curl_close($ch);
		}
		elseif (ini_get('allow_url_fopen'))
		{
			ini_set('user_agent', $userAgent);
			$result = file_get_contents($url, false);
			ini_restore('user_agent');
		}
		else
		{
			$result = false;
		}

		return $result;
	}

	/*
	 * Makes safe XHTML code, strip only dangerous tags and attributes
	 *
	 * @param string $string HTML text
	 *
	 * @return string
	 */
	public static function safeHTML($string)
	{
		require_once IA_INCLUDES . 'htmlpurifier' . IA_DS . 'HTMLPurifier.auto' . iaSystem::EXECUTABLE_FILE_EXT;

		// generate cache folder
		$cacheDirectory = IA_CACHEDIR . 'Serializer' . IA_DS;
		file_exists($cacheDirectory) || @mkdir($cacheDirectory, 0777);

		$config = HTMLPurifier_Config::createDefault();

		$config->set('HTML.Doctype', 'XHTML 1.0 Transitional');
		$config->set('Cache.SerializerPath', $cacheDirectory);
		$config->set('Attr.AllowedFrameTargets', array('_blank'));
		$config->set('Attr.AllowedRel', 'facebox,nofollow,print,ia_lightbox');

		// allow YouTube and Vimeo
		$config->set('HTML.SafeIframe', true);
		$config->set('URI.SafeIframeRegexp', '%^(https?:)?//(www\.youtube(?:-nocookie)?\.com/embed/|player\.vimeo\.com/video/)%');

		$purifier = new HTMLPurifier($config);

		return $purifier->purify($string);
	}

	public static function go_to($url)
	{
		if (empty($url))
		{
			trigger_error('Fatal error: empty url param of function ' . __METHOD__);
		}
		if (!headers_sent())
		{
			unset($_SESSION['info'], $_SESSION['msg']);

			$_SESSION['msg'] = iaCore::instance()->iaView->getMessages();
//			$_SESSION['error_msg'] = true;

			header('Location: ' . $url);

			exit;
		}
		else
		{
			trigger_error('Headers already sent. Redirection is impossible.');
		}
	}

	public static function post_goto($goto, $name = 'goto')
	{
		$action = 'stay';
		if (isset($_POST[$name]))
		{
			$action = $_POST[$name];
		}
		isset($goto[$action]) || $action = 'list';

		self::go_to($goto[$action]);
	}

	public static function makeDirCascade($path, $mode = 0777, $chmod = false)
	{
		if (!is_dir(dirname($path)))
		{
			self::makeDirCascade(dirname($path), $mode);
		}
		if (!is_dir($path))
		{
			$result = mkdir($path, $mode);
			if ($chmod && !function_exists('posix_getuid') || function_exists('posix_getuid') && posix_getuid() != fileowner(IA_HOME . 'index.php'))
			{
				chmod($path, $mode);
			}
			return $result;
		}

		return true;
	}

	public static function generateToken($length = 10)
	{
		$result = md5(uniqid(rand(), true));
		$result = substr($result, 0, $length);

		return $result;
	}

	public static function redirect($title, $message, $url = null, $isAjax = false)
	{
		$url = $url ? $url : IA_URL;
		$message = is_array($message) ? implode('<br />', $message) : $message;
		unset($_SESSION['redir']);

		$_SESSION['redir'] = array(
			'caption' => $title,
			'msg' => $message,
			'url' => $url
		);

		if (!$isAjax)
		{
			$redirectUrl = IA_URL . 'redirect/';

			if (iaCore::instance()->get('redirect_time', 4000) == 0)
			{
				$redirectUrl = $url;
			}

			header('Location: ' . $redirectUrl);
			exit;
		}
	}

	public static function reload($params = null)
	{
		$url = IA_SELF;

		if (is_array($params))
		{
			foreach ($params as $k => $v)
			{
				// remove key
				if (is_null($v))
				{
					unset($params[$k]);
					if (array_key_exists($k, $_GET))
					{
						unset($_GET[$k]);
					}
				}
				elseif (array_key_exists($k, $_GET)) // set new value
				{
					$_GET[$k] = $v;
					unset($params[$k]);
				}
			}
		}

		if ($_GET || $params)
		{
			$url .= '?';
		}
		foreach ($_GET as $k => $v)
		{
			// Unfort. At this time we delete an individual items using GET requests instead of POST
			// so when reloading we should skip delete action
			if ($k == 'action' && $v == 'delete')
			{
				continue;
			}
			$url .= $k . '=' . urlencode($v) . '&';
		}

		if ($params)
		{
			if (is_array($params))
			{
				foreach ($params as $k => $v)
				{
					$url .= $k . '=' . urlencode($v) . '&';
				}
			}
			else
			{
				$url .= $params;
			}
		}
		$url = rtrim($url, '&');

		self::go_to($url);
	}

	/*
	 * Check that personal folder exists and return path
	 *
	 * @param string $userName
	 *
	 * @return str path from UPLOADS directory (you can completely insert it into DB)
	 */
	public static function getAccountDir($userName = '')
	{
		if (empty($userName))
		{
			$userName = iaUsers::hasIdentity() ? iaUsers::getIdentity()->username : false;
		}

		$serverDirectory = '';
		umask(0);

		if (empty($userName))
		{
			$serverDirectory .= '_notregistered' . IA_DS;
			if (!is_dir(IA_UPLOADS . $serverDirectory))
			{
				mkdir(IA_UPLOADS . $serverDirectory);
			}
		}
		else
		{
			$subFolders = array();
			$subFolders[] = strtolower(substr($userName, 0, 1)) . IA_DS;
			$subFolders[] = $userName . IA_DS;
			foreach ($subFolders as $test)
			{
				$serverDirectory .= $test;
				if (!is_dir(IA_UPLOADS . $serverDirectory))
				{
					mkdir(IA_UPLOADS . $serverDirectory);
				}
			}
		}

		return $serverDirectory;
	}

	/**
	 * Provides a basic check if file is a zip archive
	 *
	 * @param $file file path
	 * @return bool
	 */
	public static function isZip($file)
	{
		if (function_exists('zip_open'))
		{
			if (is_resource($zip = zip_open($file)))
			{
				zip_close($zip);

				return true;
			}
		}
		else
		{
			$fh = fopen($file, 'r');
			if (is_resource($fh))
			{
				$signature = fread($fh, 2);
				fclose($fh);

				if ('PK' === $signature)
				{
					return true;
				}
			}
		}

		return false;
	}

	public static function checkPostParam($key, $default = '')
	{
		if (isset($_POST[$key]))
		{
			return $_POST[$key];
		}
		if (is_array($default))
		{
			if (isset($default[$key]))
			{
				$default = $default[$key];
			}
			else
			{
				$default = '';
			}
		}

		return $default;
	}

	public static function getFormattedTimezones()
	{
		$result = array();
		$regions = array('Africa', 'America', 'Antarctica', 'Asia', 'Atlantic', 'Australia', 'Europe', 'Indian', 'Pacific');
		$timezones = DateTimeZone::listIdentifiers();
		foreach ($timezones as $timezone)
		{
			$array = explode('/', $timezone);
			if (2 == count($array) && in_array($array[0], $regions))
			{
				$result[$array[0]][$timezone] = str_replace('_', ' ', $timezone);
			}
		}

		return $result;
	}

	public static function getIp($long = true)
	{
		// test if it is a shared client
		if (!empty($_SERVER['HTTP_CLIENT_IP']))
		{
			$ip = $_SERVER['HTTP_CLIENT_IP'];
		}
		// is it a proxy address
		elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR']))
		{
			$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
		}
		else
		{
			$ip = $_SERVER['REMOTE_ADDR'];
		}

		return $long ? ip2long($ip) : $ip;
	}

	public static function getLetters()
	{
		return array(
			'0-9','A','B','C','D','E','F','G','H','I','J','K','L',
			'M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z'
		);
	}

	/**
	 * Delete file(s)
	 *
	 * @param string|array $file filename(s) to be deleted
	 *
	 * @return bool
	 */
	public static function deleteFile($file)
	{
		if (is_array($file))
		{
			foreach ($file as $name)
			{
				self::deleteFile($name);
			}

			return true;
		}
		elseif (is_file($file))
		{
			return @unlink($file);
		}

		return false;
	}

	/**
	 * Load core of the UTF8 lib and then functions specified in arguments
	 *
	 * @param list of function names to be prepared to use
	 * @return bool
	 */
	public static function loadUTF8Functions()
	{
		$libPath = 'phputf8';
		static $isLibLoaded = false;

		if (!$isLibLoaded)
		{
			$path = IA_INCLUDES . $libPath . IA_DS;

			try
			{
				if (function_exists('mb_internal_encoding'))
				{
					mb_internal_encoding('UTF-8');
					require_once $path . 'mbstring' . IA_DS . 'core' . iaSystem::EXECUTABLE_FILE_EXT;
				}
				else
				{
					require_once $path . 'utils' . IA_DS . 'unicode' . iaSystem::EXECUTABLE_FILE_EXT;
					require_once $path . 'native' . IA_DS . 'core' . iaSystem::EXECUTABLE_FILE_EXT;
				}
			}
			catch (Exception $e)
			{
				return false;
			}

			$isLibLoaded = true;
		}

		if (func_num_args())
		{
			foreach (func_get_args() as $fn)
			{
				require_once IA_INCLUDES . $libPath . IA_DS . 'utils' . IA_DS . $fn . iaSystem::EXECUTABLE_FILE_EXT;
			}
		}

		return true;
	}
}