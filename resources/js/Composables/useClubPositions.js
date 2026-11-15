import { ref } from 'vue';
import { router } from '@inertiajs/vue3';

/**
 * The club positions list on the settings page (`form.member_ranks`): add, reorder, rename and remove, with a
 * warning before removing a position members hold and an undo afterwards. Renaming or removing also updates the
 * members holding it (`props.members`, the page's list, changed in place) on the server straight away.
 * It lives on the page, not in the tab, so the undo notice outlasts a switch to another tab.
 */
export function useClubPositions(form, props) {
    const newRankInput = ref('');

    const addRank = () => {
        const val = newRankInput.value.trim();
        if (val && !form.member_ranks.includes(val)) {
            form.member_ranks.push(val);
            newRankInput.value = '';
        }
    };

    const moveRankUp = (index) => {
        if (index <= 0) return;
        const item = form.member_ranks.splice(index, 1)[0];
        form.member_ranks.splice(index - 1, 0, item);
    };

    const moveRankDown = (index) => {
        if (index >= form.member_ranks.length - 1) return;
        const item = form.member_ranks.splice(index, 1)[0];
        form.member_ranks.splice(index + 1, 0, item);
    };

    const editingRankIndex = ref(null);
    const editingRankValue = ref('');

    // Deletion Warning & Undo State
    const showDeleteWarningModal = ref(false);
    const pendingDeleteRank = ref(null);
    const showUndoToast = ref(false);
    const lastDeletedRank = ref(null);
    const undoTimer = ref(null);

    const updateMemberRank = (userId, newRank) => {
        router.post(
            route('admin.users.rank.update', { clubSlug: props.club.slug, userId }),
            { rank: newRank },
            { preserveScroll: true }
        );
    };

    const getMemberCountForRank = (rankName) => {
        return props.members.filter(m => m.rank === rankName).length;
    };

    const initiateRemoveRank = (index) => {
        const rankToDelete = form.member_ranks[index];
        const affectedMembers = props.members.filter(m => m.rank === rankToDelete);

        pendingDeleteRank.value = {
            index,
            rank: rankToDelete,
            affectedMembers,
            reassignRank: '', // default to unassigned
        };
        showDeleteWarningModal.value = true;
    };

    const executeRemoveRank = (index, rankToDelete, affectedMemberRanks) => {
        form.member_ranks.splice(index, 1);

        lastDeletedRank.value = {
            index,
            rank: rankToDelete,
            affectedMemberRanks,
        };

        showUndoToast.value = true;
        if (undoTimer.value) clearTimeout(undoTimer.value);
        undoTimer.value = setTimeout(() => {
            showUndoToast.value = false;
        }, 10000);
    };

    const confirmDeleteRank = () => {
        if (!pendingDeleteRank.value) return;
        const { index, rank, affectedMembers, reassignRank } = pendingDeleteRank.value;

        const affectedMemberRanks = affectedMembers.map(m => ({
            id: m.id,
            oldRank: m.rank,
        }));

        // Reassign or clear rank for affected members locally
        affectedMembers.forEach(m => {
            m.rank = reassignRank;
            updateMemberRank(m.id, reassignRank);
        });

        executeRemoveRank(index, rank, affectedMemberRanks);
        showDeleteWarningModal.value = false;
        pendingDeleteRank.value = null;
    };

    const undoDeleteRank = () => {
        if (!lastDeletedRank.value) return;
        const { index, rank, affectedMemberRanks } = lastDeletedRank.value;

        if (!form.member_ranks.includes(rank)) {
            form.member_ranks.splice(Math.min(index, form.member_ranks.length), 0, rank);
        }

        if (affectedMemberRanks && affectedMemberRanks.length > 0) {
            affectedMemberRanks.forEach(item => {
                const member = props.members.find(m => m.id === item.id);
                if (member) {
                    member.rank = item.oldRank;
                    updateMemberRank(member.id, item.oldRank);
                }
            });
        }

        showUndoToast.value = false;
        lastDeletedRank.value = null;
    };

    const startEditRank = (index) => {
        editingRankIndex.value = index;
        editingRankValue.value = form.member_ranks[index];
    };

    const saveEditRank = (index) => {
        const oldRank = form.member_ranks[index];
        const trimmed = editingRankValue.value.trim();

        if (trimmed && trimmed !== oldRank) {
            form.member_ranks[index] = trimmed;

            // Update members using oldRank
            props.members.forEach(m => {
                if (m.rank === oldRank) {
                    m.rank = trimmed;
                    updateMemberRank(m.id, trimmed);
                }
            });
        }

        editingRankIndex.value = null;
        editingRankValue.value = '';
    };

    const cancelEditRank = () => {
        editingRankIndex.value = null;
        editingRankValue.value = '';
    };

    return {
        newRankInput, addRank, moveRankUp, moveRankDown,
        editingRankIndex, editingRankValue, startEditRank, saveEditRank, cancelEditRank,
        showDeleteWarningModal, pendingDeleteRank, showUndoToast, lastDeletedRank,
        getMemberCountForRank, initiateRemoveRank, confirmDeleteRank, undoDeleteRank,
    };
}
