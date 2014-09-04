<?php

$VenusConfig = [];

$VenusConfig['venus_body_js'] = [
	'type' => AssetsManager::TYPE_JS,
	'skin' => 'venus',
	'assets' => [
		'//resources/jquery/jquery-2.1.1.min.js',

		//libraries/frameworks
		'//resources/wikia/libraries/modil/modil.js',
		'//resources/wikia/libraries/Ponto/ponto.js',
		'//resources/wikia/libraries/my.class/my.class.js',

		//core modules
		'//resources/wikia/modules/window.js',
		'//resources/wikia/modules/location.js',
		'//resources/wikia/modules/nirvana.js',
		'//resources/wikia/modules/loader.js',
		'//resources/wikia/modules/querystring.js',
		'//resources/wikia/modules/log.js',
		'//resources/wikia/modules/cookies.js',

		//tracker
		'#group_tracker_js',

		// jquery libs
		'//resources/wikia/libraries/jquery/throttle-debounce/jquery.throttle-debounce.js',
		'//resources/wikia/libraries/sloth/sloth.js',
		'//resources/wikia/jquery.wikia.js',

		// mw
		'//resources/mediawiki/mediawiki.js',
		'//skins/common/wikibits.js',

		//platform components
		'//extensions/wikia/JSMessages/js/JSMessages.js',
		'//extensions/wikia/JSSnippets/js/JSSnippets.js',
		'//extensions/wikia/AssetsManager/js/AssetsManager.js',

		'//extensions/wikia/Venus/scripts/isTouchScreen.js',
		'//extensions/wikia/Venus/scripts/Venus.js',
		'#function_AssetsConfig::getJQueryUrl',
	]
];

$VenusConfig['venus_head_js'] = [
	'type' => AssetsManager::TYPE_JS,
	'skin' => 'venus',
	'assets' => [
	]
];

$VenusConfig['venus_css'] = [
	'type' => AssetsManager::TYPE_SCSS,
	'skin' => 'venus',
	'assets' => [
		'//extensions/wikia/Venus/styles/Venus.scss'
	]
];

/** GlobalFooter extension */
$VenusConfig['global_footer_css'] = array(
	'type' => AssetsManager::TYPE_SCSS,
	'skin' => ['venus'],
	'assets' => array(
		'//extensions/wikia/GlobalFooter/styles/GlobalFooter.scss'
	)
);


/** GlobalNavigation extension */
$VenusConfig['global_navigation_scss'] = array(
	'type' => AssetsManager::TYPE_SCSS,
	'skin' => ['venus', 'oasis'],
	'assets' => array(
		'//extensions/wikia/GlobalNavigation/css/GlobalNavigation.scss',
		'//extensions/wikia/GlobalNavigation/css/GlobalNavigationSearch.scss'
	)
);

$VenusConfig['global_navigation_js'] = array(
	'type' => AssetsManager::TYPE_JS,
	'skin' => ['venus', 'oasis'],
	'assets' => array(
		'//extensions/wikia/GlobalNavigation/js/GlobalNavigationSearch.js',
	)
);
