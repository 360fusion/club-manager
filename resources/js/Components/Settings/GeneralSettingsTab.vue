<script setup>
// General tab of the club settings. `form` is the page's form: this tab edits it in place and the page saves it.
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
      <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 sm:p-8 shadow-sm border border-slate-200/80 dark:border-slate-800/80 space-y-6">
        <div class="border-b border-slate-100 dark:border-slate-800 pb-3">
          <h2 class="text-2xl font-extrabold text-slate-900 dark:text-white">Details</h2>
        </div>

        <!-- 2-Column Responsive Layout -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-12 gap-y-1 text-sm font-semibold text-slate-800 dark:text-slate-100">
          <!-- Left Column -->
          <div>
            <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 py-3.5">
              <span class="font-black text-slate-900 dark:text-white">Status</span>
              <span class="px-3 py-0.5 rounded border border-emerald-500 text-emerald-600 dark:text-emerald-400 bg-white dark:bg-slate-900 text-xs font-medium">
                {{ form.lodge_status || 'Normal' }}
              </span>
            </div>
            <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 py-3.5">
              <span class="font-black text-slate-900 dark:text-white">Installed Masters</span>
              <span class="text-slate-800 dark:text-slate-100 font-normal">{{ form.installed_masters || 'No' }}</span>
            </div>
            <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 py-3.5">
              <span class="font-black text-slate-900 dark:text-white">Ritual</span>
              <span class="text-slate-800 dark:text-slate-100 font-normal">{{ form.ritual || '-' }}</span>
            </div>
            <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 py-3.5">
              <span class="font-black text-slate-900 dark:text-white">Consecration Date</span>
              <span class="text-slate-800 dark:text-slate-100 font-normal">{{ form.consecration_date || '3rd April 1873' }}</span>
            </div>
            <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 py-3.5">
              <span class="font-black text-slate-900 dark:text-white">Constitution Date</span>
              <span class="text-slate-800 dark:text-slate-100 font-normal">{{ form.constitution_date || '21st October 1872' }}</span>
            </div>
          </div>

          <!-- Right Column -->
          <div>
            <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 py-3.5">
              <span class="font-black text-slate-900 dark:text-white">Subscription Month</span>
              <span class="text-slate-800 dark:text-slate-100 font-normal">{{ form.subscription_month || 'April' }}</span>
            </div>
            <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 py-3.5">
              <span class="font-black text-slate-900 dark:text-white">Installation Month</span>
              <span class="text-slate-800 dark:text-slate-100 font-normal">{{ form.installation_month || 'April' }}</span>
            </div>
            <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 py-3.5">
              <span class="font-black text-slate-900 dark:text-white">Provincial AR Month</span>
              <span class="text-slate-800 dark:text-slate-100 font-normal">{{ form.provincial_ar_month || 'March' }}</span>
            </div>
            <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 py-3.5">
              <span class="font-black text-slate-900 dark:text-white">Meeting Formula</span>
              <span class="text-slate-800 dark:text-slate-100 font-normal">{{ form.meeting_formula || '4th Thu. 1 To 11 Ex. 6, 7, 8' }}</span>
            </div>
          </div>
        </div>

        <!-- Edit Particulars Inputs Grid -->
        <div class="border-t border-slate-100 dark:border-slate-800 pt-5 space-y-4">
          <h3 class="text-xs font-black uppercase tracking-wider text-slate-500 dark:text-slate-400">Edit Lodge Particulars & Dates</h3>
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 text-xs">
            <div>
              <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Status</label>
              <select v-model="form.lodge_status" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl font-semibold outline-none focus:ring-2 focus:ring-blue-500">
                <option value="Normal">Normal</option>
                <option value="Active">Active</option>
                <option value="Suspended">Suspended</option>
                <option value="Erased">Erased</option>
                <option value="Amalgamated">Amalgamated</option>
              </select>
            </div>

            <div>
              <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Installed Masters</label>
              <select v-model="form.installed_masters" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl font-semibold outline-none focus:ring-2 focus:ring-blue-500">
                <option value="No">No</option>
                <option value="Yes">Yes</option>
              </select>
            </div>

            <div>
              <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Ritual</label>
              <input v-model="form.ritual" type="text" placeholder="e.g. Emulation / Stability" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 font-semibold" />
            </div>

            <div>
              <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Consecration Date</label>
              <input v-model="form.consecration_date" type="text" placeholder="e.g. 3rd April 1873" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 font-semibold" />
            </div>

            <div>
              <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Constitution Date</label>
              <input v-model="form.constitution_date" type="text" placeholder="e.g. 21st October 1872" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 font-semibold" />
            </div>

            <div>
              <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Subscription Month</label>
              <select v-model="form.subscription_month" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl font-semibold outline-none focus:ring-2 focus:ring-blue-500">
                <option v-for="m in ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December']" :key="m" :value="m">{{ m }}</option>
              </select>
            </div>

            <div>
              <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Installation Month</label>
              <select v-model="form.installation_month" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl font-semibold outline-none focus:ring-2 focus:ring-blue-500">
                <option v-for="m in ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December']" :key="m" :value="m">{{ m }}</option>
              </select>
            </div>

            <div>
              <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Provincial AR Month</label>
              <select v-model="form.provincial_ar_month" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl font-semibold outline-none focus:ring-2 focus:ring-blue-500">
                <option v-for="m in ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December']" :key="m" :value="m">{{ m }}</option>
              </select>
            </div>

            <div>
              <label for="meeting-formula" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Meeting Formula Rule</label>
              <div class="flex items-center gap-2">
                <input id="meeting-formula" v-model="form.meeting_formula" type="text" placeholder="e.g. 4th Thu. 1 To 11 Ex. 6, 7, 8" class="flex-1 px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 font-semibold" />
                <button
                  type="button"
                  @click="openFormulaBuilder"
                  class="px-3.5 py-2.5 bg-blue-50 dark:bg-blue-950/40 hover:bg-blue-100 dark:hover:bg-blue-900/40 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800/60 rounded-xl text-xs font-bold whitespace-nowrap transition-colors cursor-pointer"
                >
                  ⚡ Build rule
                </button>
              </div>
              <p class="text-[11px] text-slate-400 mt-1">Type it directly, or build it from the same occurrence / weekday / months picker the meetings page uses.</p>
            </div>
          </div>
        </div>
      </div>

      <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 sm:p-8 shadow-sm border border-slate-200/80 dark:border-slate-800/80 space-y-6">
        <h2 class="text-lg font-bold text-slate-900 dark:text-white border-b border-slate-100 dark:border-slate-800 pb-3">🏢 Organization Profile</h2>
        
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 text-xs">
          <div>
            <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Club / Organization Name</label>
            <input v-model="form.name" type="text" required class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 font-semibold" />
          </div>

          <div>
            <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Lodge / Club Number</label>
            <input v-model="form.lodge_number" type="text" placeholder="e.g. 1418" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 font-semibold" />
          </div>

          <div>
            <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Assigned Masonic Province</label>
            <select v-model="form.province_id" @change="onProvinceChange" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl font-bold outline-none focus:ring-2 focus:ring-blue-500">
              <option value="">No Province Selected</option>
              <option v-for="p in provinces" :key="p.id" :value="p.id">
                🏛️ {{ p.name }}
              </option>
            </select>
          </div>

          <div>
            <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Masonic Hall / Meeting Place</label>
            <select v-model="form.masonic_hall_id" @change="onHallChange" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl font-bold outline-none focus:ring-2 focus:ring-blue-500">
              <option value="">No Masonic Hall Selected</option>
              <optgroup label="Masonic halls">
                <option v-for="h in hallsOnly" :key="h.id" :value="h.id">{{ hallLabel(h) }}</option>
              </optgroup>
              <optgroup v-if="otherVenues.length" label="Hotels, clubs and other venues">
                <option v-for="h in otherVenues" :key="h.id" :value="h.id">{{ hallLabel(h) }}</option>
              </optgroup>
            </select>
            <p v-if="selectedHallAddress" class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">📍 {{ selectedHallAddress }}</p>
            <p v-else class="text-[11px] text-slate-400 mt-1">Where your lodge or chapter meets, including hotels and clubs. Choose a province first to narrow the list.</p>
            <p v-if="form.errors.masonic_hall_id" class="text-[11px] text-rose-600 mt-1">{{ form.errors.masonic_hall_id }}</p>
          </div>

          <div>
            <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Tagline or Motto</label>
            <input v-model="form.tagline" type="text" placeholder="e.g. Excellence on the Isis & Thames" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500" />
          </div>

          <div>
            <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Contact Email</label>
            <input v-model="form.contact_email" type="email" placeholder="admin@club.org" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500" />
          </div>

          <div>
            <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Support Phone Number</label>
            <input v-model="form.phone" type="text" placeholder="+44 20 7946 0912" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500" />
          </div>

          <div class="sm:col-span-2 border-t border-slate-100 dark:border-slate-800 pt-4 mt-2">
            <label class="block font-bold text-slate-900 dark:text-white mb-3 text-xs flex items-center gap-1.5">
              <span>📍</span> Club Headquarters & Physical Address
            </label>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div class="sm:col-span-2">
                <label class="block font-semibold text-slate-600 dark:text-slate-300 mb-1">Address Line 1</label>
                <input v-model="form.address_line_1" type="text" placeholder="Building name, street number & name" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500" />
              </div>

              <div class="sm:col-span-2">
                <label class="block font-semibold text-slate-600 dark:text-slate-300 mb-1">Address Line 2 (Optional)</label>
                <input v-model="form.address_line_2" type="text" placeholder="Suite, unit, floor, or department" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500" />
              </div>

              <div>
                <label class="block font-semibold text-slate-600 dark:text-slate-300 mb-1">Town / City</label>
                <input v-model="form.city" type="text" placeholder="e.g. Oxford" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500" />
              </div>

              <div>
                <label class="block font-semibold text-slate-600 dark:text-slate-300 mb-1">County / State / Region</label>
                <input v-model="form.county" type="text" placeholder="e.g. Oxfordshire" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500" />
              </div>

              <div>
                <label class="block font-semibold text-slate-600 dark:text-slate-300 mb-1">Postcode / ZIP Code</label>
                <input v-model="form.postcode" type="text" placeholder="e.g. OX1 1AA" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 uppercase font-mono" />
              </div>

              <div>
                <label class="block font-semibold text-slate-600 dark:text-slate-300 mb-1">Country</label>
                <input v-model="form.country" type="text" placeholder="e.g. United Kingdom" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500" />
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 sm:p-8 shadow-sm border border-slate-200/80 dark:border-slate-800/80 space-y-6">
        <h2 class="text-lg font-bold text-slate-900 dark:text-white border-b border-slate-100 dark:border-slate-800 pb-3">⚙️ Onboarding & Access Control</h2>
        
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 text-xs">
          <div>
            <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Registration Mode</label>
            <select v-model="form.registration_mode" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 font-bold">
              <option value="open">🔓 Public Open Join</option>
              <option value="invite_only">🔒 Invite-Only / Admin Approval</option>
            </select>
          </div>

          <div class="sm:col-span-2">
            <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Who outside the lodge can see your meetings</label>
            <select v-model="form.summons_visibility" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 font-bold">
              <option value="members">Members only (nothing is shared)</option>
              <option value="approved_visitors">Visiting brethren I approve</option>
              <option value="public_safe">Anyone: date, time, venue, dress and dining only</option>
            </select>
            <p class="text-[11px] text-slate-400 mt-1">Shown on your lodge's directory page. Outsiders never see the sick list, candidates, dues, bank details or the rest of a summons.</p>
          </div>

          <div>
            <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Member Number Prefix</label>
            <input v-model="form.member_prefix" type="text" placeholder="OUBC-" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 font-mono font-bold" />
          </div>

          <div>
            <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Default New Member Role</label>
            <select v-model="form.default_role" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 font-bold">
              <option value="member">Member</option>
              <option value="coach">Coach</option>
              <option value="treasurer">Treasurer</option>
              <option value="admin">Admin</option>
            </select>
          </div>

          <div>
            <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Invite Expiry (Days)</label>
            <input v-model="form.invite_expiration_days" type="number" min="1" max="365" placeholder="14" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 font-bold" />
          </div>

          <div>
            <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Invite Reminder (Days)</label>
            <input v-model="form.invite_reminder_days" type="number" min="0" max="365" placeholder="7" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 font-bold" />
            <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">One reminder is emailed to anyone who has not accepted after this many days. Use 0 to switch reminders off.</p>
          </div>

          <div>
            <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Membership Year Start Date</label>
            <input v-model="form.membership_year_start" type="date" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 font-bold" />
          </div>
        </div>
      </div>
    </div>
</template>
