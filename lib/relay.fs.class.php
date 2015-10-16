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
			'SCREENCAST_URL'            => 'https://screencast.uninett.no',
			'SCREENCAST_EMPLOYEE_URL'   => 'https://screencast.uninett.no/relay/ansatt/',
			'SCREENCAST_STUDENT_URL'    => 'https://screencast.uninett.no/relay/student/',
			'SCREENCAST_PATH'           => '/var/www/mnt/relaymedia_cache/',
			'SCREENCAST_EMPLOYEE_PATH'  => '/var/www/mnt/relaymedia_cache/ansatt/',
			'SCREENCAST_STUDENT_PATH'   => '/var/www/mnt/relaymedia_cache/student/',
			'SCREENCAST_DELETE_LIST'	=> '/var/www/mnt/relaymedia_cache/relaymedia_deletelist'			// NOT IN USE
		);

		function __construct(Relay $relay, FeideConnect $connect) {
			$this->relay = $relay;
			$this->feideConnect = $connect;
		}


		function getRelayUserMedia($feideUserName){
			global $RELAY_CONFIG;

			$this->relay->getUser($feideUserName);

/*

			$isEmployee = true;
			// Establish root directories for this user
			if(strpos($feideAffiliation, 'employee') 	   !== false) { $screencast_user_root = $RELAY_CONFIG['SCREENCAST_EMPLOYEE_PATH'] . $feideUserName; }
			else if (strpos($feideAffiliation, 'student') !== false) { $screencast_user_root = $RELAY_CONFIG['SCREENCAST_STUDENT_PATH'] . $feideUserName; $isEmployee = false; }
			else { $response['details'] = "Missing affiliation for user " . $feideUserName; return $response;}

			// Ensure that user has content
			if(!file_exists( $screencast_user_root )) { $response['details'] = "Fant ikke noe innhold for bruker " . $feideUserName; return $response; }
			//
			$response['status'] = true;
			// Caching...
			if(!apc_exists('getRelayUserMedia.' . $feideUserName)) {

				error_log('NO CACHE');

				// Grab single xml file per presentation
				$screencast_user_xmls = getRelayXMLFiles($screencast_user_root);

				// Iterate each folder where an XML file lives and use some of the metadata in this file
				foreach ($screencast_user_xmls as $file) {
					try {
						// Get XML metadata
						$screencast_user_xml = simplexml_load_file($file);
						// Parses about any English textual datetime description into a Unix timestamp, like Relay's "1/16/2012 10:58:41 AM"
						$timestamp = strtotime($screencast_user_xml->date); // = 1326707921
						//
						//$path_parts = pathinfo($file);
						//
						$screencast_presentation_url = $isEmployee ?
							$RELAY_CONFIG['SCREENCAST_EMPLOYEE_URL'] . substr($file, strpos($file, $feideUserName)) :
							$RELAY_CONFIG['SCREENCAST_STUDENT_URL'] . substr($file, strpos($file, $feideUserName));

						// Only single MP3 produced
						$fileMP3 = glob(dirname($file) . "/*.mp3");
						$fileMP3 = basename($fileMP3[0]);	// basename -> Get filename only, strip away path.
						// Only single html produced
						$fileHTML = glob(dirname($file) . "/*.html");
						$fileHTML = basename($fileHTML[0]);
						// Two MP4s produced. Safe to assume that mobile version has smaller filesize
						$filesPortable = glob(dirname($file) . "/*.mp4");
						if(sizeof($filesPortable) >=2){
							if( filesize($filesPortable[0]) > filesize($filesPortable[1]) ){
								$fileMobile = basename($filesPortable[1]); $fileTablet = basename($filesPortable[0]);
							} else {
								$fileMobile = basename($filesPortable[0]); $fileTablet = basename($filesPortable[1]);
							}
						}
						/*
						error_log(dirname($screencast_presentation_url) . '/' . $fileHTML);
						error_log(dirname($screencast_presentation_url) . '/' . $fileTablet);
						error_log(dirname($screencast_presentation_url) . '/' . $fileMobile);
						error_log(dirname($screencast_presentation_url) . '/' . $fileMP3);
						error_log('\n\n');
						// error_log(dirname($screencast_presentation_url));
						//filesize($filename)
						*/
/*
						$screencast_user_media = array(
							'pc'        => dirname($screencast_presentation_url) . '/' . $fileHTML,
							'nettbrett' => dirname($screencast_presentation_url) . '/' . $fileTablet,
							'mobil' 	=> dirname($screencast_presentation_url) . '/' . $fileMobile,
							'lyd' 		=> dirname($screencast_presentation_url) . '/' . $fileMP3,
						);
						// Preview in a subfolder with same name as file for PC sans ".html"...
						$screencast_media_preview = str_replace('.html', '', $screencast_user_media['pc']) .'/media/video_thumb.jpg';

						$screencast_media_resolution = explode("x", $screencast_user_xml->sourceRecording->resolution);

						// Recreate a new representation of relevant metadata
						$response['media'][] = array(
							'title'       => (String)$screencast_user_xml->title,
							'description' => (String)$screencast_user_xml->description,
							'presenter'   =>  array(
								'name'      => (String)$screencast_user_xml->presenter->displayName,
								'email'     => (String)$screencast_user_xml->presenter->email,
								'username'  => (String)$screencast_user_xml->presenter->userName
							),
							'server_path'	=>	dirname($file),
							'files'       =>  $screencast_user_media,
							'preview'		=>  $screencast_media_preview,

							'duration'    =>  array(
								'duration_ms'		=>	(int)$screencast_user_xml->trimmedDuration,
								'duration_human'	=>  formatMilliseconds((int)$screencast_user_xml->trimmedDuration),
							),
							'time'        =>  array(
								'date'  		=>  strtolower(date("d M Y", $timestamp)),  // FIXED | e.g. 02 feb 2012
								'year'  		=>  (int)date("Y", $timestamp),             // FIXED | e.g. 2012
								'month' 		=>  (int)date("m", $timestamp),             // FIXED | e.g. 1
								'day'   		=>  (int)date("d", $timestamp),             // FIXED | e.g. 01
								'time'  		=>  date("H:i", $timestamp),                // FIXED | e.g. 16:47 (Custom time formatting),
								'timestamp' 	=>	$timestamp
							),
							'profile'     =>  (String)$screencast_user_xml->profile,
							'resolution'  =>  array(
								'width'		=> (int)$screencast_media_resolution[0],
								'height'	=> (int)$screencast_media_resolution[1]
							)
						);
					} catch (Exception $e) { $response['status'] = false; $response['details'] = $e; return $response; }
				}
				apc_add('getRelayUserMedia.' . $feideUserName, $response, CACHE_SCREENCASTREQUESTS_TTL);
			}
			//
			return apc_fetch('getRelayUserMedia.' . $feideUserName);
*/
		}




	}