<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

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
    <Head title="Admin Login - ClubManager" />

    <div class="min-h-screen bg-slate-950 text-slate-100 font-sans flex items-center justify-center p-6 selection:bg-blue-500 selection:text-white">
        <!-- Ambient background glows -->
        <div class="fixed inset-0 overflow-hidden pointer-events-none">
            <div class="absolute -top-40 -left-40 w-96 h-96 bg-blue-600/20 rounded-full blur-3xl"></div>
            <div class="absolute -bottom-40 -right-40 w-96 h-96 bg-blue-600/20 rounded-full blur-3xl"></div>
        </div>

        <div class="relative z-10 w-full max-w-md bg-slate-900/80 border border-slate-800 p-8 rounded-3xl backdrop-blur-xl shadow-2xl space-y-6">
            <div class="text-center space-y-2">
                <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-blue-500 to-blue-400 flex items-center justify-center text-2xl font-bold mx-auto shadow-lg shadow-blue-500/30">
                    ⚡
                </div>
                <h1 class="text-2xl font-black text-white">ClubManager Admin Login</h1>
                <p class="text-xs text-slate-400">Access your club portal & executive dashboard</p>
            </div>

            <p v-if="notice" class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-xs text-emerald-200" role="status">{{ notice }}</p>

            <!-- Preset Demo Credentials Callout -->
            <div v-if="demoCredentials" class="p-4 rounded-2xl bg-blue-500/10 border border-blue-500/20 text-xs space-y-1.5">
                <div class="font-bold text-blue-300">🔑 Pre-Configured Admin Credentials:</div>
                <div class="text-slate-300 font-mono">Email: <strong class="text-white">{{ demoCredentials.email }}</strong></div>
                <div class="text-slate-300 font-mono">Password: <strong class="text-white">{{ demoCredentials.password }}</strong></div>
            </div>

            <form @submit.prevent="submit" class="space-y-4 text-sm">
                <div>
                    <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1">Email Address</label>
                    <input 
                        v-model="form.email" 
                        type="email" 
                        required 
                        class="w-full bg-slate-950 border border-slate-800 rounded-xl p-3 text-white placeholder-slate-600 focus:border-blue-500 focus:outline-none"
                    />
                    <div v-if="form.errors.email" class="text-xs text-rose-400 mt-1 font-semibold">{{ form.errors.email }}</div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1">Password</label>
                    <input 
                        v-model="form.password" 
                        type="password" 
                        required 
                        class="w-full bg-slate-950 border border-slate-800 rounded-xl p-3 text-white placeholder-slate-600 focus:border-blue-500 focus:outline-none"
                    />
                    <div v-if="form.errors.password" class="text-xs text-rose-400 mt-1 font-semibold">{{ form.errors.password }}</div>
                </div>

                <div class="flex items-center justify-between text-xs">
                    <label class="flex items-center gap-2 text-slate-400 cursor-pointer">
                        <input type="checkbox" v-model="form.remember" class="w-4 h-4 rounded accent-blue-600" />
                        Remember Me
                    </label>

                    <Link :href="route('password.request')" class="text-blue-400 hover:text-blue-300 font-semibold transition-colors">
                        Forgot / Reset Password?
                    </Link>
                </div>

                <button 
                    type="submit" 
                    :disabled="form.processing" 
                    class="w-full py-3.5 rounded-xl bg-gradient-to-r from-blue-600 to-blue-600 hover:from-blue-500 hover:to-blue-500 text-white font-bold text-sm shadow-xl shadow-blue-600/20 transition-all hover:scale-[1.02]"
                >
                    Log In to Admin Workspace →
                </button>
            </form>

            <div class="text-center pt-2">
                <Link href="/" class="text-xs text-slate-400 hover:text-white transition-colors">
                    ← Back to Platform Hub
                </Link>
            </div>
        </div>
    </div>
</template>
