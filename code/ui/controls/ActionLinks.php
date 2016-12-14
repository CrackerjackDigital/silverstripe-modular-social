<?php
namespace Modular\UI\Controls;

use ArrayData;

/**
 * Adds 'ActionLinks' to a controller which will render links applicable
 * to the view mode (view, edit, new, list), the model being viewed and the
 * permissions of the logged-in user.
 */

class SocialActionLinks extends SocialActionMenu {
    const MenuClass = 'action-links';
    const FilterField = 'ShowInActionLinks';

    /**
     * Return a rendered ActionMenu component with actions allowed the logged in member to the extended class.
     * @param string $restrictTo - csv list of actions we wan't to limit to
     * @return ArrayData
     */
    public function ActionLinks($restrictTo = null) {
        $model = $this()->getModelInstance(null);
        return self::action_links($model, $restrictTo);
    }


}