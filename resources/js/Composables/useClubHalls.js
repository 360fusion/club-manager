import { computed } from 'vue';

/**
 * The masonic hall picker on the General tab. `form` is the settings form and `masonicHalls` a getter for the
 * page's list of halls; picking a province drops a hall from another one, and picking a hall fills an empty province.
 */
export function useClubHalls(form, masonicHalls) {
    // Halls offered for the chosen province (all of them until a province is picked).
    const availableHalls = computed(() => masonicHalls().filter(
        (h) => !form.province_id || !h.province_id || h.province_id === Number(form.province_id)
    ));

    const hallsOnly = computed(() => availableHalls.value.filter((h) => h.kind === 'hall'));
    const otherVenues = computed(() => availableHalls.value.filter((h) => h.kind !== 'hall'));

    const hallLabel = (h) => (h.town && !h.name.toLowerCase().includes(h.town.toLowerCase()) ? `${h.name} – ${h.town}` : h.name);

    const selectedHall = computed(() => masonicHalls().find((h) => h.id === Number(form.masonic_hall_id)) || null);

    const selectedHallAddress = computed(() => selectedHall.value
        ? [selectedHall.value.address_line_1, selectedHall.value.address_line_2, selectedHall.value.town, selectedHall.value.county, selectedHall.value.postcode].filter(Boolean).join(', ')
        : '');

    function onProvinceChange() {
        if (selectedHall.value && selectedHall.value.province_id && selectedHall.value.province_id !== Number(form.province_id)) {
            form.masonic_hall_id = '';
        }
    }

    function onHallChange() {
        if (selectedHall.value?.province_id && !form.province_id) {
            form.province_id = selectedHall.value.province_id;
        }
    }

    return { hallsOnly, otherVenues, hallLabel, selectedHallAddress, onProvinceChange, onHallChange };
}
