<?php
namespace Modular\Relationships\Social;
use ArrayData;
use DataList;
use Modular\Edges\SocialRelationship;
use Modular\UI\Components\Social\OrganisationChooser;

/**
 * class SocialHasContactsExtension
 */
class HasContacts extends HasManyMany {
	const RelatedClassName = 'Modular\Models\Social\ContactInfo';

	/**
	 * Returns extension related data for use in e.g. an ExpandoWidget
	 *
	 * - Title
	 * - Content
	 * - ListedClass
	 * - ListItems
	 *
	 * @param null|string|array $actionCodes
	 * @return ArrayData
	 */
	public function HasContacts($actionCodes = "MFM") {
		$data = [
			'Title'     => _t('HasContacts.WidgetTitle', 'Contacts', 'Contacts'),
			'Content'   => _t('HasContacts.WidgetContent', 'Here are your contacts:'),
			'Model'     => $this->owner,
			'ListItems' => $this->contactList($actionCodes),
		];
		return new ArrayData($data);
	}

	/**
	 * Returns an array with members:
	 * - ListedClass = 'Contacts'
	 * - ListItems = map of Contacts matching provided actions
	 *
	 * @param null|string|array $actionCodes - optional csv or array of codes to include
	 * @return \SS_List
	 */
	private function contactList($actionCodes = null) {
		return parent::actionList($actionCodes);
	}

	/**
	 * Return form component used to modify this action. If no self::$chooser_field set then return null.
	 *
	 * @return OrganisationChooser
	 */
	public function ContactChooser() {
		return parent::Chooser();
	}

	/**
	 * Return related instances with an optional action type.
	 *
	 * @param null $actionCodes
	 * @return DataList
	 */
	public function Contacts($actionCodes = null) {
		return parent::related($actionCodes);
	}

	/**
	 * Get first related instances's ID with provided action or return null if none such.
	 *
	 * @param $actionCode
	 * @return int|null
	 */
	public function getContactID($actionCode) {
		return parent::related($actionCode)->first();
	}

	/**
	 * Return first related instance found with ID and optionally actionCode.
	 *
	 * @param      $ContactID
	 * @param null $actionCode
	 * @return int
	 */
	public function hasContact($ContactID, $actionCode = null) {
		return parent::hasRelated($ContactID, $actionCode);
	}

	/**
	 * Relate a ContactModel to this object by supplied action.
	 *
	 * Creates a action class object if Instane and ActionType records
	 * exist for supplied parameters and adds it to the action collection.
	 *
	 * @param int    $ContactID
	 * @param string $actionCode
	 * @return SocialRelationship
	 */
	public function addContact($ContactID, $actionCode) {
		return parent::addRelated($ContactID, $actionCode);
	}

	/**
	 * Remove actions from this object to a ContactModel, optionally by a supplied type.
	 *
	 * @param int         $ContactID
	 * @param string|null $actionCode
	 * @return int count of actions deleted
	 */
	public function removeContact($ContactID, $actionCode = null) {
		return parent::removeRelated($ContactID, $actionCode);
	}

	/**
	 * Clear out all actions of a provided type and add new one.
	 *
	 * @param $ContactID
	 * @param $actionCode
	 */
	public function setContacts($ContactID, $actionCode) {
		parent::setRelated($ContactID, $actionCode);
	}
}