<?php
namespace Modular\Edges;

/**
 * SocialModel between two models with a relationship type, used to track historical actions and relationships made/broken.
 */
class SocialRelationship extends Directed {
	const NodeAClassName = 'Modular\Models\SocialModel';
	const NodeBClassName = 'Modular\Models\SocialModel';

}
