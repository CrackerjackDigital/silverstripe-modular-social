<?php
namespace Modular\Actions;

use Controller;
use EmailNotifier;
use Member;
use Modular\Edges\MemberMember;
use Modular\Edges\SocialRelationship;
use Modular\Extensions\Controller\SocialAction;
use SS_HTTPRequest;

class Confirmable extends SocialAction  {
	use \Modular\enabler;

	const ActionCode = 'CFM';

	const ConfirmedFieldName         = 'ConfirmedFlag';
	const ConfirmationTokenFieldName = 'ConfirmationToken';

	private static $url_handlers = [
		'confirm/$Token' => self::ActionName,
	];

	private static $allowed_actions = [
		self::ActionName => '->canConfirm("action")',
	];

	private static $action_templates = [
		self::ActionName => self::ActionName,
	];

	private static $action_modes = [
		self::ActionName => self::ActionName,
	];

	/**
	 * Check member can confirm registrations
	 *
	 * @param string $source set to 'action' if this is a direct controller allowed_actions check
	 * @return bool|int|void
	 */
	public function canConfirm($source = null) {
		return true;
	}

	public function Confirmed() {
		return SocialRelationship::to(
			$this(),
			'CRT'
		);
	}

	/**
	 * Find all create relationships between the toModel and any Members (not necessarily this member), and then check that those members
	 * have confirmed themselves. (this is not Approval). Checks enabled state of this extension before, if not enabled skips checks (useful
	 * during registration process).
	 *
	 * @param DataObject $fromModel            ignored here
	 * @param DataObject $toModel              a foreign model to check that we can do something with
	 * @param string     $actionTypeCode ignored here
	 * @return bool
	 */
	public function checkPermissions($fromModel, $toModel, $actionTypeCode) {
		if ($confirmed = !static::enabled()) {
			$registrants = MemberMember::nodeBForAction($toModel, 'REG');

			/** @var Confirmable $registrant */
			foreach ($registrants as $registrant) {
				if (!$confirmed = $registrant->isConfirmed()) {
					break;
				}
			}
		}
		if (!$confirmed) {
			\Controller::curr()->httpError('403', "Sorry, the registrant has not yet confirmed their account");
		}
		return $confirmed;
	}

	/**
	 * Handles the 'delete' action, only POST. If GET then returns 405.
	 */
	public function confirm(SS_HTTPRequest $request) {
		$token = $request->param('Token');
		if (isset($token) && !empty($token)) {
			/** @var Member|Confirmable $member */
			$reenable = Approveable::disable();
			$member = Member::get()->filter([self::ConfirmationTokenFieldName => $token])->first();
			Approveable::enable($reenable);

			if ($member) {
				if ($member->{self::ConfirmedFieldName} == 0) {
					$member->{self::ConfirmedFieldName} = 1;
					$member->write();

					$this->sendConfirmationSuccessEmail($member);

					$this()->setSessionMessage("You have successfully confirmed your account.");

				} else {
					$this()->setSessionMessage("You have already confirmed your account.", "notice");
				}
				//automatic login
				return $this()->redirect('/Security/login');
			} else {
				return $this()->httpError(404);
			}
		} else {
			return $this()->httpError(405);
		}
	}

	/**
	 * Renew confirmation token on extended model and write it, returns a link to confirmation page for the token.
	 *
	 * @return string ling e.g. 'member/confirm/<token>'
	 */
	public function renewToken() {
		/** @var SocialAction $model */
		$token = self::generate_token(md5($this()->ClassName . $this()->ID . time()));
		$this()->{self::ConfirmationTokenFieldName} = $token;
		$this()->write();
		$routePart = \Config::inst()->get($this()->ClassName, 'route_part');

		return Controller::join_links($routePart, self::ActionName, $token);
	}

	/**
	 * Generate and return a token using $from as seed/data.
	 *
	 * @param string $from
	 * @return string
	 */

	public static function generate_token($from) {
		return md5($from);
	}

	/**
	 *
	 * Generate a new token and add to extended record, send email to member with confirmation link with this token.
	 *
	 * @param \Member $recipient who receives the email (not the model which needs to be confirmed)
	 * @throws \Exception
	 */
	public function sendConfirmationLinkEmail(Member $recipient) {
		$link = $this->renewToken();

		/** @var EmailNotifier $notifier */
		$notifier = EmailNotifier::create();
		$notifier->setEmailTemplate('Welcome_Token');
		$notifier->setEmailSubject("NZ Food Portal registration confirmation");
		$notifier->setRecipients($recipient);
		$notifier->setMessage("Confirm registration");
		$notifier->setEmailTemplateData(["TokenLink" => $link, "Member" => $recipient, "Target" => $this()]);
		$notifier->send();
	}

	/**
	 * Send confirmation email after succesfull confirmation action.
	 *
	 * @param \Member $recipient who receives the email (not the confirmed model)
	 * @throws \Exception
	 */
	public function sendConfirmationSuccessEmail(Member $recipient) {
		/** @var EmailNotifier $notifier */
		$notifier = EmailNotifier::create();
		$notifier->setEmailTemplate('Welcome_Individual');
		$notifier->setEmailSubject("Welcome to NZ Food Portal");
		$notifier->setRecipients($recipient);
		$notifier->setMessage("Welcome Message");
		$notifier->setEmailTemplateData(["Member" => $recipient]);
		$notifier->send();
	}
}