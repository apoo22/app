<?php
/**
 * Class definition for Wikia\Search\Test\QueryService\Select\Dismax\InterWikiTest
 */
namespace Wikia\Search\Test\QueryService\Select\Dismax;
use Wikia, ReflectionMethod, ReflectionProperty;
/**
 * Tests interwiki search functionality
 */
class InterWikiTest extends Wikia\Search\Test\BaseTest {
	/**
	 * @covers Wikia\Search\QueryService\Select\Dismax\InterWiki::extractMatch
	 */
	public function testExtractMatch() {
		$mockSelect = $this->getMockBuilder( 'Wikia\Search\QueryService\Select\Dismax\InterWiki' )
		                   ->disableOriginalConstructor()
		                   ->setMethods( [ 'extractWikiMatch' ] )
		                   ->getMock();
		$mockMatch = $this->getMockBuilder( 'Wikia\Search\Match\Wiki' )
		                  ->disableOriginalConstructor()
						  ->setMethods( array( 'getId' ) )
		                  ->getMock();

		$mockSelect
		    ->expects( $this->once() )
		    ->method ( 'extractWikiMatch' )
		    ->will   ( $this->returnValue( $mockMatch ) )
		;
		$method = new ReflectionMethod( $mockSelect, 'extractMatch' );
		$method->setAccessible( true );
		$this->assertEquals(
				$mockMatch,
				$method->invoke( $mockSelect )
		);
	}
	
	/**
	 * @covers Wikia\Search\QueryService\Select\Dismax\InterWiki::registerComponents
	 *
	public function testRegisterComponents() {
		$mockQuery = $this->getMockBuilder( '\Solarium_Query_Select' )
		                  ->disableOriginalConstructor()
		                  ->getMock();
		
		$mockSelect = $this->getMockBuilder( 'Wikia\Search\QueryService\Select\Dismax\InterWiki' )
		                   ->disableOriginalConstructor()
		                   ->setMethods( array( 'registerQueryParams', 'registerFilterQueries', 'registerGrouping', 'configureQueryFields' ) )
		                   ->getMock();
		
		$mockSelect
		    ->expects( $this->once() )
		    ->method ( 'configureQueryFields' )
		    ->will   ( $this->returnValue( $mockSelect ) )
		;
		$mockSelect
		    ->expects( $this->once() )
		    ->method ( 'registerQueryParams' )
		    ->with   ( $mockQuery )
		    ->will   ( $this->returnValue( $mockSelect ) )
		;
		$mockSelect
		    ->expects( $this->once() )
		    ->method ( 'registerFilterQueries' )
		    ->with   ( $mockQuery )
		    ->will   ( $this->returnValue( $mockSelect ) )
		;
		$mockSelect
		    ->expects( $this->once() )
		    ->method ( 'registerGrouping' )
		    ->with   ( $mockQuery )
		    ->will   ( $this->returnValue( $mockSelect ) )
		;
		$register = new ReflectionMethod( 'Wikia\Search\QueryService\Select\Dismax\InterWiki', 'registerComponents' );
		$register->setAccessible( true );
		$this->assertEquals(
				$mockSelect,
				$register->invoke( $mockSelect, $mockQuery )
		);
	} */
	
	
	/**
	 * @covers Wikia\Search\QueryService\Select\Dismax\InterWiki::registerFilterQueryForMatch
	 */
	public function testRegisterFilterQueryForMatch() {
		$mockConfig = $this->getMock( 'Wikia\Search\Config', array( 'hasWikiMatch', 'getWikiMatch', 'setFilterQuery' ) );
		$mockSelect = $this->getMockBuilder( 'Wikia\Search\QueryService\Select\Dismax\InterWiki' )
		                   ->disableOriginalConstructor()
		                   ->setMethods( [ 'getConfig' ] )
		                   ->getMock();
		
		$mockMatch = $this->getMockBuilder( 'Wikia\Search\Match\Wiki' )
		                  ->disableOriginalConstructor()
		                  ->setMethods( array( 'getId' ) )
		                  ->getMock();
		
		$mockSelect
		    ->expects( $this->once() )
		    ->method ( 'getConfig' )
		    ->will   ( $this->returnValue( $mockConfig ) )
		;
		$mockConfig
		    ->expects( $this->once() )
		    ->method ( 'hasWikiMatch' )
		    ->will   ( $this->returnValue( true ) )
		;
		$mockConfig
		    ->expects( $this->once() )
		    ->method ( 'getWikiMatch' )
		    ->will   ( $this->returnValue( $mockMatch ) )
		;
		$mockMatch
		    ->expects( $this->once() )
		    ->method ( 'getId' )
		    ->will   ( $this->returnValue( 123 ) )
		;
		$mockConfig
		    ->expects( $this->once() )
		    ->method ( 'setFilterQuery' )
		    ->with   ( '-(id:123)', 'wikiptt' )
		;
		$method = new ReflectionMethod( 'Wikia\Search\QueryService\Select\Dismax\InterWiki', 'registerFilterQueryForMatch' );
		$method->setAccessible( true );
		$this->assertEquals(
				$mockSelect,
				$method->invoke( $mockSelect )
		);
	}
	
	/**
	 * @covers Wikia\Search\QueryService\Select\Dismax\InterWiki::prepareRequest
	 */
	public function testPrepareRequest() {
		$mockConfig = $this->getMockBuilder( 'Wikia\Search\Config' )
                           ->disableOriginalConstructor()
                           ->setMethods( array( 'getPage', 'setStart', 'getLength', 'setLength', 'setIsInterWiki' ) )
                           ->getMock();
		$mockSelect = $this->getMockBuilder( '\Wikia\Search\QueryService\Select\Dismax\InterWiki' )
		                   ->disableOriginalConstructor()
		                   ->setMethods( array( 'getConfig' ) )
		                   ->getMockForAbstractClass();
		
		$mockSelect
		    ->expects( $this->once() )
		    ->method ( 'getConfig' )
		    ->will   ( $this->returnValue( $mockConfig ) )
		;
		$mockConfig
		    ->expects( $this->any() )
		    ->method ( 'getPage' )
		    ->will   ( $this->returnValue( 2 ) )
		;
		$mockConfig
		    ->expects( $this->any() )
		    ->method ( 'getLength' )
		    ->will   ( $this->returnValue( 10 ) )
		;
		$mockConfig
		    ->expects( $this->once() )
		    ->method ( 'setStart' )
		    ->with   ( 10 )
		;
		$mockConfig
		    ->expects( $this->once() )
		    ->method ( 'setStart' )
		    ->with   ( 10 )
		;
		$reflPrep = new ReflectionMethod( 'Wikia\Search\QueryService\Select\Dismax\InterWiki', 'prepareRequest' );
		$reflPrep->setAccessible( true );
		$this->assertEquals(
				$mockSelect,
				$reflPrep->invoke( $mockSelect )
		);
	}
	
	/**
	 * @covers Wikia\Search\QueryService\Select\Dismax\InterWiki::configureQueryFields
	 */
	public function testConfigureQueryFields() {
		
		$mockConfig = $this->getMock( 'Wikia\Search\Config', array( 'setQueryField' ) );
		
		$dc = new Wikia\Search\QueryService\DependencyContainer( array( 'config' => $mockConfig ) );
		$mockSelect = $this->getMockBuilder( 'Wikia\Search\QueryService\Select\Dismax\InterWiki' )
		                   ->setConstructorArgs( array( $dc ) )
		                   ->setMethods( null )
		                   ->getMock();
		
		
		$mockConfig
		    ->expects( $this->at( 0 ) )
		    ->method ( 'setQueryField' )
		    ->with   ( 'wikititle', 200 )
		    ->will   ( $this->returnValue( $mockConfig ) )
		;
		$mockConfig
		    ->expects( $this->at( 1 ) )
		    ->method ( 'setQueryField' )
		    ->with   ( 'wiki_description_txt', 150 )
		;
		$method = new ReflectionMethod( 'Wikia\Search\QueryService\Select\Dismax\InterWiki', 'configureQueryFields' );
		$method->setAccessible( true );
		$this->assertEquals(
				$mockSelect,
				$method->invoke( $mockSelect )
		);
	}
	
	/**
	 * @covers Wikia\Search\QueryService\Select\Dismax\InterWiki::getFilterQueryString
	 */
	public function testGetFilterQueryString() {
		$mockConfig = $this->getMock( 'Wikia\Search\Config', array( 'getHub' ) );
		$dc = new Wikia\Search\QueryService\DependencyContainer( array( 'config' => $mockConfig ) ); 
		$mockSelect = $this->getMockBuilder( '\Wikia\Search\QueryService\Select\Dismax\InterWiki' )
		                   ->setConstructorArgs( array( $dc ) )
		                   ->setMethods( array( null ) )
		                   ->getMockForAbstractClass();
		$mockConfig
		    ->expects( $this->once() )
		    ->method ( 'getHub' )
		    ->will   ( $this->returnValue( 'Entertainment' ) )
		;
		$reflspell = new ReflectionMethod( 'Wikia\Search\QueryService\Select\Dismax\InterWiki', 'getFilterQueryString' );
		$reflspell->setAccessible( true );
		$this->assertEquals(
				'-articles_i:[0 TO 50] AND (hub_s:Entertainment)',
				$reflspell->invoke( $mockSelect )
		);
	}
	
	/**
	 * @covers Wikia\Search\QueryService\Select\Dismax\InterWiki::getQueryClausesString
	 */
	public function testGetQueryClausesString() {
		$mockConfig = $this->getMock( 'Wikia\Search\Config', array( 'getHub', 'getLanguageCode' ) );
		$mockService = $this->getMockBuilder( 'Wikia\Search\MediaWikiService' )
		                      ->disableOriginalConstructor()
		                      ->setMethods( array( 'getGlobal', 'getWikiId' ) )
		                      ->getMock();
		$dc = new Wikia\Search\QueryService\DependencyContainer( array( 'config' => $mockConfig, 'service' => $mockService ) );
		$mockSelect = $this->getMockBuilder( 'Wikia\Search\QueryService\Select\Dismax\InterWiki' )
		                   ->setConstructorArgs( array( $dc ) )
		                   ->setMethods( null )
		                   ->getMock();
		
		$mockConfig
		    ->expects( $this->once() )
		    ->method ( 'getHub' )
		    ->will   ( $this->returnValue( 'Entertainment' ) )
		;
		$mockConfig
		    ->expects( $this->once() )
		    ->method ( 'getLanguageCode' )
		    ->will   ( $this->returnValue( 'en' ) )
		;
		$mockService
		    ->expects( $this->once() )
		    ->method ( 'getGlobal' )
		    ->with   ( 'CrossWikiaSearchExcludedWikis' )
		    ->will   ( $this->returnValue( array( 123, 321 ) ) )
		;
		$mockService
			->expects( $this->once() )
			->method ( 'getWikiId' )
			->will   ( $this->returnValue( 456 ) )
		;
		$method = new ReflectionMethod( 'Wikia\Search\QueryService\Select\Dismax\InterWiki', 'getQueryClausesString' );
		$method->setAccessible( true );
		$this->assertEquals(
				'lang_s:en AND (hub:Entertainment)',
				$method->invoke( $mockSelect )
		);
	}
	
	/**
	 * @covers Wikia\Search\QueryService\Select\Dismax\InterWiki::getQueryFieldsString 
	 */
	public function testGetQueryFieldsString() {
		$mockConfig = $this->getMock( 'Wikia\Search\Config', array( 'getQueryFieldsToBoosts' ) );
		$dc = new Wikia\Search\QueryService\DependencyContainer( array( 'config' => $mockConfig ) );
		$mockSelect = $this->getMockBuilder( 'Wikia\Search\QueryService\Select\Dismax\InterWiki' )
		                   ->setConstructorArgs( array( $dc ) )
		                   ->setMethods( array() )
		                   ->getMock();
		
		$mockConfig
		    ->expects( $this->once() )
		    ->method ( 'getQueryFieldsToBoosts' )
		    ->will   ( $this->returnValue( array( 'foo' => 5, 'bar' => 10 ) ) )
		;
		$get = new ReflectionMethod( 'Wikia\Search\QueryService\Select\Dismax\InterWiki', 'getQueryFieldsString' );
		$get->setAccessible( true );
		$this->assertEquals(
				'foo^5 bar^10',
				$get->invoke( $mockSelect )
		);
	}
}