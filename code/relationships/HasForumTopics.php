<?php
use Modular\Relationships\SocialHasManyMany;

class HasForumTopicsExtension extends SocialHasManyMany {

	protected static $other_class_name = 'ForumTopic';

	protected static $action_name = 'RelatedForumTopics';

	public function HasForumTopics($actionCodes = null) {
		return $this->ForumTopicList($actionCodes)->count();
	}

	/**
	 * Returns an array with members:
	 * - ListedClass = 'ForumTopics'
	 * - ListItems = list of ForumTopics matching provided actions
	 * -
	 *
	 * @param null $actionCodes
	 * @return array
	 */
	public function ForumTopicList($actionCodes = null) {
		return parent::actionList($actionCodes);
	}

	/**
	 * Return form component used to modify this action. If no self::$chooser_field set then return null.
	 *
	 * @return OrganisationChooserField
	 */
	public function ForumTopicChooser() {
		return parent::Chooser();
	}

	/**
	 * Return related instances with an optional action type.
	 *
	 * @param null $actionCode
	 * @return DataList
	 */
	public function ForumTopics($actionCodes = null) {
		return parent::related($actionCodes);
	}

	/**
	 * Get first related instances's ID with provided action or return null if none such.
	 *
	 * @param $actionCode
	 * @return int|null
	 */
	public function getForumTopicID($actionCode = null) {
		return parent::getActionName($actionCode);
	}

	/**
	 * Return first related instance found with ID and optionally actionCode.
	 *
	 * @param $ForumTopicID
	 * @param null $actionCode
	 * @return int
	 */
	public function hasForumTopic($ForumTopicID, $actionCode = null) {
		return parent::hasAction($ForumTopicID, $actionCode);
	}

	/**
	 * Relate a ForumTopic to this object by supplied action.
	 *
	 * Creates a action class object if Instane and ActionType records
	 * exist for supplied parameters and adds it to the action collection.
	 *
	 * @param int $ForumTopicID
	 * @param string $actionCode
	 * @return bool
	 */
	public function addForumTopic($ForumTopicID, $actionCode) {
		return parent::addAction($ForumTopicID, $actionCode);
	}

	/**
	 * Remove actions from this object to a ForumTopic, optionally by a supplied type.
	 *
	 * @param int $ForumTopicID
	 * @param string|null $actionCode
	 * @return int count of actions deleted
	 */
	public function removeForumTopic($ForumTopicID, $actionCode = null) {
		return parent::removeAction($ForumTopicID, $actionCode);
	}

	/**
	 * Clear out all actions of a provided type and add new one.
	 *
	 * @param $ForumTopicID
	 * @param $actionCode
	 */
	public function setForumTopics($ForumTopicID, $actionCode) {
		parent::setActions($ForumTopicID, $actionCode);
	}

	/**
	 * Returns ListItem
	 * @param $mode
	 * @param $actionCodes
	 * @return DataList|ArrayList
	 */
	public function provideListItemsForAction($mode, $actionCodes = []) {
		if ($mode === NewsFeedExtension::Action) {

			$related = parent::related(
				Action::merge_code_lists($actionCodes, ['MLT', 'MFT', 'MCT'])
			);
			return $related;
		}
	}
}