<?php

namespace App\Http\Requests\PageAdmin;

use App\Http\Requests\ClubRequest;
use App\Models\SiteDeployTarget;
use Illuminate\Validation\Rule;

/**
 * Where a club's static website is pushed: an FTP(S) server or a GitHub repository. On an edit the password or token
 * may be left empty to keep the saved one.
 */
class SaveSiteDeployTargetRequest extends ClubRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $isNew = ! $this->filled('id');

        return [
            'id' => 'nullable|integer',
            'type' => ['required', Rule::in(SiteDeployTarget::TYPES)],
            'name' => 'required|string|max:120',

            'host' => ['exclude_unless:type,ftps', 'required', 'string', 'max:255', 'regex:/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/i'],
            'port' => 'exclude_unless:type,ftps|nullable|integer|between:1,65535',
            'username' => 'exclude_unless:type,ftps|required|string|max:255',
            'password' => ['exclude_unless:type,ftps', $isNew ? 'required' : 'nullable', 'string', 'max:255'],
            'path' => ['exclude_unless:type,ftps', 'nullable', 'string', 'max:255', 'not_regex:/\.\./', 'regex:/^[A-Za-z0-9_\-.\/ ]*$/'],
            'insecure' => 'exclude_unless:type,ftps|boolean',

            'repository' => ['exclude_unless:type,github', 'required', 'string', 'max:200', 'regex:/^[A-Za-z0-9_.-]+\/[A-Za-z0-9_.-]+$/'],
            'branch' => ['exclude_unless:type,github', 'required', 'string', 'max:100', 'not_regex:/\.\./', 'regex:/^[A-Za-z0-9._\/-]+$/'],
            'token' => ['exclude_unless:type,github', $isNew ? 'required' : 'nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'host.regex' => 'Enter the server name only, for example ftp.yourlodge.org.uk (no ftp:// in front).',
            'repository.regex' => 'Enter the repository as owner/name, for example yourlodge/website.',
        ];
    }
}
