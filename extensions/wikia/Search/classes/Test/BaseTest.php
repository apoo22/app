<?php 
namespace Wikia\Search\Test;
use \WikiaBaseTest as WikiaBaseTest;
/**
 * Base test class for search extension.
 * All shared methods should go here.
 * @author Robert Elwell <robert@wikia-inc.com>
 */
abstract class BaseTest extends WikiaBaseTest {
	/**
	 * (non-PHPdoc)
	 * @see WikiaBaseTest::setUp()
	 */
	protected function setUp() {
		global $IP;
	    $this->setupFile = "{$IP}/extensions/wikia/Search/WikiaSearch.setup.php";

		parent::setUp();
	}
}
