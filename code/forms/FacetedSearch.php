<?php
namespace Modular\Forms\Social;

use FieldList;
use Form;
use FormAction;
use Modular\Edges\OrganisationProductAndService;
use Modular\Forms\SocialForm;
use Modular\Models\Social\Organisation;
use Modular\Models\SocialOrganisation;
use Modular\Types\Social\OrganisationProductAndServiceType;
use Modular\Types\Social\OrganisationSubType;
use Modular\Types\SocialOrganisationProductAndServiceType;
use Modular\Types\SocialOrganisationSubType;
use Modular\UI\Components\Social\OrganisationSubTypeChooser;
use RequiredFields;
use Select2Field;
use SS_HTTPRequest;
use StopWord;
use TextField;

/**
 *
 * Faceted Search Form
 *
 **/

class FacetedSearchForm extends SocialForm {

	public function __construct($controller, $name) {
		$fields = FieldList::create(

			TextField::create('Keywords', 'Keywords')->setAttribute('placeholder', 'Enter Keywords'),
			OrganisationSubTypeChooser::create('', 'Please Select'),

			Select2Field::create("Location", "Location")
				->setSource(Organisation::CitiesArray())
				->setEmptyString("Please Select")
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
	 * @param array           $data
	 * @param \Form           $form
	 * @param \SS_HTTPRequest $request
	 * @return \HTMLText
	 */

	public function doSearch(array $data, Form $form, SS_HTTPRequest $request) {
		//getVars
		$Keywords = $request->getVar('Keywords');
		$OrganisationType = $request->getVar('SocialOrganisationType');
		$Location = $request->getVar('Location');
		$SubCategories = $request->getVar('OrganisationSubTypeID');

		//SubCategory
		if ($SubCategories) {
			$SubCategory = OrganisationSubType::get()->filter(["ID" => $SubCategories]);
			$orgIDs = [];
			foreach ($SubCategory as $category) {
				foreach ($category->Organisations() as $org) {
					$orgIDs[] = $org->ID;
				}
			}

			if (count($orgIDs) != 0) {
				$Results = Organisation::get()->filter(["ID" => $orgIDs])->sort("Title", "ASC");
			}

		}

		//Keywords used for title filter
		if ($Keywords) {
			$splitKeywords = StopWord::getKeywords($Keywords);
			if (count($splitKeywords) > 0) {

				//matching SocialOrganisation title and description
				$orgCriteria = [
					'Title:PartialMatch' => $splitKeywords,
					'Description:PartialMatch' => $splitKeywords,
				];

				//matching product and service types
				$typeIDs = OrganisationProductAndServiceType::get()->filter([
					'Title:PartialMatch' => $splitKeywords])->column('ID');
				$orgIDsMatchingProductTypes = OrganisationProductAndService::get()->filter([
					'ToProductAndServiceTypeID' => $typeIDs])->column('FromOrganisationID');
				$productTypeCriteria = (['ID' => $orgIDsMatchingProductTypes]);

				//matching organisation subtypes
				// $orgSubTypeIDs = OrganisationSubType::get()->filter([
				// 	'Title:PartialMatch' => $splitKeywords])->column('ID');
				// $orgSubTypeCriteria = $orgSubTypeIDs ? $orgSubTypeCriteria = (['OrganisationSubTypes.ID' => $orgSubTypeIDs]) : array();

				if (!isset($Results)) {
					$Results = Organisation::get();
				}
				// $Results = $Results->filterAny(
				// 	array_merge($orgCriteria, $productTypeCriteria)
				// );
				$splitKeywords = implode(" ", $splitKeywords);
				$Results = $Results->filter('SearchFields:fulltext', $splitKeywords);
				// echo $Results->sql();exit;
			}
		}

		//Location filter
		if ($Location) {
			if (!isset($Results)) {
				$Results = Organisation::get();
			}
			$Results = $Results->filter([
				'City' => $Location,
			])->sort("City", "ASC");
		}

		if (isset($Results)) {
			if ($Results->count() > 0) {
				$Results->limit(30);
			}
		}

		if ($this->controller->request->isAjax()) {
			return $this->renderWith(["Search_result"], compact('Results', 'Keywords', 'Location', 'SubCategory'));
		} else {
			return $this->renderWith(["Search_result", "Page"], compact('Results', 'Keywords', 'Location', 'SubCategory'));
		}

	}

	public function forTemplate() {
		return $this->renderWith(array("FacetedSearchForm"));
	}

}