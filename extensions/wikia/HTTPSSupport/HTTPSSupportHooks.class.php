<?php

class HTTPSSupportHooks {

	// List of articles that shouldn't redirect to http versions.
	// This array contains wgDBname mapped to array of article keys.
	static $httpsArticles = [
		/* www.wikia.com */
		'wikiaglobal' => [ 'Licensing', 'Privacy_Policy', 'Terms_of_Use'  ],
		/* ja.wikia.com */
		'jacorporate' => [ 'Privacy_Policy', 'Terms_of_Use' ],
		/* community.wikia.com */
		'wikia' => [ 'Community_Central:Licensing' ]
	];

	const VIGNETTE_IMAGES_HTTP_UPGRADABLE = '#(images|img|static|vignette)(\d+)?\.wikia\.(nocookie\.)?(net|com)#i';

	public static function onMercuryWikiVariables( array &$wikiVariables ): bool {
		global $wgDisableHTTPSDowngrade;
		$basePath = $wikiVariables['basePath'];
		$user = RequestContext::getMain()->getUser();
		if ( self::httpsAllowed( $user, $basePath ) ) {
			$wikiVariables['basePath'] = wfHttpToHttps( $basePath );
		}
		$wikiVariables['disableHTTPSDowngrade'] = !empty( $wgDisableHTTPSDowngrade );
		return true;
	}

	/**
	 * Handle redirecting users to HTTPS when on wikis that support it,
	 * as well as redirect those on wikis that don't support HTTPS back
	 * to HTTP if necessary.
	 *
	 * @param  Title      $title
	 * @param             $article
	 * @param  OutputPage $output
	 * @param  User       $user
	 * @param  WebRequest $request
	 * @param  MediaWiki  $mediawiki
	 * @return bool
	 */
	public static function onAfterInitialize( Title $title, $article, OutputPage $output,
		User $user, WebRequest $request, MediaWiki $mediawiki
	): bool {
		global $wgDisableHTTPSDowngrade;
		if ( !empty( $_SERVER['HTTP_FASTLY_FF'] ) &&  // don't redirect internal clients
			// Don't redirect externaltest and showcase due to weird redirect behaviour (PLATFORM-3585)
			!in_array( $request->getHeader( 'X-Staging' ), [ 'externaltest', 'showcase' ] )
		) {
			$requestURL = $request->getFullRequestURL();
			if ( WebRequest::detectProtocol() === 'http' &&
				self::httpsAllowed( $user, $requestURL )
			) {
				$output->redirectProtocol( PROTO_HTTPS, '301' );
				if ( $user->isAnon() ) {
					$output->enableClientCache( false );
				}
			} elseif ( WebRequest::detectProtocol() === 'https' &&
				!self::httpsAllowed( $user, $requestURL ) &&
				empty( $wgDisableHTTPSDowngrade ) &&
				!$request->getHeader( 'X-Wikia-WikiaAppsID' ) &&
				!self::httpsEnabledTitle( $title )
			) {
				$output->redirectProtocol( PROTO_HTTP );
				$output->enableClientCache( false );
			}
		}
		return true;
	}

	/**
	 * Handle downgrading anonymous requests for our sitemaps and robots.txt.
	 *
	 * @param  WebRequest $request
	 * @param  User       $user
	 * @return boolean
	 */
	public static function onSitemapRobotsPageBeforeOutput( WebRequest $request, User $user ): bool {
		$url = wfExpandUrl( $request->getFullRequestURL(), PROTO_HTTP );
		if ( WebRequest::detectProtocol() === 'http' &&
			self::httpsAllowed( $user, $request->getFullRequestURL() )
		) {
			self::redirectWithPrivateCache( wfHttpToHttps( $url ), $request );
			return false;
		} elseif ( WebRequest::detectProtocol() === 'https' &&
			!self::httpsAllowed( $user, $request->getFullRequestURL() )
		) {
			self::redirectWithPrivateCache( wfHttpsToHttp( $url ), $request );
			return false;
		}
		return true;
	}

	public static function parserUpgradeVignetteUrls( string &$url ) {
		if ( preg_match( self::VIGNETTE_IMAGES_HTTP_UPGRADABLE, $url ) && strpos( $url, 'http://' ) === 0 ) {
			$url = wfHttpToHttps( $url );
		}
	}

	/**
	 * Make sure any "external" links to our own wikis that support HTTPS
	 * are protocol-relative on output.
	 *
	 * @param  string  &$url
	 * @param  string  &$text
	 * @param  bool    &$link
	 * @param  array   &$attribs
	 * @return boolean
	 */
	public static function onLinkerMakeExternalLink( string &$url, string &$text, bool &$link, array &$attribs ): bool {
		if ( wfHttpsAllowedForURL( $url ) ) {
			$url = wfProtocolUrlToRelative( $url );
		}
		return true;
	}

	private static function httpsAllowed( User $user, string $url ): bool {
		global $wgEnableHTTPSForAnons;
		return wfHttpsAllowedForURL( $url ) &&
			( !empty( $wgEnableHTTPSForAnons ) || $user->isLoggedIn() );
	}

	private static function httpsEnabledTitle( Title $title ): bool {
		global $wgDBname;
		return array_key_exists( $wgDBname, self::$httpsArticles ) &&
			in_array( $title->getPrefixedDBKey(), self::$httpsArticles[ $wgDBname ] );
	}

	private static function redirectWithPrivateCache( string $url, WebRequest $request ) {
		$response = $request->response();
		$response->header( "Location: $url", true, 302 );
		$response->header( 'X-Redirected-By: HTTPS-Support' );
		$response->header( 'Cache-Control: private, must-revalidate, max-age=0' );
	}
}
