<?php
namespace Modular\Models;

use FeedMeFeedModelExtension as FeedMeFeedModel;

/**
 * @property string RssURL
 * @property string Interests
 *
 * @method MemberRssFeed RelatedMembers()
 * @method Post[] Posts() returns an array of PostModels
 */
class SocialRssFeed extends SocialModel implements \FeedMeFeedModelInterface  {

	private static $singular_name = 'RSS Feed';

	private static $plural_name = 'RSS Feeds';

	private static $route_part = 'rssfeed';

	private static $summary_fields = ['Title', 'RssURL'];

	private static $db = [
		'RssURL' => 'Text',
		'Interests' => 'Text',
	];

	private static $has_many = [
		'Posts' => 'Post',
		'RelatedMembers' => 'MemberRssFeed.ToModel',
	];

	/**
	 * Convenience method return the Feeds items.
	 * @return SS_List of FeedMeItemInterface instances
	 */
	public function RssFeedItems() {
		return $this->{FeedMeFeedModel::action_name()};
	}

	/**
	 * Called by FeedMeFeedModelExtension when it has finished importing the feed.
	 * @param array $valuesFromFeed - all item fields, may not have changed though.
	 * @return mixed
	 */
	public function feedMeImported(array $valuesFromFeed = []) {
		// TODO: Implement feedMeImported() method.
	}

	/**
	 * Called by FeedMeFeedModelExtension when it has finished updating the feed model.
	 *
	 * @param $updatedFields - array of fields which were updated
	 * @return mixed
	 */
	public function feedMeUpdated(array $updatedFields = []) {
		// TODO: Implement feedMeUpdated() method.
	}
	/**
	 * Return a list of this feeds posts.
	 * @param null|int $limit
	 * @return \ArrayData with ListItems property set to posts list
	 */
	public function ListView($limit = null) {
		$posts = $this->Posts()->exists() ? $this->Posts()->limit($limit) : $this->Posts();
		return new ArrayData([
			'ExtraClasses' => 'feed-posts',
			'ListItems' => $posts
		]);
	}
}
