<?php
namespace Modular\Actions;

use \Modular\Extensions\Controller\SocialAction;

class Administrable extends SocialAction {
    const ActionCode = 'ADM';

	private static $url_handlers = [
		'$ID/admin' => self::Action,
	];

	public function canAdminister($source = null) {
        return parent::canDoIt(self::ActionCode, $source);
    }
}