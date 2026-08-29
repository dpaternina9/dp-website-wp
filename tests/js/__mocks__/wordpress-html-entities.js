/**
 * A stand-in for @wordpress/html-entities. See wordpress-block-editor.js.
 *
 * Enough of the real decoder for a post title: the five entities WordPress
 * escapes titles with, and the numeric forms it writes for typographic
 * punctuation.
 */
const named = {
	'&amp;': '&',
	'&lt;': '<',
	'&gt;': '>',
	'&quot;': '"',
	'&#039;': "'",
};

const decodeEntities = ( value ) =>
	String( value ?? '' )
		.replace(
			/&amp;|&lt;|&gt;|&quot;|&#039;/g,
			( match ) => named[ match ]
		)
		.replace( /&#(\d+);/g, ( match, code ) =>
			String.fromCodePoint( Number( code ) )
		);

module.exports = { decodeEntities };
