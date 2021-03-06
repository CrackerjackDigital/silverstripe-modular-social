<?php
namespace Modular\Actions;

use \Modular\Extensions\Controller\SocialAction;

class Administrable extends SocialAction {
    const ActionCode = 'ADM';
	const ActionName = 'admin';

	public function canAdminister($source = null) {
        return parent::canDoIt(self::ActionCode, $source);
    }
}