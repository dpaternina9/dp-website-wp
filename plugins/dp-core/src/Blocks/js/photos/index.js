/**
 * The Photos editing surface, on the client.
 *
 * Two things, both on `dp_photo` only: the single-choice Trip panel in place of
 * core's token field (`trip-select.js`), and the Photo and Camera panels in the
 * document sidebar (`photo-panel.js`). The wall itself is `dp/photo-wall`, a
 * server-rendered block whose editor preview is registered with the others in
 * `dynamic/server-rendered.js`.
 *
 * Internal dependencies
 */
import { registerPhotoPanels } from './photo-panel';
import { registerTripSelect } from './trip-select';

/**
 * Everything the Photos editing surface needs on the client.
 *
 * @return {void}
 */
export function installPhotoEditing() {
	registerTripSelect();
	registerPhotoPanels();
}
