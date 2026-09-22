<script setup>
import { ref, onMounted, onBeforeUnmount, nextTick, watch } from 'vue';
import SignaturePadLib from 'signature_pad';

const props = defineProps({
    signerName: { type: String, default: '' },
});

const emit = defineEmits(['update']);

const mode = ref('typed');
const typedName = ref(props.signerName || '');
const canvasEl = ref(null);
let pad = null;

const resizeCanvas = () => {
    if (!canvasEl.value) {
        return;
    }

    const canvas = canvasEl.value;
    const ratio = Math.max(window.devicePixelRatio || 1, 1);
    const data = pad && ! pad.isEmpty() ? pad.toData() : null;

    canvas.width = canvas.offsetWidth * ratio;
    canvas.height = canvas.offsetHeight * ratio;
    canvas.getContext('2d').scale(ratio, ratio);

    if (pad) {
        pad.clear();
        if (data) {
            pad.fromData(data);
        }
    }
};

const initPad = async () => {
    await nextTick();

    if (!canvasEl.value || pad) {
        return;
    }

    pad = new SignaturePadLib(canvasEl.value, { backgroundColor: 'rgb(255,255,255)' });
    pad.addEventListener('endStroke', emitUpdate);
    resizeCanvas();
    window.addEventListener('resize', resizeCanvas);
};

const clearPad = () => {
    pad?.clear();
    emitUpdate();
};

const setMode = async (next) => {
    mode.value = next;

    if (next === 'drawn') {
        await initPad();
    }

    emitUpdate();
};

const emitUpdate = async () => {
    if (mode.value === 'typed') {
        const name = typedName.value.trim();
        emit('update', { method: 'typed', typedName: name, image: null, isValid: name.length > 1 });

        return;
    }

    if (!pad || pad.isEmpty()) {
        emit('update', { method: 'drawn', typedName: null, image: null, isValid: false });

        return;
    }

    const blob = await fetch(pad.toDataURL('image/png')).then((r) => r.blob());
    const file = new File([blob], 'signature.png', { type: 'image/png' });
    emit('update', { method: 'drawn', typedName: null, image: file, isValid: true });
};

watch(typedName, emitUpdate);

onMounted(emitUpdate);

onBeforeUnmount(() => {
    window.removeEventListener('resize', resizeCanvas);
});
</script>

<template>
    <div class="space-y-3">
        <div class="flex gap-2">
            <button
                type="button"
                @click="setMode('typed')"
                :class="['px-3 py-1.5 text-xs font-bold rounded-lg cursor-pointer', mode === 'typed' ? 'bg-blue-600 text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300']"
            >
                Type your name
            </button>
            <button
                type="button"
                @click="setMode('drawn')"
                :class="['px-3 py-1.5 text-xs font-bold rounded-lg cursor-pointer', mode === 'drawn' ? 'bg-blue-600 text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300']"
            >
                Draw
            </button>
        </div>

        <div v-if="mode === 'typed'" class="border border-slate-200 rounded-xl p-4 bg-white">
            <input
                v-model="typedName"
                type="text"
                placeholder="Type your full name"
                class="w-full border-0 border-b border-slate-300 bg-transparent pb-2 text-3xl text-slate-900 focus:outline-none focus:border-blue-500"
                style="font-family: var(--font-signature)"
            />
        </div>

        <div v-else class="border border-slate-200 rounded-xl bg-white overflow-hidden">
            <canvas ref="canvasEl" class="w-full h-40 touch-none cursor-crosshair"></canvas>
            <div class="flex justify-end p-2 bg-slate-50 border-t border-slate-100">
                <button type="button" @click="clearPad" class="text-xs font-bold text-slate-500 hover:text-slate-700 cursor-pointer">Clear</button>
            </div>
        </div>
    </div>
</template>
