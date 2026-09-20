<script setup>
import { ref, computed } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import Alert from '@/Components/Ui/Alert.vue';
import Badge from '@/Components/Ui/Badge.vue';
import Button from '@/Components/Ui/Button.vue';
import Card from '@/Components/Ui/Card.vue';
import Input from '@/Components/Ui/Input.vue';
import Modal from '@/Components/Ui/Modal.vue';
import Select from '@/Components/Ui/Select.vue';

const props = defineProps({
    // { clubs, regions, filters }
    directory: { type: Object, required: true },
});

const page = usePage();
const user = computed(() => page.props.auth?.user);

const search = ref(props.directory?.filters.search ?? '');
const region = ref(props.directory?.filters.region ?? '');

const regionOptions = computed(() => [
    { value: '', label: 'All regions' },
    ...(props.directory?.regions ?? []).map((name) => ({ value: name, label: name })),
]);

const applyFilters = () => {
    router.get(
        route('directory.index'),
        { search: search.value || undefined, region: region.value || undefined },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

const modalOpen = ref(false);
const targetClub = ref(null);
const targetType = ref(null);

const form = useForm({
    email: user.value?.email ?? '',
    name: user.value?.name ?? '',
    rank: '',
    home_club_name: '',
    home_club_number: '',
});

const openSubscribe = (club, type) => {
    targetClub.value = club;
    targetType.value = type;
    modalOpen.value = true;
};

const submitSubscription = () => {
    if (!targetClub.value || !targetType.value) return;

    form.post(route('directory.subscribe', { clubSlug: targetClub.value.slug, typeId: targetType.value.id }), {
        preserveScroll: true,
        onSuccess: () => {
            modalOpen.value = false;
        },
    });
};
</script>

<template>
    <div class="space-y-6">
        <Card>
            <form class="grid items-end gap-3 sm:grid-cols-[minmax(0,1fr)_200px_auto]" role="search" @submit.prevent="applyFilters">
                <Input v-model="search" label="Search the directory" placeholder="Lodge name, number (for example 357) or town" />
                <Select v-if="regionOptions.length > 2" v-model="region" label="Region" :options="regionOptions" />
                <div v-else class="hidden sm:block" />
                <Button type="submit">Search</Button>
            </form>
        </Card>

        <template v-if="directory">
            <h2 class="text-sm font-medium text-slate-500 dark:text-slate-400">
                {{ directory.clubs.length }} {{ directory.clubs.length === 1 ? 'lodge or club' : 'lodges and clubs' }}
            </h2>

            <div v-if="directory.clubs.length" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <Card v-for="club in directory.clubs" :key="club.id">
                    <div class="flex h-full flex-col gap-3">
                        <div>
                            <Badge v-if="club.lodge_number" variant="info">No. {{ club.lodge_number }}</Badge>
                            <h3 class="mt-1.5 text-base font-semibold text-slate-900 dark:text-white">{{ club.name }}</h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400">{{ club.town_city }} · {{ club.province_region }}</p>
                        </div>

                        <div class="space-y-2 border-t border-slate-200 pt-3 dark:border-slate-800">
                            <p class="text-xs font-medium text-slate-500 dark:text-slate-400">Subscriptions</p>

                            <ul v-if="club.subscribable_types.length" class="space-y-2">
                                <li
                                    v-for="type in club.subscribable_types"
                                    :key="type.id"
                                    class="flex items-center justify-between gap-2 rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-800 dark:bg-slate-800/50"
                                >
                                    <div class="min-w-0">
                                        <p class="truncate text-xs font-semibold text-slate-900 dark:text-white">{{ type.icon }} {{ type.name }}</p>
                                        <p v-if="type.description" class="truncate text-[11px] text-slate-500 dark:text-slate-400">{{ type.description }}</p>
                                    </div>
                                    <Button size="sm" @click="openSubscribe(club, type)">Subscribe</Button>
                                </li>
                            </ul>
                            <p v-else class="text-xs text-slate-500 dark:text-slate-400">No public subscriptions available.</p>
                        </div>
                    </div>
                </Card>
            </div>

            <Card v-else>
                <p class="py-8 text-center text-sm text-slate-500 dark:text-slate-400">No lodges or clubs match that search.</p>
            </Card>
        </template>

        <Modal :open="modalOpen" :title="`Subscribe to ${targetType?.name ?? ''}`" :subtitle="targetClub?.name" @close="modalOpen = false">
            <form id="directory-subscribe" class="space-y-4" @submit.prevent="submitSubscription">
                <Input v-model="form.name" label="Full name" placeholder="W.Bro John Smith" required :error="form.errors.name" />
                <Input v-model="form.email" type="email" label="Email address" placeholder="john@example.com" required :error="form.errors.email" />
                <div class="grid grid-cols-2 gap-3">
                    <Input v-model="form.rank" label="Masonic rank" placeholder="W.Bro" :error="form.errors.rank" />
                    <Input v-model="form.home_club_number" label="Home lodge number" placeholder="357" :error="form.errors.home_club_number" />
                </div>
                <Input v-model="form.home_club_name" label="Home lodge or club" placeholder="Apollo Lodge" :error="form.errors.home_club_name" />

                <Alert v-if="targetType?.require_approval" variant="warning">Subscriptions to this channel need approval from the club secretary.</Alert>
            </form>

            <template #footer>
                <Button variant="ghost" @click="modalOpen = false">Cancel</Button>
                <Button type="submit" form="directory-subscribe" :loading="form.processing">Subscribe</Button>
            </template>
        </Modal>
    </div>
</template>
