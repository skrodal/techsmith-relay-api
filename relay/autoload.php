<?php
	/**
	 * @author Simon Skrødal
	 * @date   30/10/2015
	 * @time   10:09
	 */

	// Define the paths to the directories holding class files
	$paths = array(
		'config',
		'utils',
		'vendor',
		'auth',
		'api',
		'database'
	);
	// Add the paths to the class directories to the include path.
	set_include_path(dirname(__DIR__) . PATH_SEPARATOR . implode(PATH_SEPARATOR, $paths));
	// Add the file extensions to the SPL.
	spl_autoload_extensions(".class.php");
	// Register the default autoloader implementation in the php engine.
	spl_autoload_register();