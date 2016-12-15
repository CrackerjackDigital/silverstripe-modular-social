<?php
namespace Modular\Forms\Social;

use Email;
use EmailField;
use FieldList;
use Form;
use FormAction;
use Modular\Forms\SocialForm;
use RequiredFields;
use SS_HTTPRequest;
use TextareaField;
use TextField;

/**
 *  NZFIN Contact form
 */
class ContactForm extends SocialForm {
	public function __construct($controller, $name) {

		$fields = FieldList::create(
			TextField::create("Name", "Name"),
			EmailField::create("Email", "Email Address"),
			TextareaField::create("Message", "Message")
		);

		$actions = FieldList::create(
			FormAction::create('submitEnquiry')->setTitle("Send Message")->addExtraClass("btn btn-blue")
		);

		$validator = new RequiredFields("Name", "Email", "Message");
		parent::__construct($controller, $name, $fields, $actions, $validator);

	}

	public function submitEnquiry(array $data, Form $form, SS_HTTPRequest $request) {
		unset($data['SecurityID']);
		unset($data['action_submitEnquiry']);
		unset($data['url']);

		$spam = json_decode(file_get_contents('http://www.stopforumspam.com/api?f=json&email=' . $data['Email']), true);

		if ($spam['email']['frequency'] > 1) {
			$this->controller->redirect("contact/thank-you");
		}

		$body = '';
		foreach ($data as $key => $value) {
			if (is_array($value)) {
				$body .= "<p><strong>" . $key . ":</strong></p>";
				foreach ($value as $key2 => $value2) {
					$body .= "<p> - $value2</p>";
				}
			} else {
				$body .= "<p><strong>" . $key . ":</strong> $value</p>";
			}

		}

		/**

		TODO:
		- create siteconfig to hold recieving email address

		 **/

		$to = "angus.brown@foodinnovationnetwork.co.nz";
		$from = "no-reply@foodportal.nz";
		$subject = "NZFIN New Contact Message";
		$email = new Email($from, $to, $subject, $body);
		$email->send();

		return $this->controller->redirect("contact/thank-you");
	}
}