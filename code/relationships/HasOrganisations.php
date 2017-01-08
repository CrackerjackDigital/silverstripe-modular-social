<?php
use Modular\Relationships\SocialHasManyMany;

/**
 *
 * Support functions for Classes which has_many MemberOrganisationAction.
 */
class HasOrganisationsExtension extends SocialHasManyMany {
	const RelatedClassName = 'Modular\Models\SocialOrganisation';

	/**
	 * Return related Organisations with an optional action type.
	 *
	 * @param null $actionCode
	 * @return DataList
	 */
	public function Organisations($actionCode = null) {
		return parent::related($actionCode);
	}

	/**
	 * Get first organisation's ID with provided action or return null if none such.
	 *
	 * @param $actionCode
	 * @return int|null
	 */
	public function getOrganisationID($actionCode = 'MRO') {
		return parent::firstRelatedID($actionCode);
	}

	/**
	 * Get first organisation's ID with provided action or return null if none such.
	 *
	 * @param $actionCode
	 * @return int|null
	 */
	public function getOrganisation($actionCode = 'MRO') {
		return parent::firstRelated($actionCode);
	}

	/**
	 * Return first organisation found with ID and optionally actionCode.
	 *
	 * @param $organisationID
	 * @param null $actionCode
	 * @return int
	 */
	public function hasOrganisation($organisationID, $actionCode = null) {
		return parent::hasRelated($organisationID, $actionCode);
	}

	/**
	 * Relate an organisation to this object by supplied action.
	 *
	 * Creates a MemberOrganisationAction object if SocialOrganisation and SocialActionType records
	 * exits for supplied parameters and adds it to RelatedOrganisations collection.
	 *
	 * @param int $organisationID
	 * @param string $actionCode
	 * @return bool
	 */
	public function addOrganisation($organisationID, $actionCode) {
		return parent::addRelated($organisationID, $actionCode);
	}

	/**
	 * Remove actions from this object to an organisation, optionally by a supplied type.
	 *
	 * @param int $organisationID
	 * @param string|null $actionCode
	 * @return int count of actions deleted
	 */
	public function removeOrganisation($organisationID, $actionCode = null) {
		return parent::removeRelated($organisationID, $actionCode);
	}

	/**
	 * Clear out all actions of a provided type and add new one.
	 *
	 * @param $organisationID
	 * @param $actionCode
	 */
	public function setOrganisation($organisationID, $actionCode) {
		parent::setRelated($organisationID, $actionCode);
	}

	/**
	 * Depending on mode/action returns a query for dataobjects usefull to use by that mode.
	 *
	 * e.g. NewsFeed will return a list of organisations which are related to the model by passed action types.
	 *
	 * @param $mode
	 * @param $actionCodes
	 * @return array|null
	 */
	public function provideListItemsForAction($mode, $actionCodes = []) {
		if ($mode === NewsFeedExtension::Action) {
			$related = parent::related($actionCodes);
			$exclusionList = [];
			foreach ($related as $org) {
				$exclusionList[] = $org->ID;
			}
			$recent = Organisation::get()->sort('Created', 'Desc')->limit(5)->exclude(["ID" => $exclusionList]);

			foreach ($recent as $org) {
				$exclusionList[] = $org->ID;
			}

			return [
				$related,
				$recent,
			];
		}
	}

	/**
	 * Provide a modal dialog for managing the action between extended model and SocialOrganisation.
	 *
	 * E.g. for Registering a new company
	 *
	 *
	 * @param $mode
	 * @return mixed
	 */
	public function provideUIModal($mode) {
		return $this()->renderWith(["SocialModel_$mode", "ModalDialog"]);
	}

	/**
	 *
	 * Updates OrganisationForm fields if they exist as follows:
	 *
	 * -    OrganisationTypeChooser: set it's values from the model
	 * -    Logo: configure max number of files = 1 and other settings
	 *
	 * @param DataObject $model
	 * @param FieldList $fields
	 * @param $mode
	 * @param array $requiredFields
	 */
	public function updateFieldsForMode(DataObject $model, FieldList $fields, $mode, &$requiredFields = []) {
		if ($chooserField = $fields->fieldByName('OrganisationTypesChooser')) {
			$chooserField->setTypeID($model->OrganisationTypeID)->setSubTypeID($model->OrganisationSubTypeID);
		}
		/** @var UploadField $uploadField */
		if ($uploadField = $fields->fieldByName('Logo')) {
			$uploadField->setAllowedMaxFileNumber(1);
			$uploadField->setCanAttachExisting(false);
			$uploadField->setCanPreviewFolder(true);
			$uploadField->setFileEditActions(null);
		}
	}
}