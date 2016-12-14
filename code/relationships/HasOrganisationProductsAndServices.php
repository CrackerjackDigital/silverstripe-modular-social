<?php
use Modular\Relationships\SocialHasMany;
use Modular\UI\Components\SocialProductAndServiceChooser;

class HasOrganisationProductsAndServicesExtension extends SocialHasMany {

	protected static $other_class = 'SocialOrganisationProductAndServiceType';

	protected static $other_field = 'ToProductAndServiceTypeID';

	protected static $relationship_name = 'RelatedProductsAndServices';

	protected static $action_class = 'OrganisationProductAndServiceAction';

	protected static $value_seperator = ',';

	// name of field added to form
	protected static $field_name = Modular\UI\Components\SocialProductAndServiceChooser::IDFieldName;

	protected static $remove_field_name = 'ProductsAndServices';

	/**
	 * Return form component used to modify this action.
	 *
	 * @return SocialProductAndServiceChooser
	 */
	public function Chooser() {
		$ProductsAndServices = $this->ProductsAndServices();
		if ($ProductsAndServices instanceof DataList) {
			$ProductsAndServices = $ProductsAndServices->map()->toArray();
		} else if ($ProductsAndServices instanceof ArrayList) {
			$ProductsAndServices = $ProductsAndServices->toArray();
		} else {
			$ProductsAndServices = [];
		}

		return (new SocialProductAndServiceChooser(
			$ProductsAndServices,
			$this->getAllowedActionTypes()->map()->toArray()
		));
	}

	/**
	 * Returns InterestTypes owner is related to.
	 *
	 * @return DataList|ArrayList of SocialInterestType records
	 */
	public function ProductsAndServices() {
		return parent::getRelated();
	}

	/**
	 * Return first of related ProductsAndServices for owner of a particular ProductAndService type.
	 *
	 * @param $productAndServiceTypeID
	 * @return DataList
	 */
	public function hasProductAndService($productAndServiceTypeID) {
		return parent::hasAction($productAndServiceTypeID);
	}

	/**
	 * Adds an ProductAndService with ID to RelatedInterests after checking it exists and is AllowedFor the owner's class.
	 * @param $productAndServiceTypeID
	 * @return Object
	 * @throws SS_HTTPResponse_Exception
	 */
	public function addProductAndService($productAndServiceTypeID) {
		return parent::addAction($productAndServiceTypeID);
	}

	/**
	 * @param $productAndServiceTypeID
	 * @return Object
	 */
	public function removeProductAndServicet($productAndServiceTypeID) {
		return parent::removeAction($productAndServiceTypeID);
	}

	/**
	 * Delete all action records.
	 *
	 * @fluent
	 * @return $this
	 */
	public function clearProductAndService() {
		return parent::clearActions();
	}

	/**
	 * Clear and set provided ProductsAndServices from array of Titles. Clears all existing ProductsAndServices first!
	 *
	 * @fluent
	 * @param array $titles
	 * @return $this
	 */
	public function setProductAndService(array $titles, $idsNotTitles = false) {
		return parent::setAction($titles, $idsNotTitles);
	}

	/**
	 * Add the SocialProductAndServiceChooser.IDFieldName to the list of fields to remove from subsequent processing as POST data.
	 *
	 * @param SS_HTTPRequest $request
	 * @param DataObject $model
	 * @param $mode
	 * @param array $fieldsHandled
	 */
	public function afterModelWrite(SS_HTTPRequest $request, DataObject $model, $mode) {
		// $fieldsHandled[SocialProductAndServiceChooser::IDFieldName] = SocialProductAndServiceChooser::IDFieldName;
		$products = $request->postVar(SocialProductAndServiceChooser::IDFieldName);
		if ($products) {
			$productSplit = explode(self::$value_seperator, $products);

			foreach ($productSplit as $item => $value) {
				//check if product or service is already saved
				$checkProductInList = DataObject::get(self::$other_class)->filter(["Title" => $value])->first();
				if (!$checkProductInList) {
					$newProductService = new self::$other_class;
					$newProductService->Title = $value;
					$newProductService->AllowedFrom = "SocialOrganisation";
					$newProductService->write();
				}
			}

			$this->setProductAndService($productSplit);
		}
	}

}