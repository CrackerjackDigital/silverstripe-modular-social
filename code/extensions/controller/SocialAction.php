<?php
namespace Modular\Extensions\Controller;

use DataObject;
use Modular\Edges\SocialRelationship;
use Modular\Exceptions\Social as Exception;
use Modular\Extensions\Model\SocialMember;
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
	const Action            = '';
	const ReverseAction     = '';
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
			$isCreator = SocialRelationship::graph(
				\Member::currentUser(),
				$this(),
				'CRT'
			)->count();

			if ($isCreator) {
				return true;
			}

		}
		$canDoIt = SocialActionType::check_permission(
			$actionCodes,
			SocialMember::current_or_guest(),
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
	 * Provider the model $modelClass for a particular mode. Generally the passed in mode is compared to an internal
	 * mode and if they match then a model will be returned, otherwise null. This method is called as an extend so
	 * multiple extensions can provide models, however only one should 'win' when it's mode matches the passed mode.
	 *
	 * @param string $modelClass
	 * @param int    $id
	 * @param string $action
	 * @param bool   $createIfNotFound
	 *
	 * @return \DataObject|\Modular\Interfaces\SocialModel|null
	 */
	public function provideModel($modelClass, $id, $action, $createIfNotFound = false) {
		$model = null;
		if ($action === static::Action) {
			if ($id) {
				$model = SocialModel::get($modelClass)->byID($id);
			}
			if (!$model && $createIfNotFound) {
				$model = $model::create();
			}
		}
		return $model;
	}

	/**
	 * Creates SocialRelationship records for each of the SocialRelationship derived classes that represent an edge between the
	 * provided models.
	 *
	 * e.g. given a Member and an Organisation will create a MemberOrganisation edge and any others which are between Member and Organisation Models.
	 *
	 * @param $fromModel
	 * @param $toModel
	 */
	public static function make($fromModel, $toModel) {
		/** @var string $implementor class name */
		foreach (SocialRelationship::implementors($fromModel, $toModel) as $implementor) {
			/** @var SocialRelationship $instance */
			$instance = new $implementor();
			$instance->setFrom($fromModel);
			$instance->setTo($toModel);
			$instance->setEdgeType(static::ActionCode);
			$instance->write();
		}
	}

}