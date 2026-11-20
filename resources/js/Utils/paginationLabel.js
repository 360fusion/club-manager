// Laravel's paginator labels carry HTML entities ("&laquo; Previous", "Next &raquo;"); this turns them into plain text.
const ENTITIES = { '&laquo;': '«', '&raquo;': '»', '&lsaquo;': '‹', '&rsaquo;': '›', '&hellip;': '…', '&amp;': '&', '&nbsp;': ' ' };

export const paginationLabel = (label) => String(label ?? '').replace(/&[a-z]+;/g, (entity) => ENTITIES[entity] ?? entity);
