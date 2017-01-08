<?php
namespace Modular\Extensions\Controller;

use DataObject;
use Modular\Actions\Editable;
use Modular\Exceptions\Social;
use Modular\Extensions\Model\SocialMember;
use Modular\Interfaces\Action;
use Modular\Interfaces\SocialModel;
use Modular\Interfaces\SocialModelProvider;
use ValidationException;
use Modular\Edges\SocialRelationship;
use Modular\Types\SocialActionType;

/**
 * Base extension for actions which can be performed by the logged in Member to establish
 * a action to the extended object, such as follow or like. Adds basic functionality common
 */
abstract class SocialAction extends SocialController
	implements SocialModelProvider, Action {
	
	const ActionCode          = '';
	const Action              = '';
	const ActionTypeClassName = 'SocialActionType';
	const MemberClassName     = 'Member';
	const ActionClassSuffix   = 'SocialActionType';
	
	/** @var SocialActionType fetched in ctor based on ActionCode */
	private $actionType;
	
	public function __construct() {
		parent::__construct();
		$this->actionType = \DataObject::get(static::ActionTypeClassName)
			->filter(SocialActionType::edge_type_filter_field_name(), static::ActionCode)
			->first();
		
		if (!$this->actionType) {
			// fail hard as we can't do anything more without this loaded.
			$this->debug_fail(new Social("Failed to load an '" . static::ActionTypeClassName . "' action type for code '" . static::ActionCode . "'"));
		}
	}
	
	/**
	 * Check if the action can be done on the controlled model instance if an
	 * ID is available, or class if not.
	 *
	 * @param string|array $actionCodes
	 * @param string       $source where call is being made from, e.g. a controller will set this to 'action' on checking allowed_actions
	 * @return bool|int
	 */
	public function canDoIt($actionCodes, $source = '') {
		$action = static::action();
		
		if ($id = $this()->getModelID()) {
			/** @var string|Edge $actionClassName */
			$actionClassName = current(SocialRelationship::implementors(
				$this()->getModelClass(),
				$this()->getModelClass()
			));
			$isCreator = $actionClassName::get()->filter([
	             $actionClassName::from_field_name() => $id,
	             $actionClassName::to_field_name()   => $id,
	             'SocialActionType.Code'                   => 'CRT',
	         ])->count();
			
			if ($isCreator) {
				return true;
			}
			
		}
		$canDoIt = SocialActionType::check_permission(
			$actionCodes,
			$this()->getModelID()
				? $this()->getModelInstance($action)
				: $this()->getModelClass()
		);
		if ($source && !$canDoIt) {
			if ($source == 'action') {
				$this()->httpError(403, "Sorry, you do not have permissions to do that");
			}
		}
		return $canDoIt;
	}
	
	public static function action() {
		return static::Action;
	}
	
	protected function checkPermission($actionCode) {
		return SocialActionType::check_permission(
			$actionCode,
			$this()->getModelInstance(null)
		);
	}
	/**
	 * Provider the model $modelClass for a particular mode. Generally the passed in mode is compared to an internal
	 * mode and if they match then a model will be returned, otherwise null. This method is called as an extend so
	 * multiple extensions can provide models, however only one should 'win' when it's mode matches the passed mode.
	 *
	 * @param $modelClass
	 * @param $id
	 * @param $mode
	 *
	 * @return SocialModel|DataObject|null
	 */
	public function provideModel($modelClass, $id, $action, $createIfNotFound = false) {
		if ($action === static::Action) {
			if ($id) {
				return SocialModel::get($modelClass)->byID($id);
			}
		}
	}
	
	/**
	 * Build the name of the SocialActionType between 'Member' and the owner model, e.g 'MemberOrganisationAction'
	 *
	 * @return string
	 */
	protected function getActionClassName() {
		return $this->actionType->EdgeClassName;
	}
	
	/**
	 * Create a action of the provided type between the logged in member and the controller model.
	 *
	 * @param string $actionCode
	 * @param array  $extraData to add to action record
	 * @return SocialActionType
	 * @throws ValidationException
	 */
	protected function makeRelationship($actionCode, array $extraData = []) {
		/** @var \Modular\Edges\SocialRelationship|string $className */
		$className = $this->getActionClassName();
		
		// TODO set mode on all extensions, e.g. 'follow'
		/** @var SocialModel $action */
		$action = $className::make(
			SocialMember::current_or_guest(),
			$this()->getModelInstance(Editable::Action),
			$actionCode
		);
		if ($action && $extraData) {
			// update the action with any extra data passed
			$action->update($extraData);
			$action->write();
		}
		
		return $action;
	}
	
	/**
	 * Remove all actions of provided type from current logged in member to the extended controllers model.
	 *
	 * @param $actionCode
	 */
	protected function breakRelationship($actionCode) {
		/** @var SocialActionType $className */
		$className = $this->getActionClassName();
		
		// TODO set mode on all extensions, e.g. 'follow'
		
		$className::remove(
			SocialMember::current_or_guest(),
			$this()->getModelInstance(Editable::Action),
			$actionCode
		);
		// TODO: emailer?
	}
	
	
}