<?php
namespace Modular\Collections;

use Modular\Edges\Directed;

/**
 * DirectedEdgeList is a DataList whose items are instances of a Directed edge. This means they have a FromModelID, a ToModelID and an EdgeTypeID.
 *
 * @package Modular\Collections
 */
class DirectedEdgeList extends EdgeList {
	const EdgeClassName = 'Modular\Edges\Directed';

	public function to() {
		foreach ($this as $item) {
			yield (new DirectedNodeist(static::edge_class_name()))->filter('ID', ($item->{static::to_field_name('ID')}));
		}
	}

	public function from() {
		foreach ($this as $item) {
			yield (new DirectedNodeList(static::edge_class_name()))->filter('ID', ($item->{static::from_field_name('ID')}));
		}
	}
	protected static function to_field_name($suffix = 'ID') {
		$edgeClassName = static::EdgeClassName;
		return $edgeClassName::to_field_name($suffix);
	}
}