<?php
namespace Modular\Extensions\Admin;

/**
 * Extension to add to SilverStripe security Group model.
 */
class SecurityGroup extends \DataExtension {
    private static $belongs_many_mnay = [
        'NotifyActionTypes' => 'Modular\Types\SocialAction'
    ];

}