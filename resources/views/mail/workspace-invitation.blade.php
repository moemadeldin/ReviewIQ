@component('mail::message')
# You're invited to join {{ $workspace->name }}

{{ $invitedBy->name }} has invited you to join the workspace **"{{ $workspace->name }}"** on ReviewIQ.

Click the button below to accept this invitation and join the workspace.

@component('mail::button', ['url' => $signedUrl])
Accept Invitation
@endcomponent

If you didn't expect this invitation, you can safely ignore this email.

Thanks,<br>
{{ config('app.name') }}
@endcomponent
