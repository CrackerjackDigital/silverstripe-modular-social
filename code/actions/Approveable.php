<?php
namespace Modular\Actions;

use ArrayList;
use CompositeField;
use Controller;
use DataObject;
use DateField;
use DropdownField;
use FieldList;
use LeftAndMain;
use Member;
use Modular\Application;
use Modular\Edges\SocialRelationship;
use Modular\emailer;
use Modular\enabler;
use Modular\Extensions\Model\SocialMember;
use Modular\Extensions\Model\SocialModel;
use Modular\notifies;
use Modular\Types\SocialAction;
use OptionsetField;
use Permission;
use SQLQuery;
use SS_Datetime;

class Approveable extends SocialModel {
	use emailer;
	use enabler;
	use notifies;

	const ActionCode = 'APP';
	const Action = 'approve';

	// this config field will hold the approval mode, e.g. Automatic, Required etc, Extensions don't have a config
	// so this is always managed through Config::inst() so we don't need to declare and so keep in sync a static.
	const ModeConfigVariable = 'approveable_mode';

	// name of the approveable field e.g. in forms
	const FieldName = 'ApproveableApproved';

	// values for approveable field
	const PendingValue  = 'Pending';
	const ApprovedValue = 'Approved';
	const DeclinedValue = 'NotApproved';

	// approval modes
	const ApprovalAutomatic = 0;
	const ApprovalManual    = 1;

	const ApprovalStatusBeforeWrite = 'ApprovalStatusBeforeWrite';

	const PermissionPrefix = 'CAN_APPROVE_';

	private static $approveable_mode = \Modular\Actions\Approveable::ApprovalManual;
	private static $approveable_field_name = \Modular\Actions\Approveable::FieldName;

	private static $enabled = true;

	// NOT USED: ToDo choose only approveable actions, currently CRT is used when a model is new
	private static $approveable_actions = [
		'CRT',
	];

	/**
	 * If approval mode is not automatic and we don't have the 'APP' permission then augment query to only return
	 * records which have been approved.
	 *
	 * @param SQLQuery $query
	 */
	public function augmentSQL(SQLQuery &$query) {
		// skip checks if dev/build or non-content controller, e.g. CMS
		if (Controller::curr() instanceof \ContentController) {
			if (self::enabled() && !$this->isAutomaticApproval()) {
				$query->addWhere(self::FieldName . " = '" . self::ApprovedValue . "'");
			}
		}
		parent::augmentSQL($query);
	}

	/**
	 * Returns if extended model is already approved.
	 * @return bool
	 */
	public function isApproved() {
		return $this()->{self::FieldName} == self::ApprovedValue;
	}

	/**
	 * We can approve if we have 'APP' permissions (or ADMIN)
	 *
	 * @param Member $source if null then current member will be used
	 * @return bool
	 */
	public function canApprove($source = null) {
		return parent::canDoIt(self::ActionCode, $source);
	}

	/**
	 * - If approval is set to 'Automatic' mode then automatically approve the owner.
	 * - If approval status has changed at all then set ApproveableMemberID and ApproveableDate.
	 *
	 * @throws \Exception
	 */
	public function onBeforeWrite() {
		if (!$this()->isInDB()) {
			// if we're new and owner.config.approveable_type is automatic then approve by logged in member
			if ($this()->isAutomaticApproval()) {
				$this()->ApproveableApproved = self::ApprovedValue;
			}
		}
		// if status has changed set previous value on the dataobject so onAfterWrite can pick it up
		if ($this->fieldValueChanged(self::FieldName, $previousValue)) {
			// set value of previous status for onAfterWrite to pick up
			$this()->{self::ApprovalStatusBeforeWrite} = $previousValue;
		}
		parent::onBeforeWrite();
	}

	/**
	 * After writing owner to database signal other extensions that approval status has changed.
	 */
	public function onAfterWrite() {
		// indicates it changed if a value is returned, value will be previous status if needed here
		$previousValue = $this()->{self::ApprovalStatusBeforeWrite};
		if (!$previousValue && !$this->isAutomaticApproval()) {
			// we may have saved record in 'Approved' state, e.g. in CMS.
			if (!$this->isApproved()) {
				// no previous value so a new record, send request
				$this->queueRequestNotification('CRT');
			}
		} else {
			if (!$this()->{self::FieldName} == self::PendingValue) {
				// there was a previous value and the current value is not 'pending' so send the response
				$this->queueResponseNotification('CRT');
			}
		}
	}

	/**
	 * Send an email to approvers asking for approval of an action.
	 *
	 * @param string $forAction
	 */
	public function queueRequestNotification($forAction) {
		$status = self::PendingValue;       // e.g. Approved or NotApproved
		$actionClassName = get_called_class();      // e.g. Approveable

		$subject = _t(
			"$actionClassName.Notifications.Request.Subject", // Approveable.Notifications.Request.Subject
			"Your {model} '{title}' {action}",
			[
				'model'  => $this()->i18n_singular_name(),
				'title'  => $this()->Title,
				'action' => _t(
					"$actionClassName.Request",
					"requires approval"
				),
			]
		);
		$message = _t(
			"$actionClassName.Notifications.Request.Message",    // Approveable.Notifications.Request.Message
			"{action} for {model} '{title}': {link}",
			[
				'model'  => $this()->i18n_singular_name(),
				'title'  => $this()->Title,
				'link'   => $this()->ActionLink('view'),
				'action' => _t(
					"$actionClassName.$status",
					"Approval required"
				),
			]
		);
		$approvers = $this->Approvers($forAction);

		if ($approvers->count() == 0) {
			$subject = "Problem sending approval request for: $subject";
			$message = "No approvers could be found to send this message to: $message";
			$approvers->push(\Member::default_admin());
			$this->debug_error("Failed to find any members to notify, sending to admin instead");
		}

		// Approveable_Pending
		$template = "{$actionClassName}_{$status}";

		if ($this() instanceof \Member) {
			$initiator = $this()->Email;
		} else {
			$initiator = SocialMember::current_or_guest()->Email;
		}

		$this->notify(
			$initiator,                         // sender is whoever is creating the model
			$approvers,                         // recipients are the approvers for this action on these models
			$subject,
			$message,
			$template
		);
	}

	/**
	 * Send an email back to the original initiator of an action regarding the action being Approved or Not Approved.
	 *
	 * @param string $forAction
	 */
	public function queueResponseNotification($forAction = 'CRT') {
		$status = $this()->{self::FieldName};       // e.g. Approved or NotApproved
		$actionClassName = get_called_class();      // e.g. Approveable

		$subject = _t(
			"$actionClassName.Notifications.$status.Subject", // Approveable.Notifications.NotApproved.Subject
			"Your {model} '{title}' has {action}: {link}",
			[
				'model'  => $this()->i18n_singular_name(),
				'title'  => $this()->Title,
				'link'   => $this()->ActionLink('view'),
				'action' => _t(
					"$actionClassName.$status",
					$status == self::ApprovedValue ? 'been approved' : 'not been approved'
				),
			]
		);
		$message = _t(
			"$actionClassName.Notifications.$status.Message",    // Approveable.Notifications.Approved.Message
			"Your {model} '{title}' has {action}",
			[
				'model'  => $this()->i18n_singular_name(),
				'title'  => $this()->Title,
				'action' => _t(
					"$actionClassName.$status",
					$status == self::ApprovedValue ? 'been approved' : 'not been approved'
				),
			]
		);
		// get the last 'CRT' action that was performed on the extended model
		if (!$initiator = $this()->LastActor('CRT')) {
			// otherwise we send to approvers with a hint that couldn't find the 'real' person to notify
			$initiator = Application::admin_email();
			$subject = "Problem sending approval response for: $subject";
			$message = "No initiator could be identified to send this message to: $message";
			$this->debug_error("Failed to find initiator to send approval response to, sending to admin instead");
		}

		// e.g. Approveable_Approved or Approveable_NotApproved
		$template = "{$actionClassName}_{$status}";

		// TODO work out where we store approvers/emails
		$this->notify(
			SocialMember::current_or_guest()->Email,             // sender is whoever is approving
			$initiator,                         // recipient
			$subject,
			$message,
			$template,
			[
				'ACTION'  => $this()->LastAction('CRT'),
				'TOMODEL' => $this(),
			]
		);
	}

	/**
	 * Return a list of members who should be notified when an action is performed on the extended model.
	 *
	 * @param array|string $forActions
	 * @param string       $fromModelClass
	 * @return \ArrayList of \Member objects
	 */
	public function Approvers($forActions, $fromModelClass = 'Member') {
		$forActions = is_array($forActions) ? $forActions : $forActions;
		$fromModelClass = is_object($fromModelClass) ? get_class($fromModelClass) : $fromModelClass;

		$approvers = new ArrayList();
		/** @var string|SocialAction $relationshipClassName */

		// first find the names of the SocialModel classes which link to the extended model, e.g. 'MemberOrganisationRelationship'
		if ($relationshipClassNames = SocialRelationship::implementors($fromModelClass, $this()->ClassName)) {
			foreach ($relationshipClassNames as $relationshipClassName) {
				// find the RelationshipTypes which deal with actions between the found relationships models
				$relationshipTypes = SocialAction::get_by_edge_type_code(
					$relationshipClassName::from_class_name(),
					$relationshipClassName::to_class_name(),
					$forActions
				);
				/** @var SocialAction $relationshipType */
				foreach ($relationshipTypes as $relationshipType) {
					$approvers->merge($relationshipType->NotificationRecipients());
				}
			}
		}
		return $approvers;
	}

	/**
	 * Returns true if approval is automatic, otherwise manual.
	 *
	 * @return bool
	 */
	public function isAutomaticApproval() {
		$mode = $this()->config()->get(self::ModeConfigVariable) ?: static::config()->get('approval_mode');
		return $mode == self::ApprovalAutomatic;
	}

	/**
	 * Set the mode to provided mode, ephemeral only works for current running process. If no mode
	 * is provided then the mode before the last set_approval_mode will be restored.
	 *
	 * @param int|null $approvalMode    - should be one of the self.ApprovalABC constants
	 *                                  or null/missing to restore previous mode.
	 * @return string
	 */
	public static function set_approval_mode($approvalMode = null) {
		static $previous_mode;

		if (is_null($previous_mode)) {
			$previous_mode = \Config::inst()->get(__CLASS__, self::ModeConfigVariable);
		}

		if (is_null($approvalMode)) {
			static::set_approval_mode($previous_mode);
		} else {
			\Config::inst()->update(__CLASS__, self::ModeConfigVariable, $approvalMode);
		}
		return $previous_mode;
	}

	/**
	 * Return fields for this widget, expects any existing fields for ApprovableExtension to have been
	 * removed already. If we don't have APP permissions then make the fields read-only.
	 *
	 * @return CompositeField
	 */
	public function ApproveableWidget() {
		$permissionCode = SocialAction::make_permission_code(
			self::PermissionPrefix,
			$this()
		);

		$composite = new CompositeField([
			new DateField(
				'ApproveableDate',
				'Approved Date',
				$this()->ApproveableDate),
			new DropdownField(
				'ApproveableMemberID',
				'Approved By',
				Permission::get_members_by_permission($permissionCode)->map()),
		]);

		$composite->replaceField(
			'ApproveableApproved',
			new OptionsetField('ApproveableApproved', 'Approved', [self::ApprovedValue, self::DeclinedValue])
		);
		if (!SocialAction::check_permission(self::ActionCode, $this())) {
			$composite = $composite->performReadonlyTransformation();
		}
		$composite->addExtraClass('approveable-widget');
		return $composite;
	}

	/**
	 * Adds ApproveableWidget to fields if current member has Approval Permission for current model and we're
	 * in the CMS.
	 *
	 * - before owner.config.approveable_fields_before field name
	 * - after owner.config.approveable_fields_after field name
	 * - at end of fields
	 *
	 * @patam DataObject $model
	 * @param FieldList $fields
	 * @param           $mode
	 * @param array     $requiredFields
	 */
	public function updateFieldsForMode(DataObject $model, FieldList $fields, $mode, array &$requiredFields = []) {
		if (Controller::curr() instanceof LeftAndMain) {
			if (SocialAction::check_permission('APP', $this->getModelClass())) {
				// remove db and instance fields as will be replaced by ApprovableWidget. We also need to append
				// 'ID' to has_one field names.

				self::remove_own_fields($fields);

				$fields->push($this->ApproveableWidget());
			}
		}
	}

	/**
	 * Approve extended model
	 */
	public function approveableApprove() {
		$this->approveableUpdate(self::ApprovedValue);
	}

	/**
	 * Decline approval for extended model.
	 */
	public function approveableDecline() {
		$this->approveableUpdate(self::DeclinedValue);
	}

	/**
	 * Set fields to approve the extended object.
	 */
	public function approveableUpdate($status = self::ApprovedValue) {
		$this()->ApproveableApproved = $status;
		$this()->ApproveableData = SS_Datetime::now()->Rfc2822();
		$this()->ApproveableMember = Member::currentUser();
	}
}