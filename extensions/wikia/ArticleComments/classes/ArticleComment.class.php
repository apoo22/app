<?php

/**
 * ArticleComment is article, this class is used for manipulation on
 */
class ArticleComment {

	const MOVE_USER = 'WikiaBot';
	const AVATAR_BIG_SIZE = 50;
	const AVATAR_SMALL_SIZE = 30;

	public
		$mProps,	//blogs only
		$mTitle,
		$mLastRevId,
		$mFirstRevId,
		$mLastRevision,  ### for displaying text
		$mFirstRevision, ### for author & time
		$mUser,          ### comment creator
		$mArticle,
		$mNamespace,
		$mMetadata,
		$mText,
		$mRawtext,
		$mNamespaceTalk;

	public function __construct( $title ) {
		$this->mTitle = $title;
		$this->mNamespace = $title->getNamespace();
		$this->mNamespaceTalk = MWNamespace::getTalk($this->mNamespace);
		$this->mProps = false;
	}

	/**
	 * newFromTitle -- static constructor
	 *
	 * @static
	 * @access public
	 *
	 * @param Title $title -- Title object connected to comment
	 *
	 * @return ArticleComment object
	 */
	static public function newFromTitle( Title $title ) {
		return new ArticleComment( $title );
	}

	/**
	 * newFromTitle -- static constructor
	 *
	 * @static
	 * @access public
	 *
	 * @param Title $title -- Title object connected to comment
	 *
	 * @return ArticleComment object
	 */
	static public function newFromArticle( Article $article ) {
		$title = $article->getTitle();

		$comment = new ArticleComment( $title );
		return $comment;
	}

	/**
	 *
	 * Used to store extra data in comment contend
	 *
	 * @access public
	 *
	 */

	public function setMetadata( $key, $val ) {
		$this->mMetadata[$key] = $val;
	}

	public function removeMetadata( $key ) {
		unset($this->mMetadata[$key]);
	}

	/**
	 *
	 * Used to get extra data in comment contend
	 *
	 * @access public
	 *
	 */

	public function getMetadata( $key, $val = '' ) {
		return empty($this->mMetadata[$key]) ? $val:$this->mMetadata[$key];
	}

	/**
	 * newFromId -- static constructor
	 *
	 * @static
	 * @access public
	 *
	 * @param Integer $id -- identifier from page_id
	 *
	 * @return ArticleComment object
	 */
	static public function newFromId( $id ) {
		$title = Title::newFromID( $id );
		if ( ! $title ) {
			/**
			 * maybe from Master?
			 */
			$title = Title::newFromID( $id, Title::GAID_FOR_UPDATE );

			if (empty($title)) {
				return false;
			}
		}
		//RT#86385 Why do we get an ID of 0 here sometimes when we know our id already?  Just set it!
		if ($title && $title->getArticleID() <= 0) {
			$title->mArticleID = $id;
		}
		return new ArticleComment( $title );
	}

	/**
	 * load -- set variables, load data from database
	 *
	 */
	public function load($master = false) {
		global $wgMemc, $wgParser, $wgOut;
		wfProfileIn( __METHOD__ );

		$result = true;

		if ( $this->mTitle ) {
			// get revision ids
			if ($master) {
				$this->mFirstRevId = $this->getFirstRevID( DB_MASTER );
				$this->mLastRevId = $this->mTitle->getLatestRevID( Title::GAID_FOR_UPDATE );
			} else {
				$this->mFirstRevId = $this->getFirstRevID( DB_SLAVE );
				$this->mLastRevId = $this->mTitle->getLatestRevID();
				// if first rev does not exist on slave then fall back to master anyway
				if ( !$this->mFirstRevId ) {
					$this->mFirstRevId = $this->getFirstRevID( DB_MASTER );
				}
				// if last rev does not exist on slave then fall back to master anyway
				if ( !$this->mLastRevId ) {
					$this->mLastRevId = $this->mTitle->getLatestRevID( Title::GAID_FOR_UPDATE );
				}
				// if last rev STILL does not exist, give up and set it to first rev
				if ( !$this->mLastRevId ) {
					$this->mLastRevId = $this->mFirstRevId;
				}
			}

			if( empty($this->mFirstRevId) || empty($this->mLastRevId) ) {
			// assume article is bogus, threat as if it doesn't exist
				wfProfileOut( __METHOD__ );
				return false;
			}

			$memckey = wfMemcKey( 'articlecomment', 'basedata', $this->mLastRevId );
			$acData = $wgMemc->get($memckey);

			if (!empty($acData) && is_array($acData)) {
				$this->mText = $acData['text'];
				$this->mMetadata = empty($this->mMetadata) ? $acData['metadata']:$this->mMetadata;
				$this->mRawtext = $acData['raw'];
				$this->mFirstRevision = $acData['first'];
				$this->mLastRevision = $acData['last'];
				$this->mUser = $acData['user'];
				wfProfileOut( __METHOD__ );
				return true;
			}
			// get revision objects
			if ( $this->mFirstRevId ) {
				$this->mFirstRevision = Revision::newFromId( $this->mFirstRevId );
				if ( !empty( $this->mFirstRevision ) && is_object( $this->mFirstRevision ) && ( $this->mFirstRevision instanceof Revision ) ) { // fix for FB:15198
					if ($this->mLastRevId == $this->mFirstRevId) {
						// save one db query by just setting them to the same revision object
						$this->mLastRevision = $this->mFirstRevision;
					} else {
						$this->mLastRevision = Revision::newFromId( $this->mLastRevId );
						if ( empty( $this->mLastRevision ) || !is_object( $this->mLastRevision ) || !( $this->mLastRevision instanceof Revision ) ) {
							$return = false;
						}
					}
				} else {
					$result = false;
				}
			} else {
				$result = false;
			}

			// get user that created this comment
			if ( $this->mFirstRevision ) {
				$this->mUser = User::newFromId( $this->mFirstRevision->getUser() );
				$this->mUser->setName( $this->mFirstRevision->getUserText() );
			} else {
				$result = false;
			}

			if(empty($this->mFirstRevision) || empty($this->mLastRevision) ){
				return false;
			}

			$rawtext = $this->mLastRevision->getText();
			$this->parseText($rawtext);
			$wgMemc->set($memckey, array(
				'text' => $this->mText,
				'metadata' => $this->mMetadata,
				'raw' => $this->mRawtext,
				'first' => $this->mFirstRevision,
				'last' => $this->mLastRevision,
				'user' => $this->mUser
			), 3600);
		} else { // null title
			$result = false;
		}

		wfProfileOut( __METHOD__ );
		return $result;
	}

	public function parseText($rawtext) {
		global $wgParser, $wgOut;
		$this->mRawtext = self::removeMetadataTag($rawtext);
		global $wgEnableParserCache;
		$wgEnableParserCache = false;

		$wgParser->ac_metadata = array();

		global $wgUser;

		$this->mText = $wgParser->parse( $rawtext, $this->mTitle, $wgOut->parserOptions())->getText();
		if( isset($wgParser->ac_metadata) ) {
			$this->mMetadata = $wgParser->ac_metadata;
		} else {
			$this->mMetadata = array();
		}

		return $this->mText;
	}

	public function getText() {
		return $this->mText;
	}

	/**
	 * getFirstRevID -- What is id for first revision
	 * @see Title::getLatestRevID
	 *
	 * @return Integer
	 */
	private function getFirstRevID( $db_conn ) {
		wfProfileIn( __METHOD__ );

		$id = false;

		if ( $this->mTitle ) {
			$db = wfGetDB($db_conn);
			$id = $db->selectField(
				'revision',
				'min(rev_id)',
				array( 'rev_page' => $this->mTitle->getArticleID() ),
				__METHOD__
			);
		}

		wfProfileOut( __METHOD__ );

		return $id;
	}
	/**
	 * getTitle -- getter/accessor
	 *
	 */
	public function getTitle() {
		return $this->mTitle;
	}

	public function getData($master = false, $title = null) {
		global $wgLang, $wgContLang, $wgUser, $wgParser, $wgOut, $wgTitle, $wgBlankImgUrl, $wgMemc, $wgArticleCommentsEnableVoting;

		wfProfileIn( __METHOD__ );

		$title = empty($title) ? $wgTitle : $title;
		$title = empty($title) ? $this->mTitle : $title;

		$comment = false;
		if ( $this->load($master) ) {
			$articleDataKey = wfMemcKey( 'articlecomment', 'comm_data_v2', $this->mLastRevId, $wgUser->getId() );
			$data = $wgMemc->get( $articleDataKey );
			if(!empty($data)) {
				wfProfileOut( __METHOD__ );
				$data['timestamp'] = "<a href='" . $this->getTitle()->getFullUrl( array( 'permalink' => $data['articleId'] ) ) . '#comm-' . $data['articleId'] . "' class='permalink'>" . wfTimeFormatAgo($data['rawmwtimestamp']) . "</a>";
				return $data;
			}

			$canDelete = $wgUser->isAllowed( 'delete' );

			$sig = ( $this->mUser->isAnon() )
				? AvatarService::renderLink( $this->mUser->getName() )
				: Xml::element( 'a', array ( 'href' => $this->mUser->getUserPage()->getFullUrl() ), $this->mUser->getName() );
			$articleId = $this->mTitle->getArticleId();

			$isStaff = (int)in_array('staff', $this->mUser->getEffectiveGroups() );

			$parts = self::explode($this->getTitle());

			$buttons = array();
			$replyButton = '';

			$commentingAllowed = true;
			if (defined('NS_BLOG_ARTICLE') && $title->getNamespace() == NS_BLOG_ARTICLE) {
				$props = BlogArticle::getProps($title->getArticleID());
				$commentingAllowed = isset($props['commenting']) ? (bool)$props['commenting'] : true;
			}

			if ( ( count( $parts['partsStripped'] ) == 1 ) && $commentingAllowed && !ArticleCommentInit::isFbConnectionNeeded() ) {
				$replyButton = '<button type="button" class="article-comm-reply wikia-button secondary actionButton">' . wfMsg('article-comments-reply') . '</button>';
			}
			if( defined('NS_QUESTION_TALK') && ( $this->mTitle->getNamespace() == NS_QUESTION_TALK ) ) {
				$replyButton = '';
			}

			if ( $canDelete && !ArticleCommentInit::isFbConnectionNeeded() ) {
				$img = '<img class="remove sprite" alt="" src="'. $wgBlankImgUrl .'" width="16" height="16" />';
				$buttons[] = $img . '<a href="' . $this->mTitle->getLocalUrl('redirect=no&action=delete') . '" class="article-comm-delete">' . wfMsg('article-comments-delete') . '</a>';
			}

			//due to slave lag canEdit() can return false negative - we are hiding it by CSS and force showing by JS
			if ( $wgUser->isLoggedIn() && $commentingAllowed && !ArticleCommentInit::isFbConnectionNeeded() ) {
				$display = $this->canEdit() ? 'test=' : ' style="display:none"';
				$img = '<img class="edit-pencil sprite" alt="" src="' . $wgBlankImgUrl . '" width="16" height="16" />';
				$buttons[] = "<span class='edit-link'$display>" . $img . '<a href="#comment' . $articleId . '" class="article-comm-edit actionButton" id="comment' . $articleId . '">' . wfMsg('article-comments-edit') . '</a></span>';
			}

			if ( !$this->mTitle->isNewPage(Title::GAID_FOR_UPDATE) ) {
				$buttons[] = $wgUser->getSkin()->makeKnownLinkObj( $this->mTitle, wfMsgHtml('article-comments-history'), 'action=history', '', '', 'class="article-comm-history"' );
			}

			$commentId = $this->getTitle()->getArticleId();
			$rawmwtimestamp = $this->mFirstRevision->getTimestamp();
			$rawtimestamp = wfTimeFormatAgo($rawmwtimestamp);
			$timestamp = "<a rel='nofollow' href='" . $this->getTitle()->getFullUrl( array( 'permalink' => $commentId ) ) . '#comm-' . $commentId . "' class='permalink'>" . wfTimeFormatAgo($rawmwtimestamp) . "</a>";

			$comment = array(
				'id' => $commentId,
				'articleId' => $articleId,
				'author' => $this->mUser,
				'username' => $this->mUser->getName(),
				'avatar' => AvatarService::renderAvatar($this->mUser->getName(), self::AVATAR_BIG_SIZE),
				'avatarSmall' => AvatarService::renderAvatar($this->mUser->getName(), self::AVATAR_SMALL_SIZE),
				'userurl' =>  AvatarService::getUrl($this->mUser->getName()),
				'isLoggedIn' => $this->mUser->isLoggedIn(),
				'buttons' => $buttons,
				'replyButton' => $replyButton,
				'sig' => $sig,
				'text' => $this->mText,
				'metadata' => $this->mMetadata,
				'rawtext' =>  $this->mRawtext,
				'timestamp' => $timestamp,
				'rawtimestamp' => $rawtimestamp,
				'rawmwtimestamp' =>	$rawmwtimestamp,
				'title' => $this->mTitle->getText(),
				'isStaff' => $isStaff,
			);

			if( !empty( $wgArticleCommentsEnableVoting ) ) {
				$comment['votes'] = $this->getVotesCount();
			}

			$data = $wgMemc->set( $articleDataKey, $comment, 60*60 );

			if(!($comment['title'] instanceof Title)) {
				$comment['title'] = F::build('Title',array($comment['title'],NS_TALK),'newFromText');
			}
		}

		wfProfileOut( __METHOD__ );

		return $comment;
	}

	public function metadataParserInit( Parser &$parser ) {
		$parser->setHook('ac_metadata', 'ArticleComment::parserTag');
		return true;
	}

	public  static function parserTag( $content, $attributes, Parser $self ) {
		$self->ac_metadata = $attributes;
		return '';
	}

	/**
	 * render -- generate HTML for displaying comment
	 *
	 * @deprecated not used in Oasis
	 * @return String -- generated HTML text
	 */

	/*
	public function render($master = false) {

		wfProfileIn( __METHOD__ );

		$template = new EasyTemplate( dirname( __FILE__ ) . '/../templates/' );
		$template->set_vars(
			array (
				'comment' => $this->getData($master)
			)
		);
		$text = $template->render( 'comment' );

		wfProfileOut( __METHOD__ );

		return $text;
	}
	 */


	/**
	 * delete article with out any confirmation (used by wall)
	 *
	 * @access public
	 */

	public function doDeleteComment( $reason, $suppress = false ){
		global $wgMemc, $wgUser;
		if(empty($this->mArticle)) {
			$this->mArticle = new Article($this->mTitle, 0);
		}
		$error = '';
		$id = $this->mArticle->getId();
		//we need to run all the hook manual :/
		if ( wfRunHooks( 'ArticleDelete', array( &$this->mArticle, &$wgUser, &$reason, &$error ) ) ) {
			if( $this->mArticle->doDeleteArticle( $reason, $suppress ) ) {
				$deleted = $this->mTitle->getPrefixedText();
				wfRunHooks( 'ArticleDeleteComplete', array( &$this->mArticle, &$wgUser, $reason, $id) );
				return true;
			}
		}

		return false;
	}

	/**
	 * get Title object of article page
	 *
	 * @access public
	 */
	public function getArticleTitle() {
		if ( !isset($this->mTitle) ) {
			return null;
		}

		$title = null;
		$parts = self::explode($this->mTitle->getDBkey());
		if ($parts['title'] != '') {
			$title = Title::makeTitle(MWNamespace::getSubject($this->mNamespace), $parts['title']);
		}
		return $title;
	}

	public static function isTitleComment($title) {
		if (!($title instanceof Title)) {
			return false;
		}

		if (defined('NS_BLOG_ARTICLE') && $title->getNamespace() == NS_BLOG_ARTICLE ||
			defined('NS_BLOG_ARTICLE_TALK') && $title->getNamespace() == NS_BLOG_ARTICLE_TALK) {
			return true;
		} else {
			return strpos(end(explode('/', $title->getText())), ARTICLECOMMENT_PREFIX) === 0;
		}
	}

	public static function explode($titleText, $oTitle = null) {
		$count = 0;
		$titleTextStripped = str_replace(ARTICLECOMMENT_PREFIX, '', $titleText, $count);
		$partsOriginal = explode('/', $titleText);
		$partsStripped = explode('/', $titleTextStripped);

		if ($count) {
			$title = implode('/', array_splice($partsOriginal, 0, -$count));
			array_splice($partsStripped, 0, -$count);
		} else {
			//not a comment - fallback
			$title = $titleText;
			$partsOriginal = $partsStripped = array();
		}

		if( !empty($oTitle) && defined('NS_BLOG_ARTICLE_TALK') && $oTitle->getNamespace() == NS_BLOG_ARTICLE_TALK ) {
			$tmpArr = explode('/', $title);
			array_shift($tmpArr);
			$title = implode('/', $tmpArr);
		}

		$result = array(
			'title' => $title,
			'partsOriginal' => $partsOriginal,
			'partsStripped' => $partsStripped
		);

		return $result;
	}

	/**
	 * check if current user can edit comment
	 */
	public function canEdit() {
		global $wgUser;

		$res = false;
		$isAuthor = false;

		if ( $this->mFirstRevision ) {
			$isAuthor = $this->mFirstRevision->getUser( Revision::RAW ) == $wgUser->getId() && !$wgUser->isAnon();
		}

		$canEdit =
				//prevent infinite loop for blogs - userCan hooked up in BlogLockdown
				defined('NS_BLOG_ARTICLE_TALK') && !empty($this->mTitle) && $this->mTitle->getNamespace() == NS_BLOG_ARTICLE_TALK ||
				$this->mTitle->userCan( "edit" );

		$isAllowed = $wgUser->isAllowed('commentedit');

		$res = $isAuthor || ( $isAllowed && $canEdit );

		return $res;
	}

	public function isAuthor($user) {
		if ( $this->mUser ) {
			return $this->mUser->getId() == $user->getId() && !$user->isAnon();
		}
		return false;
	}

	/**
	 * editPage -- show edit form
	 *
	 * @access public
	 *
	 * @return String
	 */
	public function editPage() {
		global $wgUser, $wgTitle, $wgStylePath;
		wfProfileIn( __METHOD__ );

		$text = '';
		$this->load(true);
		if ($this->canEdit() && !ArticleCommentInit::isFbConnectionNeeded()) {
			$vars = array(
				'canEdit'			=> $this->canEdit(),
				'comment'			=> ArticleCommentsAjax::getConvertedContent($this->mLastRevision->getText()),
				'isReadOnly'		=> wfReadOnly(),
				'stylePath'			=> $wgStylePath,
				'articleId'			=> $this->mTitle->getArticleId(),
				'articleFullUrl'	=> $this->mTitle->getFullUrl(),
			);
			$text = F::app()->getView('ArticleComments', 'Edit', $vars)->render();
		}

		wfProfileOut( __METHOD__ );

		return $text;
	}

	/**
	 * doSaveComment -- save comment
	 *
	 * @access public
	 *
	 * @return Array or false on error. - TODO: Document what the array contains.
	 */
	public function doSaveComment( $text, $user, $title = null, $commentId = 0, $force = false ) {
		global $wgMemc, $wgTitle;
		wfProfileIn( __METHOD__ );

		$res = array();
		$this->load(true);
		if ( $force || ($this->canEdit() && !ArticleCommentInit::isFbConnectionNeeded()) ) {

			if ( wfReadOnly() ) {
				wfProfileOut( __METHOD__ );
				return false;
			}

			if ( !$text || !strlen( $text ) ) {
				wfProfileOut( __METHOD__ );
				return false;
			}

			if ( empty($this->mTitle) && !$commentId ) {
				wfProfileOut( __METHOD__ );
				return false;
			}

			$commentTitle = $this->mTitle ? $this->mTitle : Title::newFromId($commentId);

			/**
			 * because we save different title via Ajax request
			 */
			$wgTitle = $commentTitle;

			/**
			 * add article using EditPage class (for hooks)
			 */

			$article  = new Article( $commentTitle, intval($this->mLastRevId) );
			$retval = self::doSaveAsArticle($text, $article, $user, $this->mMetadata );
			if(!empty($title)) {
				$key = $title->getPrefixedDBkey();
			} else {
				$key = $this->mTitle->getPrefixedDBkey();
				$explode = $this->explode($key);
				$key =  $explode['title'];
			}

			$wgMemc->delete( wfMemcKey( 'articlecomment', 'comm', $key, 'v1' ) );

			$res = array( $retval, $article );
		} else {
			$res = false;
		}

		$this->mLastRevId = $this->mTitle->getLatestRevID( Title::GAID_FOR_UPDATE );
		$this->mLastRevision = Revision::newFromId( $this->mLastRevId );

		wfProfileOut( __METHOD__ );

		return $res;
	}

	/**
	 * doSaveAsArticle store comment as article
	 *
	 * @access protected
	 * @return TODO: DOCUMENT
	 **/

	static protected function doSaveAsArticle($text, $article, $user, $metadata = array() ) {
		$result = null;

		$editPage = new EditPage( $article );
		$editPage->edittime = $article->getTimestamp();
		$editPage->textbox1 = self::removeMetadataTag($text);

		if(!empty($metadata)) {
			$editPage->textbox1 =  $text. Xml::element( 'ac_metadata', $metadata, ' ' );
		}

		$bot = $user->isAllowed('bot');
			//this function calls Article::onArticleCreate which clears cache for article and it's talk page - TODO: is this comment still valid? Does it refer to the line above or to something that got deleted?
		$retval = $editPage->internalAttemptSave( $result, $bot );

		if( $retval->value == EditPage::AS_SUCCESS_UPDATE ) {
			$commentsIndex = F::build( 'CommentsIndex', array( $article->getID() ), 'newFromId' );
			if ( $commentsIndex instanceof CommentsIndex ) {
				$commentsIndex->updateLastRevId( $article->getTitle()->getLatestRevID(Title::GAID_FOR_UPDATE) );
			}
		}
		return $retval;
	}

	/**
	 *
	 * remove metadata tag from
	 *
	 * @access protected
	 *
	 */

	static protected function removeMetadataTag($text) {
		return preg_replace('#</?ac_metadata(\s[^>]*)?>#i', '', $text);
	}


	/**
	 * doPost -- static hook/entry for normal request post
	 *
	 * @static
	 * @access public
	 *
	 * @param WebRequest $request -- instance of WebRequest
	 * @param User       $user    -- instance of User who is leaving the comment
	 * @param Title      $title   -- instance of Title
	 *
	 * @return Article -- newly created article
	 */
	static public function doPost( $text, $user, $title, $parentId = false, $metadata = array() ) {
		global $wgMemc, $wgTitle;
		wfProfileIn( __METHOD__ );

		if ( !$text || !strlen( $text ) ) {
			wfProfileOut( __METHOD__ );
			return false;
		}

		if ( wfReadOnly() ) {
			wfProfileOut( __METHOD__ );
			return false;
		}

		/**
		 * title for comment is combination of article title and some 'random' data
		 */
		if ($parentId == false) {
			//1st level comment
			$commentTitle = sprintf('%s/%s%s-%s', $title->getText(), ARTICLECOMMENT_PREFIX, $user->getName(), wfTimestampNow());
		} else {
			$parentArticle = Article::newFromID($parentId);
			if(empty($parentArticle)) {
				$parentTitle = Title::newFromID($parentId, Title::GAID_FOR_UPDATE);
				// it's possible for Title to be empty at this point
				// if article was removed in the meantime
				// (for eg. when replying on Wall from old browser session
				//  to non-existing thread)
				// it's fine NOT to create Article in that case
				if(!empty($parentTitle)) {
					$parentArticle = new Article($parentTitle);
				}

				// if $parentTitle is empty the logging below will be executed
			}
			//FB#2875 (log data for further debugging)
			if (is_null($parentArticle)) {
				$debugTitle = !empty($title) ? $title->getText() : '--EMPTY--'; // BugId:2646
				Wikia::log(__FUNCTION__, __LINE__, "Failed to create Article object, ID=$parentId, title={$debugTitle}, user={$user->getName()}", true);
				wfProfileOut( __METHOD__ );
				return false;
			}
			$parentTitle = $parentArticle->getTitle();
			//nested comment
			$commentTitle = sprintf('%s/%s%s-%s', $parentTitle->getText(), ARTICLECOMMENT_PREFIX, $user->getName(), wfTimestampNow());
		}

		$commentTitle = Title::newFromText($commentTitle, MWNamespace::getTalk($title->getNamespace()));
		/**
		 * because we save different tile via Ajax request TODO: fix it !!
		 */
		$wgTitle = $commentTitle;

		if( !($commentTitle instanceof Title) ) {
			return false;
		}

		/**
		 * add article using EditPage class (for hooks)
		 */

		$article  = new Article( $commentTitle, 0 );
		$retval = self::doSaveAsArticle($text, $article, $user, $metadata);

		// add comment to database
		if ( $retval->value == EditPage::AS_SUCCESS_NEW_ARTICLE ) {
			$revId = $article->getRevIdFetched();
			$data = array(
				'parentPageId' => $title->getArticleID(),
				'commentId' => $article->getID(),
				'parentCommentId' => intval($parentId),
				'firstRevId' => $revId,
				'lastRevId' => $revId,
			);
			$commentsIndex = F::build( 'CommentsIndex', array($data) );
			$commentsIndex->addToDatabase();

			// set last child comment id
			$commentsIndex->updateParentLastCommentId( $data['commentId'] );

			wfRunHooks( 'EditCommentsIndex', array($article->getTitle(), $commentsIndex) );
		}

		$res = ArticleComment::doAfterPost( $retval, $article, $parentId );

		ArticleComment::doPurge($title, $commentTitle);

		wfProfileOut( __METHOD__ );

		return array( $retval, $article, $res );
	}

	static public function doPurge($title, $commentTitle) {
		wfProfileIn( __METHOD__ );

		global $wgMemc, $wgArticleCommentsLoadOnDemand;

		$wgMemc->set( wfMemcKey( 'articlecomment', 'comm', $title->getDBkey(), 'v1' ), null );

		// make sure our comment list is refreshed from the master RT#141861
		$commentList = ArticleCommentList::newFromTitle($title);
		$commentList->getCommentList(true);

		/*
		// Purge squid proxy URLs for ajax loaded content if we are lazy loading
		if ( !empty( $wgArticleCommentsLoadOnDemand ) ) {
			$app = F::app();

			$urls = array();
			$pages = $commentList->getCountPages();
			$basePath = $app->wf->ExpandUrl( $app->wg->Server . $app->wg->ScriptPath . '/wikia.php' );
			$params = array(
				'controller' => 'ArticleCommentsController',
				'method' => 'Content',
				'format' => 'html',
				'articleId' => $title->getArticleId(),
			);

			for ( $page = 1; $page <= $pages; $page++ ) {
				$params[ 'page' ] = $page;
				$urls[] = $app->wf->AppendQuery( $basePath, $params );
			}

			$squidUpdate = new SquidUpdate( $urls );
			$squidUpdate->doUpdate();

		// Otherwise, purge the article
		} else {

			//BugID: 2483 purge the parent article when new comment is posted
			//BugID: 29462, purge the ACTUAL parent, not the root page... $#%^!
			$parentTitle = Title::newFromText( $commentTitle->getBaseText() );

			if ($parentTitle) {
				$parentTitle->invalidateCache();
				$parentTitle->purgeSquid();
			}
		}*/


		$parentTitle = Title::newFromText( $commentTitle->getBaseText() );

		if ($parentTitle) {
			if ( empty( $wgArticleCommentsLoadOnDemand ) ) {
				// need to invalidate parsed article if it includes comments in the body
				$parentTitle->invalidateCache();
			}
			SquidUpdate::VarnishPurgeKey( self::getSurrogateKey( $parentTitle->getArticleID() ) );
		}

		wfProfileOut( __METHOD__ );
	}

	static public function doAfterPost( $status, $article, $parentId = 0 ) {
		global $wgUser, $wgDBname;

		wfRunHooks( 'ArticleCommentAfterPost', array( $status, &$article ) );
		$commentId = $article->getID();
		$error = false;
		$id = 0;

		switch( $status->value ) {
			case EditPage::AS_SUCCESS_UPDATE:
			case EditPage::AS_SUCCESS_NEW_ARTICLE:
				$comment = ArticleComment::newFromArticle( $article );
				$app = F::app();
				$text = $app->getView( 'ArticleComments',
					( $app->checkSkin( 'wikiamobile' ) ) ? 'WikiaMobileComment' : 'Comment',
					array('comment' => $comment->getData(true),
						'commentId' => $commentId,
						'rowClass' => '',
						'level' => ( $parentId ) ? 2 : 1 ) )->render();

				if ( !is_null($comment->mTitle) ) {
					$id = $comment->mTitle->getArticleID();
				}

				if ( !empty($comment->mTitle) ) {
					self::addArticlePageToWatchlist( $comment ) ;
				}

				$message = false;

				//commit before purging
				wfGetDB(DB_MASTER)->commit();
				break;
			default:
				$userId = $wgUser->getId();
				Wikia::log( __METHOD__, 'error', "No article created. Status: {$status->value}; DB: {$wgDBname}; User: {$userId}" );
				$text  = false;
				$error = true;
				$message = wfMsg('article-comments-error');
		}

		$res = array(
			'commentId' => $commentId,
			'error'  	=> $error,
			'id'		=> $id,
			'msg'    	=> $message,
			'status' 	=> $status,
			'text'   	=> $text
		);

		return $res;
	}

	static public function addArticlePageToWatchlist( $comment ) {
		global $wgUser, $wgEnableArticleWatchlist, $wgBlogsEnableStaffAutoFollow;

		if(!wfRunHooks( 'ArticleCommentBeforeWatchlistAdd', array( $comment ) )) {
			return true;
		}

		if ( empty($wgEnableArticleWatchlist) || $wgUser->isAnon() ) {
			return false;
		}

		$oArticlePage = $comment->getArticleTitle();
		if ( is_null($oArticlePage) ) {
			return false;
		}


		if ( $wgUser->getOption( 'watchdefault' ) && !$oArticlePage->userIsWatching() ) {
			# and article page
			$wgUser->addWatch( $oArticlePage );
		}

		if ( !empty($wgBlogsEnableStaffAutoFollow) && defined('NS_BLOG_ARTICLE') && $comment->mTitle->getNamespace() == NS_BLOG_ARTICLE ) {
			$owner = BlogArticle::getOwner($oArticlePage);
			$oUser = User::newFromName($owner);
			if ( $oUser instanceof User ) {
				$groups = $oUser->getEffectiveGroups();
				if ( is_array($groups) && in_array( 'staff', $groups ) ) {
					$wgUser->addWatch( Title::newFromText( $oUser->getName(), NS_BLOG_ARTICLE ) );
				}
			}
		}

		return true;
	}

	/**
	 * Hook
	 *
	 * @param RecentChange $oRC -- instance of RecentChange class
	 *
	 * @static
	 * @access public
	 *
	 * @return true -- because it's a hook
	 */
	static public function watchlistNotify(RecentChange &$oRC) {
		global $wgEnableGroupedArticleCommentsRC;
		wfProfileIn( __METHOD__ );

		wfRunHooks( 'AC_RecentChange_Save', array( &$oRC ) );

		if ( !empty($wgEnableGroupedArticleCommentsRC) && ( $oRC instanceof RecentChange ) ) {
			$title = $oRC->getAttribute('rc_title');
			$namespace = $oRC->getAttribute('rc_namespace');
			$article_id = $oRC->getAttribute('rc_cur_id');
			$title = Title::newFromText($title, $namespace);

			//TODO: review
			if (MWNamespace::isTalk($namespace) &&
				ArticleComment::isTitleComment($title) &&
				!empty($article_id)) {

				$comment = ArticleComment::newFromId( $article_id );
				if ( $comment instanceof ArticleComment ) {
					$oArticlePage = $comment->getArticleTitle();
					$mAttribs = $oRC->mAttribs;
					$mAttribs['rc_title'] = $oArticlePage->getDBkey();
					$mAttribs['rc_namespace'] = $oArticlePage->getNamespace();
					$mAttribs['rc_log_action'] = 'article_comment';

					$oRC->setAttribs($mAttribs);
				}
			}
		}

		wfProfileOut( __METHOD__ );
		return true;
	}

	/**
	 * Hook
	 *
	 * @param Title $title -- instance of EmailNotification class
	 * @param Array $keys -- array of all special variables like $PAGETITLE etc
	 * @param String $message (subject or body)
	 *
	 * @static
	 * @access public
	 *
	 * @return true -- because it's a hook
	 */
	static public function ComposeCommonMail( $title, &$keys, &$message, $editor ) {
		global $wgEnotifUseRealName;

		if (MWNamespace::isTalk($title->getNamespace()) && ArticleComment::isTitleComment($title)) {
			if ( !is_array($keys) ) {
				$keys = array();
			}

			$name = $wgEnotifUseRealName ? $editor->getRealName() : $editor->getName();
			if ( $editor->isIP( $name ) ) {
				$utext = trim(wfMsgForContent('enotif_anon_editor', ''));
				$message = str_replace('$PAGEEDITOR', $utext, $message);
				$keys['$PAGEEDITOR'] = $utext;
			}
		}
		return true;
	}

	/**
	 * create task to move comment
	 *
	 * @access public
	 * @static
	 */
	static private function addMoveTask( $oCommentTitle, &$oNewTitle, $taskParams ) {
		wfProfileIn( __METHOD__ );

		if ( !is_object( $oCommentTitle ) ) {
			wfProfileOut( __METHOD__ );
			return false;
		}

		$parts = self::explode($oCommentTitle->getDBkey());
		$commentTitleText = implode('/', $parts['partsOriginal']);

		$newCommentTitle = Title::newFromText(
			sprintf( '%s/%s', $oNewTitle->getText(), $commentTitleText ),
			MWNamespace::getTalk($oNewTitle->getNamespace()) );

		$taskParams['page'] = $oCommentTitle->getFullText();
		$taskParams['newpage'] = $newCommentTitle->getFullText();
		$thisTask = new MultiMoveTask( $taskParams );
		$submit_id = $thisTask->submitForm();
		Wikia::log( __METHOD__, 'deletecomment', "Added move task ($submit_id) for {$taskParams['page']} page" );

		wfProfileOut( __METHOD__ );
		return true;
	}

	/**
	 * move one comment
	 *
	 * @access public
	 * @static
	 */
	static private function moveComment( $oCommentTitle, &$oNewTitle, $reason = '' ) {
		global $wgUser;

		wfProfileIn( __METHOD__ );

		if ( !is_object( $oCommentTitle ) ) {
			wfProfileOut( __METHOD__ );
			return array('invalid title');
		}

		$currentUser = $wgUser;
		$wgUser = User::newFromName( self::MOVE_USER );

		$parts = self::explode($oCommentTitle->getDBkey());
		$commentTitleText = implode('/', $parts['partsOriginal']);

		$newCommentTitle = Title::newFromText(
			sprintf( '%s/%s', $oNewTitle->getText(), $commentTitleText ),
			MWNamespace::getTalk($oNewTitle->getNamespace()) );

		$error = $oCommentTitle->moveTo( $newCommentTitle, false, $reason, false );

		$wgUser = $currentUser;

		wfProfileOut( __METHOD__ );
		return $error;
	}

	/**
	 * hook
	 *
	 * @access public
	 * @static
	 */
	static public function moveComments( /*MovePageForm*/ &$form , /*Title*/ &$oOldTitle , /*Title*/ &$oNewTitle ) {
		global $wgUser, $wgRC2UDPEnabled, $wgMaxCommentsToMove, $wgEnableMultiDeleteExt, $wgCityId;
		wfProfileIn( __METHOD__ );

		if ( !$wgUser->isAllowed( 'move' ) ) {
			wfProfileOut( __METHOD__ );
			return true;
		}

		if ( $wgUser->isBlocked() ) {
			wfProfileOut( __METHOD__ );
			return true;
		}

		$commentList = ArticleCommentList::newFromTitle( $oOldTitle );
		$comments = $commentList->getCommentPages(true, false);

		if (count($comments)) {
			$mAllowTaskMove = false;
			if ( isset($wgMaxCommentsToMove) && ( $wgMaxCommentsToMove > 0) && ( !empty($wgEnableMultiDeleteExt) ) ) {
				$mAllowTaskMove = true;
			}

			$irc_backup = $wgRC2UDPEnabled;	//backup
			$wgRC2UDPEnabled = false; //turn off
			$finish = $moved = 0;
			$comments = array_values($comments);
			foreach ($comments as $id => $aCommentArr) {
				$oCommentTitle = $aCommentArr['level1']->getTitle();

				# move comment level #1
				$error = self::moveComment( $oCommentTitle, $oNewTitle, $form->reason );
				if ( $error !== true ) {
					Wikia::log( __METHOD__, 'movepage',
						'cannot move blog comments: old comment: ' . $oCommentTitle->getPrefixedText() . ', ' .
						'new comment: ' . $oNewTitle->getPrefixedText() . ', error: ' . @implode(', ', $error)
					);
				} else {
					$moved++;
				}

				if (isset($aCommentArr['level2'])) {
					foreach ($aCommentArr['level2'] as $oComment) {
						$oCommentTitle = $oComment->getTitle();

						# move comment level #2
						$error = self::moveComment( $oCommentTitle, $oNewTitle, $form->reason );
						if ( $error !== true ) {
							Wikia::log( __METHOD__, 'movepage',
								'cannot move blog comments: old comment: ' . $oCommentTitle->getPrefixedText() . ', ' .
								'new comment: ' . $oNewTitle->getPrefixedText() . ', error: ' . @implode(', ', $error)
							);
						} else {
							$moved++;
						}
					}
				}

				if ( $mAllowTaskMove && $wgMaxCommentsToMove < $moved ) {
					$finish = $id;
					break;
				}
			}

			# rest comments move to task
			if ( $finish > 0 && $finish < count($comments) ) {
				$taskParams= array(
					'wikis'		=> '',
					'reason' 	=> $form->reason,
					'lang'		=> '',
					'cat'		=> '',
					'selwikia'	=> $wgCityId,
					'user'		=> self::MOVE_USER
				);

				for ( $i = $finish + 1; $i < count($comments); $i++ ) {
					$aCommentArr = $comments[$i];
					$oCommentTitle = $aCommentArr['level1']->getTitle();
					self::addMoveTask( $oCommentTitle, $oNewTitle, $taskParams );
					if (isset($aCommentArr['level2'])) {
						foreach ($aCommentArr['level2'] as $oComment) {
							$oCommentTitle = $oComment->getTitle();
							self::addMoveTask( $oCommentTitle, $oNewTitle, $taskParams );
						}
					}
				}
			}

			$wgRC2UDPEnabled = $irc_backup; //restore to whatever it was
			$listing = ArticleCommentList::newFromTitle($oNewTitle);
			$listing->purge();
		} else {
			Wikia::log( __METHOD__, 'movepage', 'cannot move article comments, because no comments: ' . $oOldTitle->getPrefixedText());
		}

		wfProfileOut( __METHOD__ );
		return true;
	}

	//Blogs only functions
	/**
	 * setProps -- change props for comment article
	 *
	 */
	public function setProps( $props, $update = false ) {
		wfProfileIn( __METHOD__ );

		if ( $update && class_exists('BlogArticle') ) {
			BlogArticle::setProps( $this->mTitle->getArticleID(), $props );
		}
		$this->mProps = $props;

		wfProfileOut( __METHOD__ );
	}

	/**
	 * getProps -- get props for comment article
	 *
	 */
	public function getProps(){
		if ( (!$this->mProps || !is_array( $this->mProps )) && class_exists('BlogArticle') ) {
			$this->mProps = BlogArticle::getProps( $this->mTitle->getArticleID() );
		}
		return $this->mProps;
	}

	//Voting functions

	public function getVotesCount(){
		$pageId = $this->mTitle->getArticleId();
		$oFauxRequest = new FauxRequest(array( "action" => "query", "list" => "wkvoteart", "wkpage" => $pageId, "wkuservote" => 0, "wktimestamps" => 1 ));
		$oApi = new ApiMain($oFauxRequest);
		$oApi->execute();
		$aResult = $oApi->getResultData();

		if( isset( $aResult['query']['wkvoteart'][$pageId]['votescount'] ) ) {
			return $aResult['query']['wkvoteart'][$pageId]['votescount'];
		} else {
			return 0;
		}
	}

	public function vote() {
		$oFauxRequest = new FauxRequest(array( "action" => "insert", "list" => "wkvoteart", "wkpage" => $this->mTitle->getArticleId(), "wkvote" => 3 ));
		$oApi = new ApiMain($oFauxRequest);

		$oApi->execute();

		$aResult = $oApi->getResultData();

		$success = !empty( $aResult );

		return $success;
	}

	public function userCanVote() {
		$pageId = $this->mTitle->getArticleId();
		$result = true;

		$oFauxRequest = new FauxRequest(array( "action" => "query", "list" => "wkvoteart", "wkpage" => $pageId, "wkuservote" => 1 ));
		$oApi = new ApiMain($oFauxRequest);
		$oApi->execute();
		$aResult = $oApi->GetResultData();

		if( isset( $aResult['query']['wkvoteart'][$pageId]['uservote'] ) ) {
			$result = false;
		} else {
			$result = true;
		}

		return $result;
	}

	public function getTopParent() {
		$key = $this->mTitle->getDBkey();

		return $this->explodeParentTitleText($key);
	}

	/**
	 * @brief Explodes string got from Title::getText() and returns its parent's text if exists
	 *
	 * @param string $titleText this is the text given from Title::getText()
	 *
	 * @return string | null if given $titleText is a parent's one returns null
	 */
	public function explodeParentTitleText($titleText) {
		$parts = explode('/@', $titleText);

		if(count($parts) < 3) return null;

		return $parts[0] . '/@' . $parts[1];
	}

	public function getTopParentObj() {
		$title = $this->getTopParent();

		if( empty($title) ) return null;

		$title = Title::newFromText( $title, $this->mNamespace );

		if( $title instanceof Title ) {
			$obj = ArticleComment::newFromTitle( $title );

			return $obj;
		}

		return null;
	}

	static function getSurrogateKey( $articleId ) {
		global $wgCityId;
		return 'Wiki_' . $wgCityId . '_ArticleComments_' . $articleId;
	}
}
