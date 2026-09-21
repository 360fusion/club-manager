<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Gateway secrets saved on the billing page were kept as plain text in
     * clubs.settings. Encrypt any that still are.
     */
    public function up(): void
    {
        DB::table('clubs')->whereNotNull('settings')->orderBy('id')->each(function ($club) {
            $settings = json_decode($club->settings, true);

            if (! is_array($settings)) {
                return;
            }

            $changed = false;

            foreach (['stripe_secret_key', 'stripe_webhook_secret'] as $key) {
                if (empty($settings[$key]) || $this->isEncrypted($settings[$key])) {
                    continue;
                }

                $settings[$key] = Crypt::encryptString($settings[$key]);
                $changed = true;
            }

            if ($changed) {
                DB::table('clubs')->where('id', $club->id)->update(['settings' => json_encode($settings)]);
            }
        });
    }

    public function down(): void
    {
        // Secrets stay encrypted.
    }

    private function isEncrypted(string $value): bool
    {
        try {
            Crypt::decryptString($value);

            return true;
        } catch (Throwable) {
            return false;
        }
    }
};
