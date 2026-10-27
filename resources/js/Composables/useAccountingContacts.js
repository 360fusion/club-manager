import { computed, ref } from 'vue';

/**
 * The people the accounting screen can name: the club's saved contacts and its members, as one list with the same shape,
 * narrowed by the contacts tab's filter (everyone, people only, members only). `props` are the page's props.
 */
export function useAccountingContacts(props) {
    const contactFilter = ref('all');

    const filteredContacts = computed(() => {
      const all = [];

      (props.contacts || []).forEach(c => {
        all.push({
          id: 'c_' + c.id,
          contact_db_id: c.id,
          kind: 'contact',
          type: c.type,
          name: c.name,
          contact_person: c.contact_person || c.name,
          email: c.email || '—',
          phone: c.phone || '—',
          role: c.role || 'Contact',
          tax_id: c.tax_id || '—',
          address: c.formatted_address || '—',
          address_line_1: c.address_line_1 || '',
          address_line_2: c.address_line_2 || '',
          city: c.city || '',
          postcode: c.postcode || '',
          country: c.country || 'United Kingdom',
          notes: c.notes || '',
        });
      });

      (props.members || []).forEach(m => {
        all.push({
          id: 'm_' + m.id,
          kind: 'member',
          type: 'person',
          name: m.name,
          contact_person: m.name,
          email: m.email || '—',
          phone: '—',
          role: 'Club Member',
          tax_id: '—',
          address: 'Registered Member',
          notes: null,
          user_id: m.id,
        });
      });

      if (contactFilter.value === 'person') {
        return all.filter(item => item.type === 'person');
      }
      if (contactFilter.value === 'member') {
        return all.filter(item => item.kind === 'member');
      }

      return all;
    });

    return { contactFilter, filteredContacts };
}
