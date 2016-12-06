<?php
use Modular\Relationships\SocialHasManyMany;

/**
 * Extension which can report on a model's 'favourites', that is other models which have a LIK or FOL action
 */
class HasFavouritesExtension extends SocialHasManyMany {
	/**
	 * Returns a list of
	 */
	public function HasFavourites($actionCodes = ['LIK', 'FOL'], $relationshipNames = ['RelatedMembers', 'RelatedOrganisations']) {
		$data = [
			'Title' => _t('HasFavourites.WidgetTitle', 'Favourites', 'Favourites'),
			'Content' => _t('HasFavourites.WidgetContent', 'Here are your favourites:'),
			'Model' => $this->owner,
			'ListItems' => $this->favouritesList($actionCodes, $relationshipNames)->reverse(),

		];
		return new ArrayData($data);
	}

	private function favouritesList($parentActionCodes, $relationshipNames = []) {
		return $this->relatedByParent($parentActionCodes, $relationshipNames);
	}
}