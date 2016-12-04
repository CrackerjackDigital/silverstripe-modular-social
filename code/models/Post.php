<?php
use Modular\Models\SocialModel;

class Post extends SocialModel implements FeedMeItemModelInterface {
	private static $approveable_mode = Approveable::ApprovalAutomatic;

	private static $db = [
		'Body' => 'Text',
		'PostAs' => 'Enum("Individual,Organisation","Individual")',
		FeedMeItemModel::ExternalIDFieldName => FeedMeItemModel::ExternalIDFieldType,
		FeedMeItemModel::LastPublishedFieldName => FeedMeItemModel::LastPublishedFieldType,
		FeedMeItemModel::LinkFieldName => FeedMeItemModel::LinkFieldType,
		"PostContentLink" => "Text",
		"PostContentLinkTitle" => "Varchar(200)",
		"PostContentLinkText" => "Text",
	];
	private static $has_one = [
		'ForumTopic' => 'ForumTopic', // traditional SS has_many from ForumTopic
	];
	private static $has_many = [
		'RelatedMembers' => 'MemberPostAction.ToPost',
		'RelatedOrganisation' => 'OrganisationPostAction.ToPost',
		'PostReplies' => 'PostReply',
	];
	private static $singular_name = 'Post';

	private static $route_part = 'post';

	private static $fields_for_mode = [
		'list' => [
			'Images' => 'HasImagesField',
			'Title' => true,
			'Body' => true,
			'PostedBy' => true,
			'LastEdited' => true,
		],
		'view' => [
			'Title' => true,
			'Body' => true,
			'PostedBy' => true,
			'LastEdited' => true,
			'Images' => 'HasImagesField',
		],
		'edit' => [
			'PostAs' => true,
			'Body' => true,
			'AttachedImages' => 'ImageEditField',
			'AttachImages' => 'FileAttachmentField',
		],
	];
	private static $summary_fields = [
		'Title' => 'Title',
		// 'PostedBy.Title' => 'Posted by',
		'Images.Count' => 'Images',
		'SourceTitle' => 'Source',
	];

	public function onBeforeWrite() {
		parent::onBeforeWrite();

		preg_match_all('!https?://\S+!', $this->Body, $Match);
		if (empty($Match[0][0])) {
			return;
		}
		$firstUrl = $Match[0][0];

		libxml_use_internal_errors(true);
		//check if url is image
		$isImage = $this->isLinkImage($firstUrl);
		if (!$isImage) {
			return;
		}
		if (!$html = file_get_contents($firstUrl)) {
			return;
		}

		$doc = new DomDocument();
		$doc->strictErrorChecking = false;
		$doc->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));

		$xpath = new DOMXPath($doc);
		$query = '//*/meta[starts-with(@property, \'og:\')]';
		$metas = $xpath->query($query);
		$rmetas = array();
		foreach ($metas as $meta) {
			$property = $meta->getAttribute('property');
			$content = $meta->getAttribute('content');
			$rmetas[$property] = $content;
		}
		if (isset($rmetas['og:title'])) {
			$this->PostContentLinkTitle = $rmetas['og:title'];
			$this->PostContentLinkText = $rmetas['og:description'];
			$this->PostContentLink = $rmetas['og:url'];
		}

	}

	public function PostedBy() {
		if ($this->PostAs == "Individual") {
			$creator = $this->RelatedMembers()
				->filter('ActionType.Code', 'MCP')
				->first();
			if ($creator) {
				return $creator->FromMember();
			};
		} else if ($this->PostAs == "Organisation") {
			$creator = $this->RelatedOrganisation()
				->filter('ActionType.Code', 'OCP')
				->first();
			if ($creator) {
				return $creator->FromOrganisation();
			};
		} else {
			return _t('Post.UnknownPosterText', '[unknown]');
		}

	}

	public function Replies() {
		return $this->PostReplies()->count();
	}

	/**
	 * Convenience method to get the Feed for this item.
	 * @return FeedMeFeedInterface
	 */
	public function RssFeed() {
		return $this->{$this->feedMeAction()};
	}

	/**
	 * Build a csv string of titles from related RssFeed and ForumTopic titles where action exists
	 * (probably only one will for any given post).
	 *
	 * @return string e.g. 'New Zealand Herald Food Feed'
	 */
	public function SourceTitle() {
		$titles = [];

		if ($source = $this->RssFeed()) {
			$titles[] = $source->Title;
		}
		if ($source = $this->ForumTopic()) {
			$titles[] = $source->Title;
		}
		return implode(', ', $titles);
	}

	/**
	 * Called by FeedMeItemModelExtension when it has finished importing the feed item.
	 *
	 * @param array $valuesFromFeed - all values from feed item, may not have changed on though.
	 * @return mixed
	 */
	public function feedMeImported(array $valuesFromFeed = []) {
		// no post import actions
	}

	/**
	 * Called by FeedMeItemModelExtension when it has finished upddating the feed item.
	 *
	 * @param array $valuesFromFeed - all values from feed item, may not have changed on though.
	 * @return mixed
	 */
	public function feedMeUpdated(array $updatedFields = []) {
		// no post update actions

	}

	public function canEdit($member = null) {
		$StartedByObj = $this->RelatedMembers()->filter(['ActionType.Code' => 'MCP'])->first();
		if ($StartedByObj) {
			$ForumTopicOwner = $StartedByObj->FromMemberID;
			if ($ForumTopicOwner == Member::currentUserID()) {
				return true;
			} else {
				return false;
			}

		}return false;
	}

	/**
	 *
	 * Edit source link - used for back to source bar
	 *
	 **/
	public function EditSourceLink() {
		if ($this->ForumTopicID != 0) {
			return "forumtopic/" . $this->ForumTopicID . "/view";
		} else {
			return "post/" . $this->ID . "/view";
		}

	}

	/**
	 *
	 * Formatted Body
	 *
	 **/
	public function BodyFormatted() {
		$body = $this->Body;
		return nl2br(MakeItLink::transform($body));
	}

	public function PostContentLinkDomain() {
		$url = parse_url($this->PostContentLink);
		return $url['host'];
	}

	/**
	 *
	 * Check if url is image
	 *
	 */
	public function isLinkImage($url) {
		$params = array('http' => array(
			'method' => 'HEAD',
		));
		$ctx = stream_context_create($params);
		$fp = @fopen($url, 'rb', false, $ctx);
		if (!$fp) {
			return false;
		}
		// Problem with url

		$meta = stream_get_meta_data($fp);
		if ($meta === false) {
			fclose($fp);
			return false; // Problem reading data from url
		}

		$wrapper_data = $meta["wrapper_data"];
		if (is_array($wrapper_data)) {
			foreach (array_keys($wrapper_data) as $hh) {
				if (substr($wrapper_data[$hh], 0, 19) == "Content-Type: image") // strlen("Content-Type: image") == 19
				{
					fclose($fp);
					return true;
				}
			}
		}

		fclose($fp);
		return false;
	}

}