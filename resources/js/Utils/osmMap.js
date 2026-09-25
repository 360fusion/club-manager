// Map helpers for the Map block: the tile layers (street map from OpenStreetMap, satellite imagery from Esri), the
// slippy-map maths shared by the public map and the editor's pin picker, and links out to openstreetmap.org.
// Only numbers and the fixed URLs below are ever put into a request.

export const TILE = 256;
export const MIN_ZOOM = 3;

const IMAGERY = 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}';
const LABELS = 'https://server.arcgisonline.com/ArcGIS/rest/services/Reference/World_Boundaries_and_Places/MapServer/tile/{z}/{y}/{x}';
const ESRI_CREDIT = 'Tiles © Esri: Esri, Maxar, Earthstar Geographics and the GIS User Community';

export const MAP_STYLES = {
    street: { label: 'Street map', maxZoom: 19, layers: ['https://tile.openstreetmap.org/{z}/{x}/{y}.png'], credit: '© OpenStreetMap contributors', creditUrl: 'https://www.openstreetmap.org/copyright' },
    satellite: { label: 'Satellite', maxZoom: 18, layers: [IMAGERY], credit: ESRI_CREDIT, creditUrl: 'https://www.esri.com/en-us/legal/terms/full-master-agreement' },
    hybrid: { label: 'Satellite with labels', maxZoom: 18, layers: [IMAGERY, LABELS], credit: ESRI_CREDIT, creditUrl: 'https://www.esri.com/en-us/legal/terms/full-master-agreement' },
};

export const MAP_HEIGHTS = { small: 256, medium: 384, large: 512 };

export const mapStyle = (key) => MAP_STYLES[key] || MAP_STYLES.street;
export const clampZoom = (zoom, styleKey) => Math.max(MIN_ZOOM, Math.min(mapStyle(styleKey).maxZoom, parseInt(zoom, 10) || 16));

const num = (value) => (Number.isFinite(Number(value)) && value !== null && value !== '' ? Number(value) : null);

export function hasCoordinates(block) {
    const lat = num(block.lat);
    const lng = num(block.lng);
    return lat !== null && lng !== null && Math.abs(lat) <= 90 && Math.abs(lng) <= 180;
}

export function project(lat, lng, zoom) {
    const scale = TILE * 2 ** zoom;
    const sin = Math.sin((Math.max(-85.05, Math.min(85.05, lat)) * Math.PI) / 180);
    return { x: ((lng + 180) / 360) * scale, y: (0.5 - Math.log((1 + sin) / (1 - sin)) / (4 * Math.PI)) * scale };
}

export function unproject(x, y, zoom) {
    const scale = TILE * 2 ** zoom;
    const n = Math.PI - (2 * Math.PI * y) / scale;
    return { lat: (180 / Math.PI) * Math.atan(0.5 * (Math.exp(n) - Math.exp(-n))), lng: (x / scale) * 360 - 180 };
}

/** The tiles that cover a width x height view centred on a point, positioned relative to the view's top-left. */
export function tilesFor({ lat, lng, zoom, width, height, style }) {
    const centre = project(lat, lng, zoom);
    const left = centre.x - width / 2;
    const top = centre.y - height / 2;
    const n = 2 ** zoom;
    const tiles = [];

    mapStyle(style).layers.forEach((template, layer) => {
        for (let ty = Math.floor(top / TILE); ty <= Math.floor((top + height) / TILE); ty += 1) {
            if (ty < 0 || ty >= n) continue;
            for (let tx = Math.floor(left / TILE); tx <= Math.floor((left + width) / TILE); tx += 1) {
                const wrapped = ((tx % n) + n) % n;
                tiles.push({
                    key: `${style}/${layer}/${zoom}/${tx}/${ty}`,
                    src: template.replace('{z}', zoom).replace('{x}', wrapped).replace('{y}', ty),
                    x: tx * TILE - left,
                    y: ty * TILE - top,
                });
            }
        }
    });

    return { tiles, left, top };
}

export function osmLargerUrl(block) {
    const zoom = Math.min(19, Math.max(3, parseInt(block.zoom, 10) || 16));
    return `https://www.openstreetmap.org/?mlat=${Number(block.lat).toFixed(6)}&mlon=${Number(block.lng).toFixed(6)}#map=${zoom}/${Number(block.lat).toFixed(6)}/${Number(block.lng).toFixed(6)}`;
}

export function osmDirectionsUrl(block) {
    return `https://www.openstreetmap.org/directions?engine=fossgis_osrm_car&route=%3B${Number(block.lat).toFixed(6)}%2C${Number(block.lng).toFixed(6)}`;
}
