<?php
namespace Modular\Actions;

use \Modular\Extensions\Controller\SocialAction;

class Administrable extends SocialAction {
    const ActionTypeCode = 'ADM';
	const Action = 'admin';

    public function canAdminister($source = null) {
        return parent::canDoIt(self::ActionTypeCode, $source);
    }
}