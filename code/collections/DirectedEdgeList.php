<?php
namespace Modular\Collections;

use Modular\Models\Graph\Edge;

/**
 * DirectedEdgeList is a DataList whose items are instances of a Directed edge. This means they have a FromModelID, a ToModelID and an EdgeTypeID.
 *
 * It can be used to do fluid graph traverals such as:
 *
 * $nodeList = Node::get()->to();       returns a list of all nodes related to this node
 *
 * @package Modular\Collections
 */
class DirectedEdgeList extends \DataList {
	const InjectorName = 'GraphEdgeList';
	private static $injector_name = self::InjectorName;

	/**
	 * @return DirectedEdgeList
	 */
	public static function create() {
		return \Injector::inst()->createWithArgs(static::config()->get('injector_name') ?: get_called_class(), func_get_args());
	}

	/**
	 * Return a list of the 'To' nodes in this list.
	 *
	 * @return DirectedNodeList
	 */
	public function to() {
		return DirectedNodeList::create(static::edge()->class)->filter('ID', $this->column(static::to_field_name('ID')));
	}

	/**
	 * Return a list of the 'From' nodes in this list.
	 *
	 * @return DirectedNodeList
	 */
	public function from() {
		return DirectedNodeList::create(static::edge()->class)->filter('ID', $this->column(static::from_field_name('ID')));
	}

	protected static function edge() {
		static $edge;
		return $edge ?: $edge = Edge::create();
	}

	protected static function from_field_name($suffix = 'ID') {
		return static::edge()->from_field_name($suffix);
	}

	protected static function to_field_name($suffix = 'ID') {
		return static::edge()->to_field_name($suffix);
	}
}