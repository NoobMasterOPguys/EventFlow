@extends('layouts.legal')

@section('title', 'Privacy Policy')

@section('content')
    <div class="legal-heading">
        <span class="eyebrow">EVENTFLOW · YOUR INFORMATION</span>
        <h1>Privacy Policy</h1>
        <p>Effective date: {{ config('legal.effective_date') ?: 'Not set — this is a pre-launch draft.' }}</p>
    </div>

    <article class="legal-document">
        <p>This policy explains what EventFlow collects, how the service uses and shares it, and the choices available to you. It describes the current application; production hosting and legal operator details must be completed before public launch.</p>

        <h2>1. Who is responsible</h2>
        <p>EventFlow is operated by <strong>{{ config('legal.operator_name') ?: 'the operator’s legal name has not yet been provided' }}</strong> (“EventFlow,” “we,” or “us”). For privacy requests or questions, contact <a href="mailto:{{ config('legal.contact_email') }}">{{ config('legal.contact_email') }}</a>.</p>

        <h2>2. Information we handle</h2>
        <ul>
            <li><strong>Account information:</strong> name, email address, password hash, email-verification state, account role and status, notification preferences, and account timestamps.</li>
            <li><strong>Workspace content:</strong> event titles, descriptions, categories, locations, dates and status; task details, due dates, priorities, status and assignments; and event membership and roles.</li>
            <li><strong>Notifications:</strong> in-app notices can include event or task titles, due dates, announcement content, and read status.</li>
            <li><strong>Security and activity records:</strong> session identifiers and, depending on deployment settings, IP address, browser user-agent, last activity, login information, and audit records of actions taken in the service.</li>
            <li><strong>Google Calendar connection data (optional):</strong> if you connect Google Calendar, OAuth access and refresh tokens and Google event identifiers are stored to synchronize EventFlow events. Tokens are encrypted by the application. We do not request access until you choose to connect.</li>
        </ul>

        <h2>3. How information is used</h2>
        <p>We use this information to create and administer accounts; provide event, task, calendar, and notification features; authenticate users; maintain security; investigate misuse; support users; and keep operational and audit records. Passwords are stored as password hashes rather than readable passwords.</p>

        <h2>4. Google Calendar</h2>
        <p>If you authorize the integration, EventFlow uses Google’s Calendar Events permission to create and update EventFlow events in your primary Google Calendar and remove the corresponding synchronized entries when the related EventFlow event is removed. The current integration does not import unrelated calendar entries. EventFlow stores synchronization identifiers and encrypted OAuth tokens so it can perform those requested operations.</p>
        <p>You can disconnect Google Calendar in your EventFlow account. Disconnecting removes the tokens stored by EventFlow and stops future synchronization; it does not itself revoke EventFlow’s authorization in your Google account or remove calendar entries already synchronized. You can also review or revoke third-party access in your Google Account. Google handles information under its own policies, including its <a href="https://policies.google.com/privacy" target="_blank" rel="noopener noreferrer">Privacy Policy</a>.</p>

        <h2>5. Who can see information</h2>
        <p>People who belong to an event can see the event and relevant task information shared with that event. EventFlow administrators may access account, workspace, and audit information to operate, secure, and moderate the service. We do not expose passwords or Google OAuth tokens to other users through the application.</p>

        <h2>6. Service providers and transfers</h2>
        <p>EventFlow must use hosting, database, and email-delivery infrastructure to operate. The production providers and their processing locations have not yet been selected or configured. Before public launch, the operator must identify those providers and update this policy. Google is an independent provider when you choose its calendar integration. Provider processing or storage may occur in countries other than yours, subject to the applicable safeguards and laws.</p>

        <h2>7. Cookies and similar storage</h2>
        <p>The application uses essential session and security mechanisms, including a session cookie, to keep sign-in and request-protection features working. Session duration and storage are controlled by the deployment configuration. The current application code does not include an advertising or analytics feature.</p>

        <h2>8. Retention and account deletion</h2>
        <p>We keep information while needed to provide the service, protect accounts, maintain audit history, resolve disputes, or meet legal obligations. The current application does not define a universal retention period for all records. When you delete your account, the account is deactivated and identifying account details are anonymized, stored Google credentials and related mappings are removed, and active sessions are invalidated. Shared event and task history may be preserved for other participants, and audit records may remain for security and accountability. Deletion therefore does not necessarily erase every record immediately. Production backup retention depends on the infrastructure provider and must be documented before launch.</p>

        <h2>9. Your choices and requests</h2>
        <p>You can update account details and notification preferences, disconnect Google Calendar, and request account deletion through the service. To ask about or request access, correction, export, or deletion of personal information, email <a href="mailto:{{ config('legal.contact_email') }}">{{ config('legal.contact_email') }}</a>. We may need to verify your identity and may retain information where the law permits or requires it. Available rights depend on the law that applies to you.</p>

        <h2>10. Security</h2>
        <p>EventFlow uses password hashing and encrypts stored Google Calendar tokens. No online service or storage method can be guaranteed completely secure. The operator must configure production encryption keys, HTTPS, access controls, backups, and service providers appropriately; do not send highly sensitive information through event descriptions or task notes.</p>

        <h2>11. Children and age</h2>
        <p>EventFlow is intended for people aged {{ config('legal.minimum_age', '13') }} or older. Users who have not reached the age of majority where they live may use the service only with permission and appropriate involvement of a parent or legal guardian. The current application does not verify a user’s age or collect parental consent; organizations and guardians should supervise use where required.</p>

        <h2>12. Changes and contact</h2>
        <p>We may update this policy as the service changes. We will update the effective date when a revised version is published. Contact <a href="mailto:{{ config('legal.contact_email') }}">{{ config('legal.contact_email') }}</a> with questions or privacy requests.</p>
    </article>
@endsection
