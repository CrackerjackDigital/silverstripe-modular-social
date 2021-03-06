<?php
namespace Modular\Actions;

use ArrayList;
use CompositeField;
use Modular\Application;
use Modular\Edges\SocialRelationship;
use Modular\emailer;
use Modular\enabler;
use Modular\Extensions\Controller\SocialAction;
use Modular\Extensions\Model\SocialMember;
use Modular\notifies;
use Modular\Types\SocialEdgeType as SocialEdgeType;
use OptionsetField;

/**
 * Controller extension for Approveable handling controllers.
 *
 * @package Modular\Actions
 */
class Approveable extends SocialAction {
	use emailer;
	use enabler;
	use notifies;

	const ActionCode = 'APP';
	const ActionName = 'approve';

	// just in the offchance we need to change the configuration name for some reason
	const ModeConfigVariable = 'approveable_mode';

	const PermissionPrefix = 'CAN_APPROVE_';

	// approval modes
	const ApprovalAutomatic = 0;
	const ApprovalManual    = 1;

	// values used for e.g. display and in forms
	const PendingValue  = 'Pending';
	const ApprovedValue = 'Approved';
	const DeclinedValue = 'Declined';

	private static $allowed_actions = [
		'approve' => '->canDoIt("APP", "action")',
		'decline' => '->canDoIt("APP", "action")',
	];

	private static $approveable_mode = \Modular\Actions\Approveable::ApprovalManual;

	private static $enabled = true;

	// NOT USED: ToDo choose only approveable actions, currently CRT is used when a model is new
	private static $approveable_actions = [
		'CRT',
	];

	public function request() {
		$this->queueRequestNotification('CRT');
	}

	public function approve($member = null) {
		$member = ($member instanceof $member) ? $member : \Member::currentUser();
		if (SocialRelationship::make($member, $this(), static::ActionCode, 'approve')) {
			$this->queueResponseNotification('CRT');
		}
	}

	public function decline($member = null) {
		$member = ($member instanceof $member) ? $member : \Member::currentUser();
		if (SocialRelationship::remove($member, $this(), static::ActionCode, 'decline')) {
			$this->queueResponseNotification('CRT');
		}
	}

	/**
	 * Check for an 'APP' action to the extended Model
	 */
	public function Approved() {
		return SocialRelationship::latest(null, $this(), static::ActionCode);
	}

	/**
	 * Check that an 'APP' action to the extended Model doesn't exist
	 */
	public function Declined() {
		return !SocialRelationship::latest(null, $this(), static::ActionCode);
	}

	/**
	 * Send an email to approvers asking for approval of an action.
	 *
	 * @param string $forAction
	 */
	public function queueRequestNotification($forAction) {
		if (static::notifies_enabled()) {

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
						"$actionClassName.Request",
						"Approval required"
					),
				]
			);
			$approvers = $this->Approvers($forAction);

			if ($approvers->count() == 0) {
				$subject = "Problem sending approval request for: $subject";
				$message = "No approvers could be found to send this message to: $message";
				$approvers->push(\Member::default_admin());
			}

			// Approveable_Pending
			$template = "{$actionClassName}_Request";

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
	}

	/**
	 * Send an email back to the original initiator of an action regarding the action being Approved or Not Approved.
	 *
	 * @param string $forAction
	 */
	public function queueResponseNotification($forAction = 'CRT') {
		if (static::notifies_enabled()) {

			$status = $this()->Approved();
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
			// get the person who performed last 'CRT' action that was performed on the extended model
			if (!$initiator = $this()->LastActor('CRT')) {
				// otherwise we send to approvers with a hint that couldn't find the 'real' person to notify
				$initiator = Application::admin_email();
				$subject = "Problem sending approval response for: $subject";
				$message = "No initiator could be identified to send this message to: $message";
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
					'ACTION'  => $this()->LatestAction('CRT'),
					'TOMODEL' => $this(),
				]
			);
		}
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
		/** @var string|SocialEdgeType $relationshipClassName */

		// first find the names of the SocialModel classes which link to the extended model, e.g. 'MemberOrganisationRelationship'
		if ($relationshipClassNames = SocialRelationship::implementors($fromModelClass, $this()->ClassName)) {
			/** @var SocialRelationship $relationshipClassName */
			foreach ($relationshipClassNames as $relationshipClassName) {
				// find the RelationshipTypes which deal with actions between the found relationships models
				$relationshipTypes = SocialEdgeType::get_for_models(
					$relationshipClassName::from_class_name(),
					$relationshipClassName::to_class_name(),
					$forActions
				);
				/** @var SocialEdgeType $relationshipType */
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
		$composite = new CompositeField([
			new OptionsetField('ApprovalStatus', 'Approval', [self::PendingValue, self::ApprovedValue, self::DeclinedValue]),
		]);

		if (!SocialEdgeType::check_permission(SocialMember::current_or_guest(), $this(), self::ActionCode)) {
			$composite = $composite->performReadonlyTransformation();
		}
		$composite->addExtraClass('approveable-widget');
		return $composite;
	}


}