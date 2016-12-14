<?php
namespace Modular\Extensions\Admin;

use FieldList;

/**
 * SocialSiteConfig
 *
 * @package Modular\Extensions\Admin
 * @property string RegisterMemberHeading
 * @property string RegisterMemberContent
 * @property string RegisterOrganisationHeading
 * @property string RegisterOrganisationContent
 * @property string RegisterCompleteMessage
 * @property string RegisterWaitMessage
 * @method \Member SystemMemberApprover()
 * @method \Member SystemOrganisationApprover()
 * @method \Member SystemPostApprover()
 * @method \Image RegisterMemberImage()
 * @method \Image RegisterOrganisationImage()
 * @method \Image RegisterCompleteImage()
 */
class SocialSiteConfig extends \DataExtension {
	private static $db = [
		'RegisterMemberHeading'       => 'Text',
		'RegisterMemberContent'       => 'HTMLText',
		'RegisterOrganisationHeading' => 'Text',
		'RegisterOrganisationContent' => 'HTMLText',
		'RegisterCompleteMessage'     => 'HTMLText',
		'RegisterWaitMessage'         => 'HTMLText',
	];

	private static $has_one = [
		'SystemMemberApprover'       => 'Member',
		'SystemOrganisationApprover' => 'Member',
		'SystemPostApprover'         => 'Member',
		'RegisterMemberImage'        => 'File',
		'RegisterOrganisationImage'  => 'File',
		'RegisterCompleteImage'      => 'File',
	];

	public function updateCMSFields(FieldList $fields) {
		foreach (array_merge(self::$db, self::$has_one) as $fieldName => $type) {
			if (substr($fieldName, 0, strlen('Register')) === 'Register') {
				switch ($type) {
				case 'HTMLText':
					$fieldType = 'HTMLEditorField';
					break;
				case 'File':
					$fieldType = 'UploadField';
					break;
				default:
					$fieldType = 'TextField';
				}

				$fields->addFieldToTab(
					'Root.Registration',
					new $fieldType($fieldName)
				);
			}
		}
	}
}