<?php
use Modular\UI\Components\SocialInterestChooser;
use Modular\Relationships\SocialHasMany;

class HasInterestsExtension extends SocialHasMany {

	protected static $other_class = 'SocialInterestType';

	protected static $other_field = 'ToInterestTypeID';

	protected static $relationship_name = 'RelatedInterests';

	protected static $action_class = 'GroupInterestAction';

	protected static $value_seperator = ',';

	// name of field added to form
	protected static $field_name = Modular\UI\Components\SocialInterestChooser::IDFieldName;

	protected static $remove_field_name = 'Interests';

	/**
	 * Return form component used to modify this action.
	 *
	 * @return SocialInterestChooser
	 */
	public function Chooser() {
		$interests = $this->Interests();
		if ($interests instanceof DataList) {
			$interests = $interests->map()->toArray();
		} else if ($interests instanceof ArrayList) {
			$interests = $interests->toArray();
		} else {
			$interests = [];
		}

		return (new SocialInterestChooser(
			$interests,
			$this->getAllowedActionTypes()->map()->toArray()
		));
	}

	/**
	 * Returns InterestTypes owner is related to.
	 *
	 * @return DataList|ArrayList of SocialInterestType records
	 */
	public function Interests() {
		return parent::getRelated();
	}

	/**
	 * Return first of related interests for owner of a particular interest type.
	 *
	 * @param $interestTypeID
	 * @return DataList
	 */
	public function hasInterest($interestTypeID) {
		return parent::hasAction($interestTypeID);
	}

	/**
	 * Adds an interest with ID to RelatedInterests after checking it exists and is AllowedFor the owner's class.
	 * @param $interestTypeID
	 * @return Object
	 * @throws SS_HTTPResponse_Exception
	 */
	public function addInterest($interestTypeID) {
		return parent::addAction($interestTypeID);
	}

	/**
	 * @param $interestTypeID
	 * @return Object
	 */
	public function removeInterest($interestTypeID) {
		return parent::removeAction($interestTypeID);
	}

	/**
	 * Delete all action records.
	 *
	 * @fluent
	 * @return $this
	 */
	public function clearInterests() {
		return parent::clearRelated();
	}

	/**
	 * Clear and set provided interests from array of Titles. Clears all existing Interests first!
	 *
	 * @fluent
	 * @param array $titles
	 * @return $this
	 */
	public function setInterests(array $titles, $idsNotTitles = false) {
		return parent::setActions($titles, $idsNotTitles);
	}

	/**
	 * Add the SocialInterestChooser.IDFieldName to the list of fields to remove from subsequent processing as POST data.
	 *
	 * @param SS_HTTPRequest $request
	 * @param DataObject $model
	 * @param $mode
	 * @param array $fieldsHandled
	 */
	public function beforeModelWrite(SS_HTTPRequest $request, DataObject $model, $mode, &$fieldsHandled = []) {
		$fieldsHandled[ SocialInterestChooser::IDFieldName] = SocialInterestChooser::IDFieldName;
	}
/*
	public function afterModelWrite(SS_HTTPRequest $request, DataObject $model, $mode) {
		$interestsVar = $request->postVar(SocialInterestChooser::IDFieldName);
		if ($interestsVar) {
			$interestSplit = explode(self::$value_seperator, $interestsVar);

			foreach ($interestSplit as $item => $value) {
				//check if interest is already saved
				$checkInList = DataObject::get(self::$other_class)->filter(["Title" => $value])->first();
				if (!$checkInList) {
					$newInterest = new self::$other_class;
					$newInterest->Title = $value;
					$newInterest->AllowedFrom = "GroupModel";
					$newInterest->write();
				}
			}

			$this->clearInterests();
			$this->setInterests($interestSplit);
		}
	}
*/
}