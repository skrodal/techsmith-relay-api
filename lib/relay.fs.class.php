<?php

	/**
	 * @author Simon Skrødal
	 * @date   16/10/2015
	 * @time   14:00
	 */
	class RelayFS {

		private $relay, $feideConnect;
		// TODO: put in external config file!
		private $RELAY_CONFIG = array(
			'SCREENCAST_URL'           => 'https://screencast.uninett.no',
			'SCREENCAST_EMPLOYEE_URL'  => 'https://screencast.uninett.no/relay/ansatt/',
			'SCREENCAST_STUDENT_URL'   => 'https://screencast.uninett.no/relay/student/',
			'SCREENCAST_PATH'          => '/var/www/mnt/relaymedia_cache/',
			'SCREENCAST_EMPLOYEE_PATH' => '/var/www/mnt/relaymedia_cache/ansatt/',
			'SCREENCAST_STUDENT_PATH'  => '/var/www/mnt/relaymedia_cache/student/',
			'SCREENCAST_DELETE_LIST'   => '/var/www/mnt/relaymedia_cache/relaymedia_deletelist'            // NOT IN USE
		);

		function __construct(Relay $relay, FeideConnect $connect) {
			$this->relay        = $relay;
			$this->feideConnect = $connect;
		}


		function getRelayUserMedia($feideUserName) {
			//
			$screencastUserXMLs = NULL;
			$response           = NULL;
			// Get user account info (in separate API call)
			$userAcc = $this->relay->getUser($feideUserName);
			// In v.4.4.1 update '@' is stripped from username in publish path. Hence the need to check two folders per user.
			$feideUserNameAlt = str_replace('@', '', $feideUserName);
			// Return empty if no user found
			if(empty($userAcc)) {
				return [];
			}
			// Grabbed from assigned profile in Relay
			$isEmployee = strcasecmp($userAcc['userAffiliation'], 'employee') == 0;
			// /ansatt/ or /student/
			$screencastUserRoot = $isEmployee ? $this->RELAY_CONFIG['SCREENCAST_EMPLOYEE_PATH'] : $this->RELAY_CONFIG['SCREENCAST_STUDENT_PATH'];
			// Two potential user folders since Relay 4.4.1
			$screencastUserRoots = array(
				$screencastUserRoot . $feideUserName,
				$screencastUserRoot . $feideUserNameAlt
			);
			// Ensure that user has content in at least one of the user folders
			if(!file_exists($screencastUserRoots[0]) && !file_exists($screencastUserRoots[1])) {
				return "Fant ikke noe innhold for bruker " . $feideUserName;
			}

			foreach($screencastUserRoots as $folder) {
				$this->getUserXMLsRecursive($folder, "xml", $screencastUserXMLs);
			}

			// Iterate each folder where the XML files live, one folder per presentation
			foreach($screencastUserXMLs as $xml_path => $xml_files) {
				// Reset a few variables for each presentation loop
				$screencastUserMedia       = NULL;
				$thumbnails                = NULL;
				$screencastMediaResolution = NULL;

				// Are we working on a path with alternative username (no '@')?
				$isAltUsername = strpos($xml_path, $feideUserNameAlt) !== false;

				// Iterate XMLs pertaining to a single presentation, up to 4 XMLs per folder
				foreach($xml_files as $xml_file) {
					try {
						// Load XML metadata
						$screencastUserXml = simplexml_load_file($xml_path . '/' . $xml_file);
						// Parses about any English textual datetime description into a Unix timestamp, like Relay's "1/16/2012 10:58:41 AM"
						$timestamp = strtotime($screencastUserXml->date); // = 1326707921
						// Get presentation base URL (depends on affiliation and if username is with/without '@')
						if($isAltUsername) {
							$screencastPresentationBaseURL = $isEmployee ?
								$this->RELAY_CONFIG['SCREENCAST_EMPLOYEE_URL'] . substr($xml_path, strpos($xml_path, $feideUserNameAlt)) :
								$this->RELAY_CONFIG['SCREENCAST_STUDENT_URL'] . substr($xml_path, strpos($xml_path, $feideUserNameAlt));
						} else {
							$screencastPresentationBaseURL = $isEmployee ?
								$this->RELAY_CONFIG['SCREENCAST_EMPLOYEE_URL'] . substr($xml_path, strpos($xml_path, $feideUserName)) :
								$this->RELAY_CONFIG['SCREENCAST_STUDENT_URL'] . substr($xml_path, strpos($xml_path, $feideUserName));
						}

						// Name of format, e.g. "MP4 Smart Player (480p)" (will differ from changes in Profile settings over time)
						$encoding_preset = (String)$screencastUserXml->serverInfo->encodingPreset;
						// Get the first file listed in XML encodeFiles->fileList :: it appears that the first entry is always the mp4
						$encoding_filename = (String)$screencastUserXml->encodeFiles->fileList->file[0]['name'];
						// Add filename to array with format name as index
						$screencastUserMedia[$encoding_preset] = $screencastPresentationBaseURL . '/' . $encoding_filename;
						if(!is_file($xml_path . '/' . $encoding_filename)) {
							$screencastUserMedia[$encoding_preset] = str_ireplace(".mp4", ".html", $screencastUserMedia[$encoding_preset]);
						}

						$screencastMediaPreview = NULL;
						// Find a jpeg file in subfolder, any will do
						if(!isset($thumbnails)) {
							// Add all jpegs to array
							$thumbnails = $this->glob_recursive($xml_path . '/*.jpg');
							// If any, grab the first one
							if(isset($thumbnails[0])) {
								$screencastMediaPreview = $screencastPresentationBaseURL . str_replace($xml_path, "", $thumbnails[0]);
							}
						}
						$screencastMediaResolution = isset($screencastMediaResolution) ? $screencastMediaResolution : explode("x", $screencastUserXml->sourceRecording->resolution);
					} catch(Exception $e) {
						$response['status']  = false;
						$response['details'] = $e;

						return $response;
					}
				}

				// Find suitable mp4 and html wrapper for an MP4 for embed/download
				foreach($screencastUserMedia as $format => $url) {
					// a. Check if old PC (Flash) encoding is in array
					// NOTE: Depending on Relay-version this encoding could contain _PC_(FLASH)_ OR _8.html in
					// filename. Therefore safest to use encoding format in this case
					if(strpos($format, 'PC (Flash)') !== false) {
						$screencastUserMedia['embed']    = str_replace(".html", "", $url) . "/index.html";
						$screencastUserMedia['download'] = str_replace(".html", "", $url) . "/media/video.mp4";
						// End here, we got our target
						break;
					}
					// b. Check if new 1080p encoding (encoding ID==39) is in array
					// NOTE: Here we use URL since '_39.html' is the only
					if(strpos($url, '_39.html') !== false) {
						$screencastUserMedia['embed']    = str_replace(".html", "", $url) . "/index.html";
						$screencastUserMedia['download'] = str_replace(".html", "", $url) . "/media/video.mp4";
						// End here, we got our target
						break;
					}
				}


				// Recreate a new representation of relevant metadata, one for each presentation
				$response['media'][] = array(
					'title'       => (String)$screencastUserXml->title,
					'description' => (String)$screencastUserXml->description,
					'presenter'   => array(
						'name'     => (String)$screencastUserXml->presenter->displayName,
						'email'    => (String)$screencastUserXml->presenter->email,
						'username' => (String)$screencastUserXml->presenter->userName
					),
					'server_path' => $xml_path,
					'files'       => $screencastUserMedia,
					'preview'     => $screencastMediaPreview,

					'duration'    => array(
						'duration_ms'    => (int)$screencastUserXml->trimmedDuration,
						'duration_human' => $this->formatMilliseconds((int)$screencastUserXml->trimmedDuration),
					),
					'time'        => array(
						'date'      => strtolower(date("d M Y", $timestamp)),  // FIXED | e.g. 02 feb 2012
						'year'      => (int)date("Y", $timestamp),             // FIXED | e.g. 2012
						'month'     => (int)date("m", $timestamp),             // FIXED | e.g. 1
						'day'       => (int)date("d", $timestamp),             // FIXED | e.g. 01
						'time'      => date("H:i", $timestamp),                // FIXED | e.g. 16:47 (Custom time formatting),
						'timestamp' => $timestamp
					),
					'profile'     => (String)$screencastUserXml->profile,
					'resolution'  => array(
						'width'  => (int)$screencastMediaResolution[0],
						'height' => (int)$screencastMediaResolution[1]
					)
				);
			}
			return $response;
		}


		/******* HELPERS *******/

		/**
		 * Recursive GLOB that grabs all user XML files and places these in
		 * an array object.
		 */
		private function getUserXMLsRecursive($dir, $ext, &$screencastUserXMLs) {

			$globFiles = glob("$dir/*.$ext");
			$globDirs  = glob("$dir/*", GLOB_ONLYDIR);

			foreach($globDirs as $dir) {
				$this->getUserXMLsRecursive($dir, $ext, $screencastUserXMLs);
			}

			foreach($globFiles as $file) {
				$path_parts = pathinfo($file);
				if(strpos($file, '_xmp.xml') === false) {
					$screencastUserXMLs[$path_parts['dirname']][] = $path_parts['basename'];
				}
			}
		}

		// Does not support flag GLOB_BRACE
		private function glob_recursive($pattern, $flags = 0) {
			$files = glob($pattern, $flags);
			foreach(glob(dirname($pattern) . '/*', GLOB_ONLYDIR | GLOB_NOSORT) as $dir) {
				$files = array_merge($files, $this->glob_recursive($dir . '/' . basename($pattern), $flags));
			}

			return $files;
		}

		/**
		 * Utility function:
		 *    Format milliseconds to the H:M:SS format. Useful since Relay recording duration is given in ms...
		 *
		 * @author    Simon Skrødal, 17.02.2012
		 * @todo
		 *
		 * @param    int (duration in milliseconds)
		 *
		 * @return    String    (H:M:SS)
		 */
		private function formatMilliseconds($milliseconds) {
			$seconds      = floor($milliseconds / 1000);
			$minutes      = floor($seconds / 60);
			$hours        = floor($minutes / 60);
			$milliseconds = $milliseconds % 1000;
			$seconds      = $seconds % 60;
			$minutes      = $minutes % 60;

			// Don't return H if recording is less than an hour
			if($hours > 0) {
				$format = '%02ut %02um %02us'; // append for milliseconds: .%03u
				$time   = sprintf($format, $hours, $minutes, $seconds); // Append for milliseconds: , $milliseconds);
			} else {
				$format = '%02um %02us';
				$time   = sprintf($format, $minutes, $seconds);
			}

			return rtrim($time, '0');
		}


	}