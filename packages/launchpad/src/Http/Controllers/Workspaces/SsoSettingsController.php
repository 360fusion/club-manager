<?php

namespace Launchpad\Http\Controllers\Workspaces;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;
use Launchpad\Billing\Entitlements;
use Launchpad\Http\Ui;
use Launchpad\Models\SsoConnection;
use Launchpad\Sso\DomainVerifier;
use Launchpad\Sso\Oidc;
use Launchpad\Sso\Saml;
use Launchpad\Workspaces\Activity;
use Launchpad\Workspaces\CurrentWorkspace;
use Launchpad\Workspaces\Role;

/**
 * A team's single sign-on settings, for its owner only (it decides how everyone on the domain signs in). On plans with the feature named in
 * config launchpad.sso.feature, when one is named.
 */
class SsoSettingsController extends Controller
{
    public function show(CurrentWorkspace $current): View|Responsable
    {
        $this->owner($current);
        $workspace = $current->workspace;
        $connection = SsoConnection::where('workspace_id', $workspace->id)->first();
        $record = $connection ? DomainVerifier::record($connection) : null;
        $scimUrl = Route::has('scim.users') ? url('scim/v2') : null;
        $roles = collect([Role::Admin, Role::Manager, Role::Member])->map(fn (Role $r) => ['value' => $r->value, 'label' => $r->label()])->all();

        return Ui::render('workspaces.sso', [
            'workspace' => $workspace, 'connection' => $connection, 'record' => $record, 'roles' => $roles, 'callback' => route('sso.callback'),
            'scimUrl' => $scimUrl, 'scimToken' => session('scim_token'), 'samlAvailable' => Saml::available(),
            'saml' => $saml = $connection?->protocol === 'saml' ? ['entityId' => route('sso.saml.metadata', $connection), 'acs' => route('sso.saml.acs', $connection), 'idp' => $connection->issuer] : null,
        ], fn () => [
            'workspace' => ['name' => $workspace->name, 'slug' => $workspace->slug],
            'connection' => $connection ? ['protocol' => $connection->protocol, 'issuer' => $connection->issuer, 'clientId' => $connection->client_id, 'domain' => $connection->domain, 'defaultRole' => $connection->default_role,
                'enforce' => $connection->enforce, 'verified' => $connection->domain_verified_at !== null, 'scimOn' => $connection->scim_token_hash !== null] : null,
            'record' => $record, 'roles' => $roles, 'callback' => route('sso.callback'),
            'scimUrl' => $scimUrl, 'scimToken' => session('scim_token'), 'samlAvailable' => Saml::available(), 'saml' => $saml,
        ]);
    }

    public function update(Request $request, CurrentWorkspace $current): RedirectResponse
    {
        $this->owner($current);
        $workspace = $current->workspace;
        $connection = SsoConnection::where('workspace_id', $workspace->id)->first();

        $request->merge(['domain' => strtolower(trim((string) $request->input('domain')))]);

        $saml = $request->input('protocol') === 'saml' && Saml::available();
        $data = $request->validate([
            'protocol' => ['sometimes', Rule::in(Saml::available() ? ['oidc', 'saml'] : ['oidc'])],
            'issuer' => [Rule::requiredIf(! $saml), 'nullable', 'string', 'url', 'starts_with:https://', 'max:500'],
            'client_id' => [Rule::requiredIf(! $saml), 'nullable', 'string', 'max:255'],
            'client_secret' => [$connection?->protocol === 'oidc' || $saml ? 'nullable' : 'required', 'string', 'max:2000'],
            'metadata_url' => ['nullable', 'string', 'url', 'starts_with:https://', 'max:500'],
            'metadata_xml' => [Rule::requiredIf($saml && ! $request->filled('metadata_url') && $connection?->protocol !== 'saml'), 'nullable', 'string', 'max:1000000'],
            'domain' => ['required', 'string', 'max:255', 'regex:/^(?!-)[a-z0-9-]+(\.[a-z0-9-]+)+$/i', Rule::unique('sso_connections', 'domain')->ignore($connection?->id)->whereNotNull('domain_verified_at')],
            'default_role' => ['required', Rule::in([Role::Admin->value, Role::Manager->value, Role::Member->value])],
            'enforce' => ['sometimes', 'boolean'],
        ]);

        $idp = null;

        try {
            if (! $saml) {
                Oidc::discover($data['issuer']);
            } elseif (filled($data['metadata_url'] ?? null) || filled($data['metadata_xml'] ?? null)) {
                $idp = filled($data['metadata_url'] ?? null) ? Saml::fetchMetadata($data['metadata_url']) : Saml::parseMetadata($data['metadata_xml']);
            }
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages([$saml ? 'metadata_xml' : 'issuer' => __('That provider could not be used: :reason', ['reason' => $e->getMessage()])]);
        }

        $domain = strtolower($data['domain']);
        $connection ??= new SsoConnection(['workspace_id' => $workspace->id]);

        if ($connection->domain !== $domain) {
            // a new domain is proved afresh
            $connection->fill(['domain' => $domain, 'domain_verified_at' => null, 'verification_token' => Str::random(32)]);
        }

        $connection->fill(['protocol' => $saml ? 'saml' : 'oidc', 'default_role' => $data['default_role'], 'enforce' => (bool) ($data['enforce'] ?? false)]);

        if ($saml) {
            // the provider's entity ID stands in the issuer column; the OpenID Connect fields are empty
            $idp && $connection->fill(['issuer' => $idp['issuer'], 'saml_sso_url' => $idp['sso_url'], 'saml_certificate' => $idp['certificate']]);
            $connection->client_id = '';
            $connection->client_secret ??= '';
        } else {
            $connection->fill(['issuer' => rtrim($data['issuer'], '/'), 'client_id' => $data['client_id']]);
            if (filled($data['client_secret'] ?? null)) {
                $connection->client_secret = $data['client_secret'];
            }
        }
        $connection->save();

        Activity::record($workspace, 'sso.saved', __('changed the single sign-on settings'));

        return redirect()->route('workspace.sso', $workspace->slug)->with('status', __('Single sign-on saved.'));
    }

    /** Makes the directory's token, or a new one in place of the old: shown once */
    public function scimToken(CurrentWorkspace $current): RedirectResponse
    {
        $this->owner($current);
        $connection = SsoConnection::where('workspace_id', $current->workspace->id)->whereNotNull('domain_verified_at')->firstOrFail();
        $token = 'scim_'.Str::random(48);

        $connection->forceFill(['scim_token_hash' => hash('sha256', $token), 'scim_token_at' => now()])->save();
        Activity::record($current->workspace, 'sso.scim_token', 'made a new directory provisioning token');

        return back()->with(['status' => __('Copy the token now: it is not shown again.'), 'scim_token' => $token]);
    }

    public function scimOff(CurrentWorkspace $current): RedirectResponse
    {
        $this->owner($current);
        SsoConnection::where('workspace_id', $current->workspace->id)->firstOrFail()->forceFill(['scim_token_hash' => null, 'scim_token_at' => null])->save();
        Activity::record($current->workspace, 'sso.scim_off', 'turned off directory provisioning');

        return back()->with('status', __('Directory provisioning is off. The people it added stay in the team.'));
    }

    public function verify(CurrentWorkspace $current): RedirectResponse
    {
        $this->owner($current);
        $connection = SsoConnection::where('workspace_id', $current->workspace->id)->firstOrFail();

        if (SsoConnection::where('domain', $connection->domain)->whereNotNull('domain_verified_at')->whereKeyNot($connection->id)->exists()) {
            return back()->withErrors(['domain' => __('Another team has already verified this domain.')]);
        }

        if (! DomainVerifier::check($connection)) {
            return back()->withErrors(['domain' => __('The DNS record was not found yet. It can take a while to appear after you add it.')]);
        }

        $connection->update(['domain_verified_at' => now()]);
        Activity::record($current->workspace, 'sso.domain_verified', "verified the domain {$connection->domain} for single sign-on");

        return back()->with('status', __('The domain is verified. People on it can now sign in with single sign-on.'));
    }

    public function destroy(CurrentWorkspace $current): RedirectResponse
    {
        $this->owner($current);
        SsoConnection::where('workspace_id', $current->workspace->id)->delete();
        Activity::record($current->workspace, 'sso.removed', 'turned off single sign-on');

        return redirect()->route('workspace.sso', $current->workspace->slug)->with('status', __('Single sign-on is off.'));
    }

    private function owner(CurrentWorkspace $current): void
    {
        abort_unless($current->role() === Role::Owner, 403);

        if ($feature = config('launchpad.sso.feature')) {
            abort_unless(app(Entitlements::class)->allows($current->workspace, (string) $feature), 403);
        }
    }
}
