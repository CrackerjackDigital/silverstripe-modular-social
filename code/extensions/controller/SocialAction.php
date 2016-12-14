<?php
namespace Modular\Extensions\Controller;

use DataObject;
use Modular\Edges\SocialRelationship;
use Modular\Exceptions\Social as Exception;
use Modular\Fields\SystemData;
use Modular\Interfaces\SocialModel as SocialModelInterface;
use Modular\Interfaces\SocialModelProvider;
use Modular\Models\SocialModel;
use Modular\Types\SocialActionType as SocialActionType;

/**
 * Base extension for actions which can be performed by the logged in Member to establish
 * a action to the extended object, such as follow or like. Adds basic functionality common
 */
abstract class SocialAction extends SocialController
	implements SocialModelProvider {
	const ActionCode        = '';
	const MemberClassName   = 'Member';
	const ActionClassSuffix = '';

	/**
	 * Check if the action can be done on the controlled model instance if an
	 * ID is available, or class if not.
	 *
	 * @param string|array $actionCodes
	 * @param string       $source where call is being made from, e.g. a controller will set this to 'action' on checking allowed_actions
	 * @return bool|int
	 */
	public function canDoIt($actionCodes = null, $source = '') {
		$action = static::ActionCode;

		if ($id = $this()->getModelID()) {
			/** @var string|SocialRelationship $actionClassName */
			$actionClassName = current(SocialRelationship::implementors(
				$this()->getModelClass(),
				$this()->getModelClass()
			));
			// if someone created something then they can always
			// perform all actions on it
			$isCreator = $actionClassName::get()->filter([
				$actionClassName::from_field_name() => $id,
				$actionClassName::to_field_name()   => $id,
				'Type.Code'                         => 'CRT',
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

	/**
	 * Cache the primary action for this controller, e.g. 'CFM' for confirm. This will also fail on build if the action doesn't exist.
	 *
	 * @param null $field
	 * @return bool|mixed|\Modular\Types\SocialActionType|null
	 * @throws \Modular\Exceptions\Social
	 */
	public static function action($field = null) {
		static $action;
		if (is_null($action)) {
			$old = SystemData::disable();
			if (!$action = SocialActionType::get_by_code(static::ActionCode)) {
				throw new Exception("Failed to find a SocialActionType for code '" . static::ActionCode . "'");
			}
			SystemData::enable($old);
		}
		return $action ? ($field ? $action->$field : $action) : null;
	}

	/**
	 * Provider the model $modelClass for a particular mode. Generally the passed in mode is compared to an internal
	 * mode and if they match then a model will be returned, otherwise null. This method is called as an extend so
	 * multiple extensions can provide models, however only one should 'win' when it's mode matches the passed mode.
	 *
	 * @param string $modelClass
	 * @param int    $id
	 * @param string $action
	 *
	 * @return SocialModelInterface|DataObject|null
	 */
	public function provideModel($modelClass, $id, $action) {
		if ($action === static::Action) {
			if ($id) {
				return SocialModel::get($modelClass)->byID($id);
			}
		}
	}

}