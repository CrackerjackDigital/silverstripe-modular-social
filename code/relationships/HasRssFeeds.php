<?php
use Modular\Relationships\SocialHasManyMany;

class HasRssFeedsExtension extends SocialHasManyMany {
	const RelatedClassName = 'Modular\Models\SocialRSSFeed';
	const RelationshipName = '';

	public function HasRssFeeds($actionCodes = null) {
		return $this->RssFeedList($actionCodes)->count();
	}

	/**
	 * Returns an array with members:
	 * - ListedClass = 'RssFeeds'
	 * - ListItems = list of RssFeeds matching provided actions
	 * -
	 *
	 * @param null $actionCodes
	 * @return \SS_List
	 */
	public function RssFeedList($actionCodes = null) {
		return parent::actionList($actionCodes);
	}

	/**
	 * Return form component used to modify this action. If no self::$chooser_field set then return null.
	 *
	 * @return OrganisationChooserField
	 */
	public function RssFeedChooser() {
		return parent::Chooser();
	}

	/**
	 * Return related instances with an optional action type.
	 *
	 * @param null $actionCode
	 * @return DataList
	 */
	public function RssFeeds($actionCodes = null) {
		return parent::related($actionCodes);
	}

	/**
	 * Get first related instances's ID with provided action or return null if none such.
	 *
	 * @param $actionCode
	 * @return int|null
	 */
	public function getRssFeedID($actionCode = null) {
		return parent::related($actionCode)->first();
	}

	/**
	 * Return first related instance found with ID and optionally actionCode.
	 *
	 * @param $RssFeedID
	 * @param null $actionCode
	 * @return int
	 */
	public function hasRssFeed($RssFeedID, $actionCode = null) {
		return parent::hasRelated($RssFeedID, $actionCode);
	}

	/**
	 * Relate a RssFeed to this object by supplied action.
	 *
	 * Creates a action class object if Instane and SocialActionType records
	 * exist for supplied parameters and adds it to the action collection.
	 *
	 * @param int $RssFeedID
	 * @param string $actionCode
	 * @return bool
	 */
	public function addRssFeed($RssFeedID, $actionCode) {
		return parent::addRelated($RssFeedID, $actionCode);
	}

	/**
	 * Remove actions from this object to a RssFeed, optionally by a supplied type.
	 *
	 * @param int $RssFeedID
	 * @param string|null $actionCode
	 * @return int count of actions deleted
	 */
	public function removeRssFeed($RssFeedID, $actionCode = null) {
		return parent::removeRelated($RssFeedID, $actionCode);
	}

	/**
	 * Clear out all actions of a provided type and add new one.
	 *
	 * @param $RssFeedID
	 * @param $actionCode
	 */
	public function setRssFeeds($RssFeedID, $actionCode) {
		parent::setRelated($RssFeedID, $actionCode);
	}

	/**
	 * Add news feed items to list by returning NewsFeeds with an 'MFR' action to model.
	 * @param $mode
	 * @param $actionCodes
	 * @return ArrayList|null
	 */
	public function provideListItemsForAction($mode, $actionCodes = []) {
		if ($mode === NewsFeedExtension::Action) {
			// get ids of feeds the user is following
			$feedIDs = parent::related(
				Action::merge_code_lists($actionCodes, 'MFR')
			)->column();

			return Post::get()->filter('FeedMeFeedID', $feedIDs);
		}
	}
}