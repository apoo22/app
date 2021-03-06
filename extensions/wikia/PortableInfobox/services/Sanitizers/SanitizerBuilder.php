<?php

class SanitizerBuilder {

	/**
	 * @desc provide sanitizer for a given node type
	 *
	 * @param $type
	 * @return NodeSanitizer
	 */
	static public function createFromType( $type ) {
		switch ( $type ) {
			case 'data':
				return new NodeDataSanitizer();
			case 'horizontal-group-content':
				return new NodeHorizontalGroupSanitizer();
			case 'title':
				return new NodeTitleSanitizer();
			case 'image':
				return new NodeImageSanitizer();
			case 'hero-mobile':
			case 'hero-mobile-old': // TODO: clean it after premium layout released on mobile wiki and icache expired
			case 'hero-mobile-wikiamobile':
				return new NodeHeroImageSanitizer();
			default:
				return new PassThroughSanitizer();
		}
	}
}
