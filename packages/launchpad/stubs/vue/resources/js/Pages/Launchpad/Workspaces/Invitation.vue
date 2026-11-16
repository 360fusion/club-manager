<script setup>
import { Head, Link, router } from '@inertiajs/vue3';

const props = defineProps({
    invitation: { type: Object, default: null },
    token: { type: String, required: true },
    userEmail: { type: String, default: null },
    rightAccount: Boolean,
});

const accept = () => router.post(`/invitations/${props.token}/accept`);
</script>

<template>
    <Head :title="$t('Invitation')" />
    <div class="lp-narrow">
        <div class="lp-card">
            <template v-if="!invitation">
                <h1>{{ $t('This invitation is no longer valid') }}</h1>
                <p class="lp-muted">{{ $t('It may have expired, been withdrawn or already been used. Ask for a new one.') }}</p>
            </template>
            <template v-else>
                <h1>{{ $t('Join :team', { team: invitation.workspace }) }}</h1>
                <p>
                    <Trans k=":person invited you to join as :role." :values="{ person: invitation.inviter ?? $t('Someone') }">
                        <template #role><strong>{{ invitation.roleLabel }}</strong></template>
                    </Trans>
                </p>
                <div v-if="$page.props.errors.invitation" class="lp-alert lp-alert--error" role="alert">{{ $page.props.errors.invitation }}</div>
                <template v-if="!userEmail">
                    <p>
                        <Trans k="Sign in, or create an account, with :email to accept.">
                            <template #email><strong>{{ invitation.email }}</strong></template>
                        </Trans>
                    </p>
                    <Link class="lp-btn" href="/login">{{ $t('Sign in') }}</Link>
                    <Link class="lp-btn lp-btn--quiet" :href="`/register?email=${encodeURIComponent(invitation.email)}`">{{ $t('Create an account') }}</Link>
                </template>
                <button v-else-if="rightAccount" class="lp-btn" type="button" @click="accept">{{ $t('Accept the invitation') }}</button>
                <p v-else class="lp-alert lp-alert--error" role="alert">{{ $t('You are signed in as :email, but this invitation is for another address. Sign out and use the invited one.', { email: userEmail }) }}</p>
            </template>
        </div>
    </div>
</template>
