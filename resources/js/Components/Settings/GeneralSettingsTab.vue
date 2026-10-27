<script setup>
// General tab of the club settings. `form` is the page's form: this tab edits it in place and the page saves it.
import Badge from '@/Components/Ui/Badge.vue';
import Button from '@/Components/Ui/Button.vue';
import Card from '@/Components/Ui/Card.vue';
import Input from '@/Components/Ui/Input.vue';
import Select from '@/Components/Ui/Select.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';

defineProps({
    form: { type: Object, required: true },
    hallLabel: { type: [Object, Array, Function, String, Number, Boolean], required: true },
    hallsOnly: { type: [Object, Array, Function, String, Number, Boolean], required: true },
    onHallChange: { type: [Object, Array, Function, String, Number, Boolean], required: true },
    onProvinceChange: { type: [Object, Array, Function, String, Number, Boolean], required: true },
    openFormulaBuilder: { type: [Object, Array, Function, String, Number, Boolean], required: true },
    otherVenues: { type: [Object, Array, Function, String, Number, Boolean], required: true },
    selectedHallAddress: { type: [Object, Array, Function, String, Number, Boolean], required: true },
    provinces: { type: [Object, Array, Function, String, Number, Boolean], required: false }
});
</script>

<template>
    <div class="space-y-6">

      <!-- Lodge Details Card (Matches Lodge Particulars Specs) -->
      <Card>
        <template #header>
          <h2 class="text-xl font-bold text-slate-900 dark:text-white">Details</h2>
        </template>

        <!-- 2-Column Responsive Layout -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-12 gap-y-1 text-sm text-slate-700 dark:text-slate-200">
          <!-- Left Column -->
          <div>
            <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 py-3.5">
              <span class="font-medium text-slate-900 dark:text-white">Status</span>
              <Badge>{{ form.lodge_status || 'Normal' }}</Badge>
            </div>
            <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 py-3.5">
              <span class="font-medium text-slate-900 dark:text-white">Installed Masters</span>
              <span>{{ form.installed_masters || 'No' }}</span>
            </div>
            <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 py-3.5">
              <span class="font-medium text-slate-900 dark:text-white">Ritual</span>
              <span>{{ form.ritual || '-' }}</span>
            </div>
            <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 py-3.5">
              <span class="font-medium text-slate-900 dark:text-white">Consecration date</span>
              <span>{{ form.consecration_date || '3rd April 1873' }}</span>
            </div>
            <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 py-3.5">
              <span class="font-medium text-slate-900 dark:text-white">Constitution date</span>
              <span>{{ form.constitution_date || '21st October 1872' }}</span>
            </div>
          </div>

          <!-- Right Column -->
          <div>
            <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 py-3.5">
              <span class="font-medium text-slate-900 dark:text-white">Subscription month</span>
              <span>{{ form.subscription_month || 'April' }}</span>
            </div>
            <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 py-3.5">
              <span class="font-medium text-slate-900 dark:text-white">Installation month</span>
              <span>{{ form.installation_month || 'April' }}</span>
            </div>
            <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 py-3.5">
              <span class="font-medium text-slate-900 dark:text-white">Provincial annual return month</span>
              <span>{{ form.provincial_ar_month || 'March' }}</span>
            </div>
            <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 py-3.5">
              <span class="font-medium text-slate-900 dark:text-white">Meeting formula</span>
              <span>{{ form.meeting_formula || '4th Thu. 1 To 11 Ex. 6, 7, 8' }}</span>
            </div>
          </div>
        </div>

        <!-- Edit Particulars Inputs Grid -->
        <div class="border-t border-slate-200 dark:border-slate-800 pt-5 mt-5 space-y-4">
          <h3 class="text-base font-semibold text-slate-900 dark:text-white">Edit lodge particulars and dates</h3>
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <Select
              v-model="form.lodge_status"
              label="Status"
              :options="['Normal', 'Active', 'Suspended', 'Erased', 'Amalgamated'].map((s) => ({ value: s, label: s }))"
            />

            <Select
              v-model="form.installed_masters"
              label="Installed Masters"
              :options="[{ value: 'No', label: 'No' }, { value: 'Yes', label: 'Yes' }]"
            />

            <Input v-model="form.ritual" placeholder="e.g. Emulation / Stability" label="Ritual" />
            <Input v-model="form.consecration_date" placeholder="e.g. 3rd April 1873" label="Consecration date" />
            <Input v-model="form.constitution_date" placeholder="e.g. 21st October 1872" label="Constitution date" />

            <Select
              v-model="form.subscription_month"
              label="Subscription month"
              :options="['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'].map((m) => ({ value: m, label: m }))"
            />

            <Select
              v-model="form.installation_month"
              label="Installation month"
              :options="['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'].map((m) => ({ value: m, label: m }))"
            />

            <Select
              v-model="form.provincial_ar_month"
              label="Provincial annual return month"
              :options="['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'].map((m) => ({ value: m, label: m }))"
            />

            <div class="space-y-1.5">
              <div class="flex items-end gap-2">
                <Input v-model="form.meeting_formula" placeholder="e.g. 4th Thu. 1 To 11 Ex. 6, 7, 8" label="Meeting formula rule" class="flex-1" />
                <Button variant="secondary" class="whitespace-nowrap" @click="openFormulaBuilder">
                  <template #icon><UiIcon name="lightning" :size="14" /></template>
                  Build rule
                </Button>
              </div>
              <p class="text-xs text-slate-500 dark:text-slate-400">Type it directly, or build it from the same occurrence / weekday / months picker the meetings page uses.</p>
            </div>
          </div>
        </div>
      </Card>

      <Card>
        <template #header>
          <h2 class="text-base font-semibold text-slate-900 dark:text-white">Organisation profile</h2>
        </template>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
          <Input v-model="form.name" required label="Club or organisation name" />
          <Input v-model="form.lodge_number" placeholder="e.g. 1418" label="Lodge or club number" />

          <div class="space-y-1.5">
            <label for="settings-province" class="block text-xs font-medium text-slate-700 dark:text-slate-300">Masonic province</label>
            <select id="settings-province" v-model="form.province_id" @change="onProvinceChange" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 transition-colors focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
              <option value="">No province selected</option>
              <option v-for="p in provinces" :key="p.id" :value="p.id">
                {{ p.name }}
              </option>
            </select>
          </div>

          <div class="space-y-1.5">
            <label for="settings-hall" class="block text-xs font-medium text-slate-700 dark:text-slate-300">Masonic hall or meeting place</label>
            <select id="settings-hall" v-model="form.masonic_hall_id" @change="onHallChange" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 transition-colors focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
              <option value="">No Masonic hall selected</option>
              <optgroup label="Masonic halls">
                <option v-for="h in hallsOnly" :key="h.id" :value="h.id">{{ hallLabel(h) }}</option>
              </optgroup>
              <optgroup v-if="otherVenues.length" label="Hotels, clubs and other venues">
                <option v-for="h in otherVenues" :key="h.id" :value="h.id">{{ hallLabel(h) }}</option>
              </optgroup>
            </select>
            <p v-if="selectedHallAddress" class="text-xs text-slate-500 dark:text-slate-400">{{ selectedHallAddress }}</p>
            <p v-else class="text-xs text-slate-500 dark:text-slate-400">Where your lodge or chapter meets, including hotels and clubs. Choose a province first to narrow the list.</p>
            <p v-if="form.errors.masonic_hall_id" class="text-xs text-rose-600 dark:text-rose-400">{{ form.errors.masonic_hall_id }}</p>
          </div>

          <Input v-model="form.tagline" placeholder="e.g. Excellence on the Isis & Thames" label="Tagline or motto" />
          <Input v-model="form.contact_email" type="email" placeholder="admin@club.org" label="Contact email" />
          <Input v-model="form.phone" placeholder="+44 20 7946 0912" label="Support phone number" />

          <div class="sm:col-span-2 border-t border-slate-200 dark:border-slate-800 pt-4 mt-2">
            <h3 class="text-base font-semibold text-slate-900 dark:text-white mb-3">Address</h3>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <Input v-model="form.address_line_1" placeholder="Building name, street number & name" label="Address line 1" class="sm:col-span-2" />
              <Input v-model="form.address_line_2" placeholder="Suite, unit, floor, or department" label="Address line 2 (optional)" class="sm:col-span-2" />
              <Input v-model="form.city" placeholder="e.g. Oxford" label="Town or city" />
              <Input v-model="form.county" placeholder="e.g. Oxfordshire" label="County, state or region" />
              <Input v-model="form.postcode" placeholder="e.g. OX1 1AA" label="Postcode" />
              <Input v-model="form.country" placeholder="e.g. United Kingdom" label="Country" />
            </div>
          </div>
        </div>
      </Card>

      <Card>
        <template #header>
          <h2 class="text-base font-semibold text-slate-900 dark:text-white">Joining and access</h2>
        </template>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
          <Select
            v-model="form.registration_mode"
            label="Registration mode"
            :options="[
              { value: 'open', label: 'Anyone can join' },
              { value: 'invite_only', label: 'Invite only or admin approval' },
            ]"
          />

          <Select
            v-model="form.summons_visibility"
            label="Who outside the lodge can see your meetings"
            hint="Shown on your lodge's directory page. Outsiders never see the sick list, candidates, dues, bank details or the rest of a summons."
            class="sm:col-span-2"
            :options="[
              { value: 'members', label: 'Members only (nothing is shared)' },
              { value: 'approved_visitors', label: 'Visiting brethren I approve' },
              { value: 'public_safe', label: 'Anyone: date, time, venue, dress and dining only' },
            ]"
          />

          <Input v-model="form.member_prefix" placeholder="OUBC-" label="Member number prefix" />

          <Select
            v-model="form.default_role"
            label="Default role for new members"
            :options="[
              { value: 'member', label: 'Member' },
              { value: 'coach', label: 'Coach' },
              { value: 'treasurer', label: 'Treasurer' },
              { value: 'admin', label: 'Admin' },
            ]"
          />

          <Input v-model="form.invite_expiration_days" type="number" min="1" max="365" placeholder="14" label="Invite expiry (days)" />
          <Input v-model="form.invite_reminder_days" type="number" min="0" max="365" placeholder="7" label="Invite reminder (days)" hint="One reminder is emailed to anyone who has not accepted after this many days. Use 0 to switch reminders off." />
          <Input v-model="form.membership_year_start" type="date" label="Membership year start date" />
        </div>
      </Card>
    </div>
</template>
