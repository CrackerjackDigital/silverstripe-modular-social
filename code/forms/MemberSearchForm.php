<?php
namespace Modular\Forms;
use FieldList;
use Form;
use FormAction;
use Member;
use Organisation;
use RequiredFields;
use Select2Field;
use SS_HTTPRequest;
use TextField;

/**
 *
 * Member Search Form
 *
 **/

class MemberSearchForm extends SocialForm {

	public function __construct($controller, $name) {

		$fields = FieldList::create(

			TextField::create('MemberName', 'Member Name')->setAttribute('placeholder', 'Enter Member Name'),
			Select2Field::create("OrganisationID", "Organisation")
				->setSource(Organisation::get()->map("ID", "Title"))
				->setEmptyString("Please Select Organisation")
		);

		$actions = FieldList::create(
			FormAction::create('doSearch')->setTitle("Search")->addExtraClass("btn btn-green")
		);

		$validator = new RequiredFields();
		parent::__construct($controller, $name, $fields, $actions, $validator);
	}

	/**
	 *
	 * Run keyword and parameters searching
	 *
	 **/

	public function doSearch(array $data, Form $form, SS_HTTPRequest $request) {
		//getVars
		$MemberName = $request->getVar('MemberName');
		$Organisation_id = $request->getVar('OrganisationID');
		$Results = null;

		//Organisation name filter
		if ($Organisation_id) {
			$org = Organisation::get()->byID($Organisation_id);
			//search on members, if name is empty, we'll just return all the members
			if (!empty($MemberName)) {
				$Results = $org->OrganisationMembers()->filterAny([
					'FirstName:PartialMatch' => $MemberName,
					'Surname:PartialMatch' => $MemberName,
				])->limit(30);
			}
			else
			{
				$Results = $org->OrganisationMembers()->limit(30);
			}

			$Organisation = $org->Title;
		}
		else if (!empty($MemberName))
		{
			$MemberName = explode(" ", $MemberName);
			$Results = Member::get()->filterAny([
				'FirstName:PartialMatch' => $MemberName,
				'Surname:PartialMatch' => $MemberName,
			])->limit(30);
		}
		//user didn't enter an organisation or member name.
		else
		{
			return false;
		}

		if ($request->isAjax()) {
			return $this->renderWith(["Search_result"], compact('Results', 'MemberName', 'Organisation'));
		}
		return $this->renderWith(["Search_result", 'Page'], compact('Results', 'MemberName', 'Organisation'));
	}

	public function forTemplate() {
		return $this->renderWith(array("FacetedSearchForm"));
	}

}