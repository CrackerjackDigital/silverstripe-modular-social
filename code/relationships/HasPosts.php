<?php
use Modular\Relationships\SocialHasManyMany;

class HasPostsExtension extends SocialHasManyMany {

	protected static $other_class_name = 'Post';

	protected static $other_key_field = 'ToPostID';

	protected static $action_name = 'RelatedPosts';

	public function HasPosts($actionCodes = null) {
		return $this->PostList($actionCodes)->count();
	}

	/**
	 * Returns an array with members:
	 * - ListedClass = 'Posts'
	 * - ListItems = list of posts matching provided actions
	 * -
	 *
	 * @param null $actionCodes
	 * @return array
	 */
	public function PostList($actionCodes = null) {
		return parent::actionList($actionCodes);
	}

	/**
	 * Return form component used to modify this action. If no self::$chooser_field set then return null.
	 *
	 * @return OrganisationChooserField
	 */
	public function PostChooser() {
		return parent::Chooser();
	}

	/**
	 * Return related instances with an optional action type.
	 *
	 * @param null $actionCode
	 * @return DataList
	 */
	public function Posts($actionCodes = null) {
		return parent::related($actionCodes);
	}

	/**
	 * Get first related instances's ID with provided action or return null if none such.
	 *
	 * @param $actionCode
	 * @return int|null
	 */
	public function getPostID($actionCode) {
		return parent::getActionName($actionCode);
	}

	/**
	 * Return first related instance found with ID and optionally actionCode.
	 *
	 * @param $postID
	 * @param null $actionCode
	 * @return int
	 */
	public function hasPost($postID, $actionCode = null) {
		return parent::hasAction($postID, $actionCode);
	}

	/**
	 * Relate a Post to this object by supplied action.
	 *
	 * Creates a action class object if Instane and SocialAction records
	 * exist for supplied parameters and adds it to the action collection.
	 *
	 * @param int $postID
	 * @param string $actionCode
	 * @return bool
	 */
	public function addPost($postID, $actionCode) {
		return parent::addAction($postID, $actionCode);
	}

	/**
	 * Remove actions from this object to a Post, optionally by a supplied type.
	 *
	 * @param int $postID
	 * @param string|null $actionCode
	 * @return int count of actions deleted
	 */
	public function removePost($postID, $actionCode = null) {
		return parent::removeAction($postID, $actionCode);
	}

	/**
	 * Clear out all actions of a provided type and add new one.
	 *
	 * @param $postID
	 * @param $actionCode
	 */
	public function setPosts($postID, $actionCode) {
		parent::setActions($postID, $actionCode);
	}

	/**
	 * @param $mode
	 * @param $actionCodes
	 * @return mixed
	 */
	public function provideListItemsForAction($mode, $actionCodes = []) {
		if ($mode === NewsFeedExtension::Action) {
			$related = parent::related(
				Action::merge_code_lists($actionCodes, ['MPM', 'MCP', 'MFM'])
			);

			return $related;
		}
	}
}