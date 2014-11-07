<?php

/**
 * Class VideoFeedIngester
 */
abstract class VideoFeedIngester {

	// Constants for referring to short provider names
	const PROVIDER_SCREENPLAY = 'screenplay';
	const PROVIDER_IGN = 'ign';
	const PROVIDER_ANYCLIP = 'anyclip';
	const PROVIDER_OOYALA = 'ooyala';
	const PROVIDER_IVA = 'iva';
	const PROVIDER_CRUNCHYROLL = 'crunchyroll';
	const PROVIDER_MAKER_STUDIOS = 'makerstudios';

	// Caching constants; all integers are seconds
	const CACHE_KEY = 'videofeedingester-2';
	const CACHE_EXPIRY = 3600;
	const THROTTLE_INTERVAL = 1;

	// Names a city variable to look for additional category data.  Used in the reingestBrokenVideo.php
	const WIKI_INGESTION_DATA_VARNAME = 'wgPartnerVideoIngestionData';

	// Determines if a duplicate video found should be re-uploaded or ignored
	public $reupload = false;

	// Providers from which we ingest daily video data
	protected static $ACTIVE_PROVIDERS = [
		self::PROVIDER_IGN,
		self::PROVIDER_OOYALA,
		self::PROVIDER_IVA,
		self::PROVIDER_SCREENPLAY,
		self::PROVIDER_CRUNCHYROLL,
		self::PROVIDER_MAKER_STUDIOS
	];

	// These providers are not ingested daily, but can be ingested from if specifically named
	protected static $INACTIVE_PROVIDERS = [
		self::PROVIDER_ANYCLIP,
	];

	protected static $API_WRAPPER;
	protected static $PROVIDER;
	protected static $FEED_URL;
	protected static $CLIP_TYPE_BLACKLIST = array();
	protected static $CLIP_FILTER = array();
	protected $filterByProviderVideoId = array();

	protected $resultSummary = [
		'found'    => 0,
		'ingested' => 0,
		'skipped'  => 0,
		'warnings' => 0,
		'errors'   => 0,
	];

	protected $resultIngestedVideos = [
		'Games'         => [],
		'Entertainment' => [],
		'Lifestyle'     => [],
		'International' => [],
		'Other'         => [],
	];

	protected $defaultRequestOptions = [
		'noProxy' => true
	];

	private static $WIKI_INGESTION_DATA_FIELDS = array( 'keyphrases' );

	abstract public function import( $content = '', $params = array() );

	/**
	 * Create a list of category names to add to the new file page
	 * @param array $data - Video data
	 * @param $addlCategories - Any additional categories to add
	 * @return array - A list of category names
	 */
	abstract public function generateCategories( array $data, $addlCategories );

	/**
	 * Return a list of all the providers we actively ingest from
	 * @return array
	 */
	public static function activeProviders() {
		return self::$ACTIVE_PROVIDERS;
	}

	/**
	 * Return a list of all the providers that are legal to ingest from but from whom
	 * we do not ingest automatically.
	 * @return array
	 */
	public static function inactiveProviders() {
		return self::$INACTIVE_PROVIDERS;
	}

	/**
	 * Return a list of all available providers
	 * @return array
	 */
	public static function allProviders() {
		return array_merge( self::$ACTIVE_PROVIDERS, self::$INACTIVE_PROVIDERS );
	}

	/**
	 * Generate name for video.
	 * Note: The name is not sanitized for use as filename or article title.
	 * @param array $data video data
	 * @return string video name
	 */
	protected function generateName( array $data ) {
		return $data['titleName'];
	}

	/**
	 * generate the metadata we consider interesting for this video
	 * Note: metadata is array instead of object because it's stored in the database as a serialized array,
	 *       and serialized objects would have more version issues.
	 * @param array $data - Video data
	 * @param $errorMsg - Store any error we encounter
	 * @return array|int - An associative array of meta data or zero on error
	 */
	public function generateMetadata( array $data, &$errorMsg ) {
		if ( empty( $data['videoId'] ) ) {
			$errorMsg = 'no video id exists';
			return 0;
		}

		$metadata = array(
			'videoId'              => $data['videoId'],
			'altVideoId'           => isset( $data['altVideoId'] ) ? $data['altVideoId'] : '',
			'hd'                   => isset( $data['hd'] ) ? $data['hd'] : 0,
			'duration'             => isset( $data['duration'] ) ? $data['duration'] : '',
			'published'            => isset( $data['published'] ) ? $data['published'] : '',
			'thumbnail'            => isset( $data['thumbnail'] ) ? $data['thumbnail'] : '',
			'description'          => isset( $data['description'] ) ? $data['description'] : '',
			'name'                 => isset( $data['name'] ) ? $data['name'] : '',
			'type'                 => isset( $data['type'] ) ? $data['type'] : '',
			'category'             => isset( $data['category'] ) ? $data['category'] : '',
			'keywords'             => isset( $data['keywords'] ) ? $data['keywords'] : '',
			'industryRating'       => isset( $data['industryRating'] ) ? $data['industryRating'] : '',
			'ageGate'              => isset( $data['ageGate'] ) ? $data['ageGate'] : 0,
			'ageRequired'          => isset( $data['ageRequired'] ) ? $data['ageRequired'] : 0,
			'provider'             => isset( $data['provider'] ) ? $data['provider'] : '',
			'language'             => isset( $data['language'] ) ? $data['language'] : '',
			'subtitle'             => isset( $data['subtitle'] ) ? $data['subtitle'] : '',
			'genres'               => isset( $data['genres'] ) ? $data['genres'] : '',
			'actors'               => isset( $data['actors'] ) ? $data['actors'] : '',
			'targetCountry'        => isset( $data['targetCountry'] ) ? $data['targetCountry'] : '',
			'series'               => isset( $data['series'] ) ? $data['series'] : '',
			'season'               => isset( $data['season'] ) ? $data['season'] : '',
			'episode'              => isset( $data['episode'] ) ? $data['episode'] : '',
			'characters'           => isset( $data['characters'] ) ? $data['characters'] : '',
			'resolution'           => isset( $data['resolution'] ) ? $data['resolution'] : '',
			'aspectRatio'          => isset( $data['aspectRatio'] ) ? $data['aspectRatio'] : '',
			'expirationDate'       => isset( $data['expirationDate'] ) ? $data['expirationDate'] : '',
			'regionalRestrictions' => isset( $data['regionalRestrictions'] ) ? $data['regionalRestrictions'] : '',
		);

		return $metadata;
	}

	/**
	 *  If  $this->filterByProviderVideoId  is not empty, the ingestion script will only upload the videos
	 *  that are in the array
	 * @param $id
	 */
	public function setFilter( $id ) {

		if ( !in_array( $id, $this->filterByProviderVideoId ) ) {
			$this->filterByProviderVideoId[] = $id;
		}
	}

	/**
	 * @param array $data
	 * @param $msg
	 * @param array $params
	 * @return int
	 */
	public function createVideo( array $data, &$msg, array $params = [] ) {

		// See if this video is blacklisted (exact match against any data)
		if ( $this->isBlacklistedVideo( $data ) ) {
			$this->videoSkipped( "Skipping (due to \$CLIP_TYPE_BLACKLIST) '{$data['titleName']}' - {$data['description']}.\n" );
			return 0;
		}

		// See if this video should be filtered (regex match against specific fields)
		if ( $this->isFilteredVideo( $data ) ) {
			$this->videoSkipped( "Skipping (due to \$CLIP_FILTER) '{$data['titleName']}' - {$data['description']}.\n" );
			return 0;
		}

		$this->filterKeywords( $data['keywords'] );

		$debug = !empty( $params['debug'] );
		$remoteAsset = !empty( $params['remoteAsset'] );
		$ignoreRecent = !empty( $params['ignorerecent'] ) ? $params['ignorerecent'] : 0;
		if ( $debug ) {
			print "data after initial processing: \n";
			foreach ( explode( "\n", var_export( $data, 1 ) ) as $line ) {
				print ":: $line\n";
			}
		}
		$addlCategories = empty( $params['addlCategories'] ) ? array() : $params['addlCategories'];

		$id = $data['videoId'];
		$name = $this->generateName( $data );
		$metadata = $this->generateMetadata( $data, $msg );
		if ( !empty( $msg ) ) {
			$this->videoWarnings( "Error when generating metadata.\n" );
			var_dump( $msg );
			return 0;
		}

		$provider = empty( $params['provider'] ) ? static::$PROVIDER : $params['provider'];

		// check if the video id exists in Ooyala.
		if ( $remoteAsset ) {
			$dupAssets = OoyalaAsset::getAssetsBySourceId( $id, $provider );
			if ( !empty( $dupAssets ) ) {
				if ( $this->reupload === false ) {
					$this->videoSkipped( "Skipping $name (Id: $id, $provider) - video already exists in remote assets.\n" );
					return 0;
				}
			}
		}

		$duplicates = WikiaFileHelper::findVideoDuplicates( $provider, $id, $remoteAsset );
		$dup_count = count( $duplicates );
		$previousFile = null;
		if ( $dup_count > 0 ) {
			if ( $this->reupload === false ) {
				// if reupload is disabled finish now
				$this->videoSkipped( "Skipping $name (Id: $id, $provider) - video already exists and reupload is disabled.\n" );
				return 0;
			}

			// if there are duplicates use name of one of them as reference
			// instead of generating new one
			$name = $duplicates[0]['img_name'];
			echo "Video already exists, using it's old name: $name\n";
			$previousFile = Title::newFromText( $name, NS_FILE );
		} else {
			// sanitize name
			$name = VideoFileUploader::sanitizeTitle( $name );
			// make sure the name is unique
			$name = $this->getUniqueName( $name );
		}
		$metadata['destinationTitle'] = $name;

		if ( !$this->validateTitle( $id, $name, $msg ) ) {
			$this->videoWarnings( "Error: $msg\n" );
			return 0;
		}

		// create category names to add to the new file page
		$categories = $this->generateCategories( $data, $addlCategories );

		// create remote asset (ooyala)
		if ( $remoteAsset ) {
			$metadata['pageCategories'] = implode( ', ', $categories );
			if ( !empty( $dupAssets ) ) {
				if ( !empty( $dupAssets[0]['metadata']['sourceid'] ) && $dupAssets[0]['metadata']['sourceid'] == $id ) {
					$result = $this->updateRemoteAsset( $id, $name, $metadata, $debug, $dupAssets[0] );
				} else {
					$this->videoSkipped( "Skipping {$metadata['name']} - {$metadata['description']}. SouceId not match (Id: $id).\n" );
					return 0;
				}
			} else {
				$result = $this->createRemoteAsset( $id, $name, $metadata, $debug );
			}

			return $result;
		}

		// prepare wiki categories string (eg [[Category:MyCategory]] )
		$categories[] = wfMessage( 'videohandler-category' )->inContentLanguage()->text();
		$categories = array_unique( $categories );
		$categoryStr = '';
		foreach ( $categories as $categoryName ) {
			$category = Category::newFromName( $categoryName );
			if ( $category instanceof Category ) {
				$categoryStr .= '[[' . $category->getTitle()->getFullText() . ']]';
			}
		}

		// prepare article body
		/** @var ApiWrapper $apiWrapper */
		$apiWrapper = new static::$API_WRAPPER( $id, $metadata );

		// add category
		$body = $categoryStr."\n";

		// add description header
		$videoHandlerHelper = new VideoHandlerHelper();
		$body .= $videoHandlerHelper->addDescriptionHeader( $apiWrapper->getDescription() );

		if ( $debug ) {
			print "Ready to create video\n";
			print "id:          $id\n";
			print "name:        $name\n";
			print "categories:  " . implode( ',', $categories ) . "\n";
			print "metadata:\n";
			foreach ( explode( "\n", var_export( $metadata, 1 ) ) as $line ) {
				print ":: $line\n";
			}

			print "body:\n";
			foreach ( explode( "\n", $body ) as $line ) {
				print ":: $line\n";
			}

			$this->videoIngested( "Ingested $name (id: $id).\n", $categories );

			return 1;
		} else {
			if ( !empty( $ignoreRecent ) && !is_null( $previousFile ) ) {
				$revId = $previousFile->getLatestRevID();
				$revision = Revision::newFromId( $revId );
				$time = $revision->getTimestamp();
				$timeUnix = intval( wfTimestamp( TS_UNIX, $time ) );
				$timeNow = intval( wfTimestamp( TS_UNIX, time() ) );
				if ( $timeUnix + $ignoreRecent >= $timeNow ) {
					$this->videoSkipped( "Recently uploaded, ignoring\n" );
					return 0;
				}
			}
			/** @var Title $uploadedTitle */
			$uploadedTitle = null;
			$result = VideoFileUploader::uploadVideo( $provider, $id, $uploadedTitle, $body, false, $metadata );
			if ( $result->ok ) {
				$fullUrl = WikiFactory::getLocalEnvURL( $uploadedTitle->getFullURL() );
				$this->videoIngested( "Ingested {$uploadedTitle->getText()} from partner clip id $id. {$fullUrl}\n", $categories );

				wfWaitForSlaves( self::THROTTLE_INTERVAL );
				wfRunHooks( 'VideoIngestionComplete', array( $uploadedTitle, $categories ) );
				return 1;
			}
		}

		$this->videoWarnings();

		return 0;
	}

	/**
	 * Create remote asset
	 * @param string $id
	 * @param string $name
	 * @param array $metadata
	 * @param boolean $debug
	 * @return integer
	 */
	protected function createRemoteAsset( $id, $name, array $metadata, $debug ) {

		$assetData = $this->generateRemoteAssetData( $name, $metadata );
		if ( empty( $assetData['url']['flash'] ) ) {
			$this->videoWarnings( "Error when generating remote asset data: empty asset url.\n" );
			return 0;
		}

		if ( empty( $assetData['duration'] ) || $assetData['duration'] < 0 ) {
			$this->videoWarnings( "Error when generating remote asset data: invalid duration ($assetData[duration]).\n" );
			return 0;
		}

		// check if video title exists
		$ooyalaAsset = new OoyalaAsset();
		$isExist = $ooyalaAsset->isTitleExist( $assetData['assetTitle'], $assetData['provider'] );
		if ( $isExist ) {
			$this->videoSkipped( "SKIP: Uploading Asset: $name ($assetData[provider]). Video already exists in remote assets.\n" );
			return 0;
		}

		if ( $debug ) {
			print "Ready to create remote asset\n";
			print "id:          $id\n";
			print "name:        $name\n";
			print "assetdata:\n";
			foreach ( explode( "\n", var_export( $assetData, TRUE ) ) as $line ) {
				print ":: $line\n";
			}
		} else {
			$result = $ooyalaAsset->addRemoteAsset( $assetData );
			if ( !$result ) {
				$this->videoWarnings();
				return 0;
			}
		}

		$categories = empty( $metadata['pageCategories'] ) ? [] : explode( ", ", $metadata['pageCategories'] );
		$this->videoIngested( "Uploaded remote asset: $name (id: $id)\n", $categories );

		return 1;
	}

	/**
	 * Update remote asset (metadata only)
	 * @param string $id
	 * @param string $name
	 * @param array $metadata
	 * @param boolean $debug
	 * @param array $dupAsset
	 * @return integer
	 */
	protected function updateRemoteAsset( $id, $name, array $metadata, $debug, $dupAsset ) {

		if ( empty( $dupAsset['embed_code'] ) ) {
			$this->videoWarnings( "Error when updating remote asset data: empty asset embed code.\n" );
			return 0;
		}

		$assetData = $this->generateRemoteAssetData( $dupAsset['name'], $metadata, false );

		$ooyalaAsset = new OoyalaAsset();
		$assetMeta = $ooyalaAsset->getAssetMetadata( $assetData );

		// set reupload
		$assetMeta['reupload'] = 1;

		// remove unwanted data
		$emptyMetaKeys = array_diff( array_keys( $dupAsset['metadata'] ), array_keys( $assetMeta ) );
		foreach ( $emptyMetaKeys as $key ) {
			$assetMeta[$key] = null;
		}

		if ( $debug ) {
			print "Ready to update remote asset\n";
			print "id:          $id\n";
			print "name:        $name\n";
			print "embed code:  $dupAsset[embed_code]\n";
			print "asset name:  $dupAsset[name]\n";
			print "metadata:\n";
			foreach ( explode( "\n", var_export( $assetMeta, TRUE ) ) as $line ) {
				print ":: $line\n";
			}
		} else {
			$result = OoyalaAsset::updateMetadata( $dupAsset['embed_code'], $assetMeta );
			if ( !$result ) {
				$this->videoWarnings();
				return 0;
			}
		}

		$categories = empty( $metadata['pageCategories'] ) ? [] : explode( ", ", $metadata['pageCategories'] );
		$this->videoIngested( "Uploaded remote asset: $name (id: $id)\n", $categories );

		return 1;
	}

	/**
	 * Generate remote asset data
	 * @param string $name
	 * @param array $data
	 * @return array $data
	 */
	protected function generateRemoteAssetData( $name, array $data ) {
		$data['assetTitle'] = $name;

		return $data;
	}

	/**
	 * @param $name
	 * @return string
	 */
	protected function getUniqueName( $name ) {
		$name_final = $name;
		$i = 2;
		// is this name available?
		$title = Title::newFromText( $name_final, NS_FILE );
		while ( $title && $title->exists() ) {
			$name_final = $name . ' ' . $i;
			$i++;
			$title = Title::newFromText( $name_final, NS_FILE );
		}
		return $name_final;
	}

	/**
	 * Returns whether the given video title has a value title object
	 * @param $videoId
	 * @param $name
	 * @param $msg
	 * @return bool
	 */
	protected function validateTitle( $videoId, $name, &$msg ) {

		$sanitizedName = VideoFileUploader::sanitizeTitle( $name );
		$title = Title::newFromText( $sanitizedName, NS_FILE );
		if ( is_null( $title ) ) {
			$msg = "article title was null: clip id $videoId. name: $name";
			return false;
		}
		return true;
	}

	/**
	 * @return array
	 */
	public function getWikiIngestionData() {

		$data = array();

		// merge data from datasource into a data structure keyed by
		// partner API search keywords. Value is an array of categories
		// relevant to wikis
		$rawData = $this->getWikiIngestionDataFromSource();
		foreach ( $rawData as $cityId => $cityData ) {
			if ( is_array( $cityData ) ) {
				foreach ( self::$WIKI_INGESTION_DATA_FIELDS as $field ) {
					if ( !empty( $cityData[$field] ) && is_array( $cityData[$field] ) ) {
						foreach ( $cityData[$field] as $fieldVal ) {
							if ( !empty( $data[$field][$fieldVal] ) && is_array( $data[$field][$fieldVal] ) ) {
								$data[$field][$fieldVal] = array_merge( $data[$field][$fieldVal], $cityData['categories'] );
							} else {
								$data[$field][$fieldVal] = $cityData['categories'];
							}
						}
					}
				}
			}
		}

		return $data;
	}

	/**
	 * @return array|bool|Object
	 */
	protected function getWikiIngestionDataFromSource() {
		global $wgExternalSharedDB, $wgMemc;

		$memcKey = wfMemcKey( self::CACHE_KEY );
		$aWikis = $wgMemc->get( $memcKey );
		if ( !empty( $aWikis ) ) {
			return $aWikis;
		}

		$aWikis = array();

		// fetch data from DB
		// note: as of 2011/11, this function is referred to by only one
		// calling function, a script that is run once per day. No need
		// to memcache result yet.
		$dbr = wfGetDB( DB_SLAVE, array(), $wgExternalSharedDB );

		$aTables = array(
			'city_variables',
			'city_variables_pool',
			'city_list',
		);
		$varName = mysql_real_escape_string( self::WIKI_INGESTION_DATA_VARNAME );
		$aWhere = array( 'city_id = cv_city_id', 'cv_id = cv_variable_id' );

		$aWhere[] = "cv_value is not null";

		$aWhere[] = "cv_name = '$varName'";


		$oRes = $dbr->select(
			$aTables,
			array( 'city_id', 'cv_value' ),
			$aWhere,
			__METHOD__,
			array( 'ORDER BY' => 'city_sitename' )
		);

		while ( $oRow = $dbr->fetchObject( $oRes ) ) {
			$aWikis[$oRow->city_id] = unserialize( $oRow->cv_value );
		}
		$dbr->freeResult( $oRes );

		$wgMemc->set( $memcKey, $aWikis, self::CACHE_EXPIRY );

		return $aWikis;
	}

	/**
	 * @param $url
	 * @param $options
	 * @return string
	 */
	protected function getUrlContent( $url, $options = array() ) {
		$options = array_merge( $options, $this->defaultRequestOptions );
		return Http::request( 'GET', $url, $options );
	}

	/**
	 * Try to find keyphrase in the subject. A keyphrase could be
	 * "harry potter". A keyphrase is present in the subject if "harry" and
	 * "potter" are present.
	 * @param string $subject
	 * @param string $keyphrase
	 * @return boolean
	 */
	protected function isKeyphraseInString( $subject, $keyphrase ) {
		$keyphraseFound = false;
		$keywords = explode( ' ', $keyphrase );
		$keywordMissing = false;
		foreach ( $keywords as $keyword ) {
			if ( stripos( $subject, $keyword ) === false ) {
				$keywordMissing = true;
				break;
			}
		}
		if ( !$keywordMissing ) {
			$keyphraseFound = true;
		}

		return $keyphraseFound;
	}

	/**
	 * @param array $clipData
	 * @return bool
	 */
	protected function isClipTypeBlacklisted( array $clipData ) {
		// assume that a clip with properties that match exactly undesired
		// values should not be imported. This assumption will have to
		// change if we consider values that fall into a range, such as
		// duration < MIN_VALUE
		if ( !empty( static::$CLIP_TYPE_BLACKLIST ) && is_array( static::$CLIP_TYPE_BLACKLIST ) ) {
			$arrayIntersect = array_intersect( static::$CLIP_TYPE_BLACKLIST, $clipData );
			return ( !empty( $arrayIntersect ) && $arrayIntersect == static::$CLIP_TYPE_BLACKLIST );
		}

		return false;
	}

	/**
	 * Tests whether this video should be filtered out because of a string in its metadata.
	 *
	 * Set the $CLIP_FILTER static associative array in the child class to match a particular key or
	 * use '*' to match any key, e.g.:
	 *
	 *     $CLIP_FILTER = array( '*'        => '/Daily/',
	 *                           'keywords' => '/Adult/i',
	 *                         )
	 *
	 * This would filter out videos where any data contained the word 'Daily' and any video where the
	 * keywords contained the case insensitive string 'adult'
	 *
	 * @param array $clipData - The video data
	 * @return bool - Returns true if the video should be filtered out, false otherwise
	 */
	protected function isFilteredVideo( array $clipData ) {
		if ( is_array( static::$CLIP_FILTER ) ) {
			foreach ( $clipData as $key => $value ) {
				// See if we match key explicitly or by the catchall '*'
				$regex_list = empty( static::$CLIP_FILTER['*'] ) ? '' : static::$CLIP_FILTER['*'];
				$regex_list = empty( static::$CLIP_FILTER[$key] ) ? $regex_list : static::$CLIP_FILTER[$key];

				// If we don't have  regex at this point, skip this bit of clip data
				if ( empty( $regex_list ) ) {
					continue;
				}

				// This can be a single regex or a list of regexes
				$regex_list = is_array( $regex_list ) ? $regex_list : array( $regex_list );

				foreach ( $regex_list as $regex ) {
					if ( preg_match( $regex, $value ) ) {
						return true;
					}
				}
			}
		}

		return false;
	}

	/**
	 * get regex
	 * @param $keywords string with comma-separated keywords
	 * @return string regexp or null if no valid keywords were specified
	 */
	protected function getBlacklistRegex( $keywords ) {
		$regex = null;
		if ( $keywords ) {
			$keywords = explode( ',', $keywords );
			$blacklist = array();
			foreach ( $keywords as $word ) {
				$word = preg_replace( "/[^A-Za-z0-9' ]/", "", trim( $word ) );
				if ( $word ) {
					$blacklist[] = $word;
				}
			}

			if ( !empty( $blacklist ) ) {
				$regex = '/\b('.implode( '|', $blacklist ).')\b/i';
			}
		}

		return $regex;
	}

	/**
	 * check if video is blacklisted ( titleName, description, keywords, name )
	 * @param array $data
	 * @return boolean
	 */
	public function isBlacklistedVideo( array $data ) {

		// General filter on all keywords
		$regex = $this->getBlacklistRegex( F::app()->wg->VideoBlacklist );
		if ( !empty( $regex ) ) {
			$keys = array( 'titleName', 'description' );
			if ( array_key_exists( 'keywords', $data ) ) {
				$keys[] = 'keywords';
			}
			if ( array_key_exists( 'name', $data ) ) {
				$keys[] = 'name';
			}
			foreach ( $keys as $key ) {
				if ( preg_match( $regex, str_replace( '-', ' ', $data[$key] ) ) ) {
					echo "Blacklisting video: ".$data['titleName'].", videoId ".$data['videoId']." (reason $key: ".$data[$key].")\n";
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * filter keywords
	 * @param string $keywords (comma-separated string)
	 */
	protected function filterKeywords( &$keywords ) {
		if ( !empty( $keywords ) ) {
			$regex = $this->getBlacklistRegex( F::app()->wg->VideoKeywordsBlacklist );
			$new = array();
			if ( !empty( $regex ) ) {
				$old = explode( ',', $keywords );
				foreach ( $old as $word ) {
					if ( preg_match( $regex, str_replace( '-', ' ', $word ) ) ) {
						echo "Skip: blacklisted keyword $word.\n";
						continue;
					}

					$new[] = $word;
				}
			}

			if ( !empty( $new ) ) {
				$keywords = implode( ',', $new );
			}
		}
	}

	/**
	 * Get normalized industry rating
	 * @param string $rating
	 * @return string
	 */
	public function getIndustryRating( $rating ) {
		return IngesterDataNormalizer::getNormalizedIndustryRating( $rating );
	}

	/**
	 * Get age required from industry rating
	 * @param string $rating
	 * @return int
	 */
	public function getAgeRequired( $rating ) {
		return IngesterDataNormalizer::getNormalizedAgeRequired( $rating );
	}

	/**
	 * get normalized category
	 * @param string $category
	 * @return string
	 */
	public function getCategory( $category ) {
		return IngesterDataNormalizer::getNormalizedCategory( $category );
	}

	/**
	 * get normalized type
	 * @param string $type
	 * @return string
	 */
	public function getType( $type ) {
		return IngesterDataNormalizer::getNormalizedType( $type );
	}

	/**
	 * get normalized genre
	 * @param string $genre
	 * @return string
	 */
	public function getGenre( $genre ) {
		return IngesterDataNormalizer::getNormalizedGenre( $genre );
	}

	/**
	 * get normalized page category
	 * @param string $pageCategory
	 * @return string
	 */
	public function getPageCategory( $pageCategory ) {
		return IngesterDataNormalizer::getNormalizedPageCategory( $pageCategory );
	}

	/**
	 * Get list of additional page category
	 * @param array $categories
	 * @return array $pageCategories
	 */
	public function getAdditionalPageCategories( array $categories ) {
		$pageCategories = array();
		foreach ( $categories as $category ) {
			$addition = $this->getAdditionalPageCategory( $category );
			if ( !empty( $addition ) ) {
				$pageCategories[] = $addition;
			}
		}

		return $pageCategories;
	}

	/**
	 * Get additional page category
	 * @param string $category
	 * @return string
	 */
	public function getAdditionalPageCategory( $category ) {
		return IngesterDataNormalizer::getNormalizedAdditionalPageCategory( $category );
	}

	/**
	 * get CLDR code (return the original value if code not found)
	 * @param string $value
	 * @param string $type [language|country]
	 * @param boolean $code
	 * @return string $value
	 */
	public function getCLDRCode( $value, $type = 'language', $code = true ) {
		return IngesterDataNormalizer::getCLDRCode( $value, $type, $code );
	}

	/**
	 * get unique array (case insensitive)
	 * @param array $arr
	 * @return array $unique
	 */
	public function getUniqueArray( array $arr ) {
		$lower = array_map( 'strtolower', $arr );
		$unique = array_intersect_key( $arr, array_unique( $lower ) );
		return array_filter( $unique );
	}

	/**
	 * Set summary result for video found
	 * @param integer $num - the number of video found
	 */
	public function videoFound( $num ) {
		$msg = "Found $num videos.\n";
		$this->setResultSummary( 'found', $msg, $num );
	}

	/**
	 * Set summary result for skipped video
	 * @param string $msg
	 */
	public function videoSkipped( $msg = '' ) {
		$this->setResultSummary( 'skipped', $msg );
	}

	/**
	 * Set summary result for ingested video
	 * @param string $msg
	 * @param array $categories
	 */
	public function videoIngested( $msg = '', array $categories = [] ) {
		if ( !empty( $msg ) ) {
			$addedResult = false;
			foreach ( $categories as $category ) {
				if ( array_key_exists( $category, $this->resultIngestedVideos ) ) {
					$this->resultIngestedVideos[$category][] = $msg;
					$addedResult = true;
					break;
				}
			}

			// If this video is in some other category, make sure it still gets into the report
			if ( !$addedResult ) {
				$this->resultIngestedVideos['Other'][] = $msg;
			}
		}

		$msg .= "\n";
		$this->setResultSummary( 'ingested', $msg );
	}

	/**
	 * Set summary result for warnings
	 * @param string $msg
	 */
	public function videoWarnings( $msg = '' ) {
		$this->setResultSummary( 'warnings', $msg );
	}

	/**
	 * Set summary result for errors
	 * @param string $msg
	 */
	public function videoErrors( $msg = '' ) {
		$this->setResultSummary( 'errors', $msg );
	}

	/**
	 * Set summary result
	 * @param string $field [found/skipped/ingested/warnings/errors]
	 * @param string $msg
	 * @param integer $num
	 */
	public function setResultSummary( $field, $msg = '', $num = 1 ) {
		if ( !empty( $msg ) ) {
			echo $msg;
		}
		$this->resultSummary[$field] += $num;
	}

	/**
	 * Get summary result
	 * @return array
	 */
	public function getResultSummary() {
		return $this->resultSummary;
	}

	/**
	 * Get messages for ingested videos by category
	 * @return array
	 */
	public function getResultIngestedVideos() {
		return $this->resultIngestedVideos;
	}

	/**
	 * Get provider
	 * @return string
	 */
	public function getProvider() {
		return STATIC::$PROVIDER;
	}

}

class FeedIngesterFactory {
	public static function build( $provider ) {
		$ingester = ucfirst( $provider ) . 'FeedIngester';
		if( class_exists( $ingester ) ) {
			return new $ingester();
		}
		throw new Exception("Invalid provider name: $ingester");
	}
}
