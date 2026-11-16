import { usePage } from '@inertiajs/vue3';

// A team's address with path after it: /w/acme/billing, or https://acme.example.com/billing once team subdomains are on (the shared
// teamUrl prop). A link to another team, or from the site to a team, is a plain <a>: an Inertia visit cannot cross to another host.
export const teamUrl = (slug, path = '') => (usePage().props.teamUrl ?? '/w/{slug}').replace('{slug}', slug) + (path ? `/${path.replace(/^\//, '')}` : '');

// Whether a link stays on this page's host (and so can be an Inertia <Link>)
export const sameHost = (href) => !/^https?:\/\//i.test(href) || new URL(href).host === window.location.host;
