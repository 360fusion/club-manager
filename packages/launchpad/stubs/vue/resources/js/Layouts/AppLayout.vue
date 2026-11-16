<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { sameHost } from '../team';
import Avatar from '../Components/Avatar.vue';
import Bell from '../Components/Bell.vue';
import ConsentBanner from '../Components/ConsentBanner.vue';

const page = usePage();
const site = computed(() => page.props.site);
const auth = computed(() => page.props.auth);

const signOut = () => router.post('/logout');
const stopImpersonating = () => router.post('/impersonate/stop');
const switchTo = (slug) => router.post(`/workspaces/${slug}/switch`);
const chooseLanguage = (code) => router.post('/locale', { locale: code });

// a <details> menu only closes when its own button is clicked: close the header's menus on a click elsewhere, Escape, or a new page
const header = ref(null);
const closeMenus = (keep = null) => header.value?.querySelectorAll('details[open]').forEach((menu) => menu !== keep && menu.removeAttribute('open'));
const onClick = (event) => closeMenus(event.target.closest?.('details'));
const onKey = (event) => event.key === 'Escape' && closeMenus();
let stopListening;
onMounted(() => {
    document.addEventListener('click', onClick);
    document.addEventListener('keydown', onKey);
    stopListening = router.on('navigate', () => closeMenus());
});
onUnmounted(() => {
    document.removeEventListener('click', onClick);
    document.removeEventListener('keydown', onKey);
    stopListening?.();
});
</script>

<template>
    <a class="lp-skip" href="#main">{{ $t('Skip to the content') }}</a>
    <div v-if="page.props.impersonating" class="lp-impersonating" role="alert">
        <span>
            <Trans k="You are signed in as :name (:email), as support. What you do here is the customer's, and is logged." :values="{ email: page.props.impersonating.email }">
                <template #name><strong>{{ page.props.impersonating.name }}</strong></template>
            </Trans>
        </span>
        <button type="button" @click="stopImpersonating">{{ $t('Stop and go back to HQ') }}</button>
    </div>
    <header ref="header" class="lp-header">
        <Link class="lp-brand" href="/">{{ site.name }}</Link>
        <nav :aria-label="$t('Main')">
            <component :is="sameHost(item.href) ? Link : 'a'" v-for="item in site.nav" :key="item.href" :href="item.href">{{ item.label }}</component>
            <component :is="sameHost(site.practiceUrl) ? Link : 'a'" v-if="auth && site.practiceUrl" :href="site.practiceUrl">{{ $t('Practise') }}</component>
        </nav>
        <Bell v-if="auth && auth.bell" :bell="auth.bell" />
        <details v-if="auth" class="lp-usermenu">
            <summary>
                <Avatar :url="auth.user.avatarUrl" :name="auth.user.name || auth.user.email" />
                <span class="lp-usermenu__name">{{ auth.user.name || auth.user.email }}</span>
            </summary>
            <div class="lp-usermenu__panel">
                <p class="lp-muted lp-usermenu__email">{{ auth.user.email }}</p>
                <ul>
                    <li><Link href="/account/profile">{{ $t('My account') }}</Link></li>
                    <li><Link href="/account/security">{{ $t('Security') }}</Link></li>
                    <li><Link href="/workspaces">{{ $t('Teams') }}</Link></li>
                    <li v-if="auth.adminUrl"><a :href="auth.adminUrl">{{ $t('HQ') }}</a></li>
                </ul>
                <template v-if="auth.workspaces.length > 1">
                    <p class="lp-usermenu__label">{{ $t('Switch team') }}</p>
                    <ul>
                        <li v-for="w in auth.workspaces" :key="w.slug">
                            <button type="button" :aria-current="w.current ? 'true' : null" @click="switchTo(w.slug)">{{ w.name }}</button>
                        </li>
                    </ul>
                </template>
                <button type="button" @click="signOut">{{ $t('Sign out') }}</button>
            </div>
        </details>
        <nav v-else class="lp-header__actions" :aria-label="$t('Account')">
            <Link href="/login">{{ $t('Sign in') }}</Link>
            <Link class="lp-btn" href="/register">{{ $t('Start free') }}</Link>
        </nav>
    </header>
    <main id="main" class="lp-main lp-main--wide">
        <slot />
    </main>
    <footer class="lp-footer">
        <div class="lp-footer__cols">
            <div class="lp-footer__about">
                <Link class="lp-brand" href="/">{{ site.name }}</Link>
                <p v-if="site.tagline" class="lp-muted">{{ site.tagline }}</p>
            </div>
            <nav v-for="column in site.footer" :key="column.heading" :aria-label="column.heading">
                <h2 class="lp-footer__heading">{{ column.heading }}</h2>
                <ul><li v-for="link in column.links" :key="link.href"><Link :href="link.href">{{ link.label }}</Link></li></ul>
            </nav>
        </div>
        <div class="lp-footer__bar">
            <p class="lp-muted">&copy; {{ new Date().getFullYear() }} {{ site.company ?? site.name }}</p>
            <Link href="/cookie-preferences">{{ $t('Cookie preferences') }}</Link>
            <div v-if="page.props.locales?.length > 1" class="lp-locale" role="group" :aria-label="$t('Language')">
                <button v-for="language in page.props.locales" :key="language.code" type="button" :lang="language.code" :aria-current="language.code === page.props.locale ? 'true' : null" @click="chooseLanguage(language.code)">{{ language.name }}</button>
            </div>
        </div>
    </footer>
    <ConsentBanner v-if="!page.props.consent.decided" />
</template>
