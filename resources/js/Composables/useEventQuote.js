import { ref } from 'vue';
import { postJson } from '@/Utils/postJson';

// The live price for a booking. `getPayload` returns { attendees, promo_code } for the people entered so far.
// The server prices it with the same engine that charges the booking, so this never disagrees with the bill.
export function useEventQuote(url, getPayload) {
    const quote = ref(null);
    const error = ref('');
    const loading = ref(false);
    let timer = null;
    let latest = 0;

    const refresh = () => {
        clearTimeout(timer);
        timer = setTimeout(async () => {
            const run = ++latest;
            loading.value = true;
            const result = await postJson(url, getPayload());

            if (run !== latest) return; // a newer request is in flight

            loading.value = false;
            error.value = result.ok ? '' : (result.data?.message || 'Could not work out the price.');

            if (result.ok) quote.value = result.data;
        }, 250);
    };

    return { quote, error, loading, refresh };
}
