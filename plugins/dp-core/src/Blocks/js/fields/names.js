/**
 * The blocks the editing form is drawn with, by name.
 *
 * Kept in a module of its own, importing nothing, for the same reason
 * `SERVER_RENDERED` is: an integration test reads this list out of the source
 * and holds it against what `DP\Core\Editor\FieldBlocks` registers in PHP. A
 * block registered on one side and not the other is either a `core/missing` in
 * the canvas or a definition nothing draws, and neither announces itself.
 *
 * The order is `FieldBlocks::names()`'s: the label first, then the six controls
 * in `Control`'s declaration order.
 *
 * @type {string[]}
 */
export const FIELD_BLOCKS = [
	'dp/field-label',
	'dp/meta-text',
	'dp/meta-year',
	'dp/meta-choice',
	'dp/meta-flag',
	'dp/meta-lines',
	'dp/meta-reference',
];
