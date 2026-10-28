<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import Alert from '@/Components/Ui/Alert.vue';
import Button from '@/Components/Ui/Button.vue';
import Input from '@/Components/Ui/Input.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';

const props = defineProps({
    demoCredentials: { type: Object, default: null },
});

const page = usePage();
const notice = computed(() => page.props.flash?.success || page.props.status || null);

const form = useForm({
    email: props.demoCredentials?.email ?? '',
    password: props.demoCredentials?.password ?? '',
    remember: true,
});

const submit = () => {
    form.post('/login', {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Log in - ClubManager" />

    <!-- The sign-in card is always dark, so the kit components take their dark look inside it. -->
    <div class="dark min-h-screen bg-slate-950 text-slate-100 font-sans flex items-center justify-center p-6 selection:bg-blue-500 selection:text-white">
        <!-- Ambient background glows -->
        <div class="fixed inset-0 overflow-hidden pointer-events-none">
            <div class="absolute -top-40 -left-40 w-96 h-96 bg-blue-600/20 rounded-full blur-3xl"></div>
            <div class="absolute -bottom-40 -right-40 w-96 h-96 bg-blue-600/20 rounded-full blur-3xl"></div>
        </div>

        <div class="relative z-10 w-full max-w-md bg-slate-900/80 border border-slate-800 p-8 rounded-2xl backdrop-blur-xl shadow-2xl space-y-6">
            <div class="text-center space-y-2">
                <div class="w-12 h-12 rounded-2xl bg-blue-600 text-white flex items-center justify-center mx-auto shadow-lg shadow-blue-500/30">
                    <UiIcon name="lock" :size="20" />
                </div>
                <h1 class="text-2xl font-semibold text-white">Log in to ClubManager</h1>
                <p class="text-xs text-slate-400">Access your club portal and executive dashboard</p>
            </div>

            <Alert v-if="notice" variant="success">{{ notice }}</Alert>

            <!-- Demo credentials, only sent on a local install -->
            <Alert v-if="demoCredentials" variant="info" title="Demo admin credentials">
                <div class="font-mono">Email: <strong class="text-white">{{ demoCredentials.email }}</strong></div>
                <div class="font-mono">Password: <strong class="text-white">{{ demoCredentials.password }}</strong></div>
            </Alert>

            <form @submit.prevent="submit" class="space-y-4 text-sm">
                <Input
                    v-model="form.email"
                    label="Email address"
                    type="email"
                    required
                    autocomplete="username"
                    :error="form.errors.email"
                />

                <Input
                    v-model="form.password"
                    label="Password"
                    type="password"
                    required
                    autocomplete="current-password"
                    :error="form.errors.password"
                />

                <div class="flex items-center justify-between text-xs">
                    <label class="flex items-center gap-2 text-slate-400 cursor-pointer">
                        <input type="checkbox" v-model="form.remember" class="w-4 h-4 rounded border-slate-700 accent-blue-600" />
                        Remember me
                    </label>

                    <Link :href="route('password.request')" class="text-blue-400 hover:text-blue-300 font-medium transition-colors">
                        Forgot your password?
                    </Link>
                </div>

                <Button type="submit" size="lg" block :loading="form.processing">
                    Log in
                </Button>
            </form>

            <div class="text-center pt-2">
                <Link href="/" class="inline-flex items-center gap-1.5 text-xs text-slate-400 hover:text-white transition-colors">
                    <UiIcon name="arrow-left" /> Back to platform hub
                </Link>
            </div>
        </div>
    </div>
</template>
