<?php

/* This module encapsulates the right rail.  BodyModule handles the business logic for turning modules on or off */

class RailController extends WikiaController {

	const LAZY_LOADING_BEAKPOINT = 1440; // TOP_RIGHT_BOXAD
	const FILTER_LAZY_MODULES = true;
	const FILTER_NON_LAZY_MODULES = false;

	public function executeIndex( $params ) {
		$railModules = isset( $params['railModuleList'] ) ? $params['railModuleList'] : [];

		$this->isEditPage = isset( $params['isEditPage'] ) ? $params['isEditPage'] : false;
		$this->railModuleList = $this->filterModules( $railModules, self::FILTER_NON_LAZY_MODULES );
		$this->isGridLayoutEnabled = BodyController::isGridLayoutEnabled();
		$this->isAside = $this->wg->RailInAside;
		$this->loadLazyRail = $railModules > $this->railModuleList;
	}

	/**
	 * Entry point for lazy loading right rail for anon users
	 */
	public function executeLazyForAnons() {
		$this->getLazyRail();

		$this->response->setCacheValidity( WikiaResponse::CACHE_STANDARD );
	}

	/**
	 * Entry point for lazy loading right rail for logged in users
	 */
	public function executeLazy() {
		$this->getLazyRail();
	}

	/**
	 * Get lazy right rail modules
	 */
	protected function getLazyRail() {
		global $wgUseSiteJs, $wgAllowUserJs, $wgTitle, $wgAllInOne;
		$title = Title::newFromText(
			$this->request->getVal( 'articleTitle', null ),
			$this->request->getInt( 'namespace', null )
		);

		if ( $title instanceof Title ) {
			// override original wgTitle from title given in parameters
			// we cannot use wgTitle that is created on by API because it's broken on wikis without '/wiki' in URL
			// https://wikia-inc.atlassian.net/browse/BAC-906
			$oldWgTitle = $wgTitle;
			$wgTitle = $title;
			$assetManager = AssetsManager::getInstance();
			$railModules = $this->filterModules(
				( new BodyController )->getRailModuleList(),
				self::FILTER_LAZY_MODULES
			);
			$this->railLazyContent = '';
			krsort( $railModules );
			foreach ( $railModules as $railModule ) {
				$this->railLazyContent .= $this->app->renderView(
					$railModule[0], /* Controller */
					$railModule[1], /* Method */
					$railModule[2] /* array of params */
				);
			}

			$this->railLazyContent .= Html::element( 'div', [ 'id' => 'WikiaAdInContentPlaceHolder' ] );

			$this->css = $sassFiles = [];
			foreach ( array_keys( $this->app->wg->Out->styles ) as $style ) {
				if ( $wgAllInOne && $assetManager->isSassUrl( $style ) ) {
					$sassFiles[] = $style;
				} else {
					$this->css[] = $style;
				}
			}

			if ( !empty( $sassFiles ) ) {
				$excludeScss = (array) $this->getRequest()->getVal( 'excludeScss', [] );
				$sassFilePath = (array) $assetManager->getSassFilePath( $sassFiles );
				$includeScss = array_diff( $sassFilePath, $excludeScss );

				// SUS-771: Log any duplicate CSS that rail modules try to load but are already loaded by Oasis skin
				$duplicateScss = array_intersect( $sassFilePath, $excludeScss );
				if ( count( $duplicateScss ) ) {
					Wikia\Logger\WikiaLogger::instance()->info(
						'SUS-771',
						[
							'styles' => json_encode( $duplicateScss )
						]
					);
				}

				if ( !empty( $includeScss ) ) {
					$this->css[] = $assetManager->getSassesUrl( $includeScss );
				}
			}

			// Do not load user and site jses as they are already loaded and can break page
			$oldWgUseSiteJs = $wgUseSiteJs;
			$oldWgAllowUserJs = $wgAllowUserJs;
			$wgUseSiteJs = false;
			$wgAllowUserJs = false;

			$this->js = $this->app->wg->Out->getBottomScripts();

			$wgUseSiteJs = $oldWgUseSiteJs;
			$wgAllowUserJs = $oldWgAllowUserJs;
			$wgTitle = $oldWgTitle;
		}
	}

	/**
	 * Method that filters array of right rail modules into array of only lazy module or non lazy modules
	 *
	 * @param $moduleList
	 * @param $lazy
	 *
	 * @return array
	 */
	private function filterModules( $moduleList, $lazy ) {
		$lazyChecker = ( $lazy == self::FILTER_LAZY_MODULES ) ?
			[ $this, 'modulesLazyCheck' ] :
			[ $this, 'modulesNotLazyCheck' ];
		$out = [];
		foreach ( $moduleList as $key => $val ) {
			if ( $lazyChecker( $key ) ) {
				$out[$key] = $val;
			}
		}

		return $out;
	}

	private function modulesNotLazyCheck( $moduleKey ) {
		return $moduleKey >= self::LAZY_LOADING_BEAKPOINT;
	}

	private function modulesLazyCheck( $moduleKey ) {
		return $moduleKey < self::LAZY_LOADING_BEAKPOINT;
	}
}
