<?php
use Modular\Relationships\SocialHasManyMany;

class HasForumsExtension extends SocialHasManyMany {
	const RelatedClassName = 'Modular\Models\SocialForum';

	public function HasForums($actionCodes = null) {
		return $this->ForumList($actionCodes)->count();
	}

	/**
	 * Returns an array with members:
	 * - ListedClass = 'Forums'
	 * - ListItems = list of Forums matching provided actions
	 * -
	 *
	 * @param null $actionCodes
	 * @return \SS_List
	 */
	public function ForumList($actionCodes = null) {
		return parent::actionList($actionCodes);
	}

	/**
	 * Return form component used to modify this action. If no self::$chooser_field set then return null.
	 *
	 * @return OrganisationChooserField
	 */
	public function ForumChooser() {
		return parent::Chooser();
	}

	/**
	 * Return related instances with an optional action type.
	 *
	 * @param null $actionCode
	 * @return DataList
	 */
	public function Forums($actionCodes = null) {
		return parent::related($actionCodes);
	}

	/**
	 * Get first related instances's ID with provided action or return null if none such.
	 *
	 * @param $actionCode
	 * @return int|null
	 */
	public function getForumID($actionCode = null) {
		return parent::firstRelatedID($actionCode);
	}

	/**
	 * Return first related instance found with ID and optionally actionCode.
	 *
	 * @param $ForumID
	 * @param null $actionCode
	 * @return int
	 */
	public function hasForum($ForumID, $actionCode = null) {
		return parent::hasRelated($ForumID, $actionCode);
	}

	/**
	 * Relate a ForumModel to this object by supplied action.
	 *
	 * Creates a action class object if Instane and ActionType records
	 * exist for supplied parameters and adds it to the action collection.
	 *
	 * @param int $ForumID
	 * @param string $actionCode
	 * @return bool
	 */
	public function addForum($ForumID, $actionCode) {
		return parent::addRelated($ForumID, $actionCode);
	}

	/**
	 * Remove actions from this object to a ForumModel, optionally by a supplied type.
	 *
	 * @param int $ForumID
	 * @param string|null $actionCode
	 * @return int count of actions deleted
	 */
	public function removeForum($ForumID, $actionCode = null) {
		return parent::removeRelated($ForumID, $actionCode);
	}

	/**
	 * Clear out all actions of a provided type and add new one.
	 *
	 * @param $ForumID
	 * @param $actionCode
	 */
	public function setForums($ForumID, $actionCode) {
		parent::setRelated($ForumID, $actionCode);
	}
}