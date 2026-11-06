@extends('layouts.legal')

@section('title', 'Terms of Service')

@section('content')
    <div class="legal-heading">
        <span class="eyebrow">EVENTFLOW · USING THE SERVICE</span>
        <h1>Terms of Service</h1>
        <p>Effective date: {{ config('legal.effective_date') ?: 'Not set — this is a pre-launch draft.' }}</p>
    </div>

    <article class="legal-document">
        <p>These terms describe the rules for using EventFlow, a service for coordinating events, tasks, calendars, and participating teams. Please read them together with our <a href="{{ route('legal.privacy') }}">Privacy Policy</a>. These terms are a draft and require review for the operator and governing law before public launch.</p>

        <h2>1. Operator and eligibility</h2>
        <p>EventFlow is operated by <strong>{{ config('legal.operator_name') ?: 'the operator’s legal name has not yet been provided' }}</strong>. You must be at least {{ config('legal.minimum_age', '13') }} to create an account. If you are a minor under the law where you live, you may use EventFlow only with permission and appropriate involvement of a parent or legal guardian. The current application does not verify age or record guardian consent.</p>

        <h2>2. Your account</h2>
        <p>Provide accurate account information, keep your sign-in credentials private, and promptly notify us if you suspect unauthorized access. You are responsible for activity performed through your account unless applicable law says otherwise. Do not impersonate another person or create accounts to evade a suspension or restriction.</p>

        <h2>3. Your content and shared workspaces</h2>
        <p>You retain your rights to the event, task, and other content you submit. You give EventFlow permission to store, display, and process that content as needed to provide the service and the sharing or integrations you choose. Content shared with an event may be visible to its members and administrators. Do not submit information you do not have permission to share, or highly sensitive personal information that is unnecessary for event coordination.</p>
        <p>You are responsible for the accuracy of schedules and for coordinating with event participants. Account deletion may leave shared event or task history available to collaborators, as explained in the <a href="{{ route('legal.privacy') }}">Privacy Policy</a>.</p>

        <h2>4. Acceptable use</h2>
        <p>You must use EventFlow lawfully and respectfully. Do not use it to harass, threaten, deceive, or unlawfully discriminate; distribute unlawful or harmful content; violate another person’s privacy or intellectual-property rights; interfere with service operation; probe or bypass access controls; upload malware; or access another user’s account or data without permission.</p>

        <h2>5. Google Calendar and other services</h2>
        <p>Connecting Google Calendar is optional. If you connect it, you authorize EventFlow to synchronize your EventFlow events with your primary Google Calendar as described in the Privacy Policy. You may disconnect the integration in EventFlow and manage authorization through your Google Account. Google’s services are governed by Google’s own terms and policies, and EventFlow cannot control their availability or operation.</p>

        <h2>6. Administration and account restrictions</h2>
        <p>Administrators may manage users, moderate workspaces, and review audit records to operate and secure EventFlow. We may restrict or suspend an account when reasonably necessary to protect users, investigate suspected misuse, comply with law, or preserve service integrity. Where appropriate and lawful, we will provide notice and an opportunity to resolve the issue.</p>

        <h2>7. Availability and changes</h2>
        <p>We aim to provide a useful service but do not promise uninterrupted or error-free operation. Features may change, be suspended, or be discontinued. We may update these terms; the effective date above will change when a revised version is published. Continued use after an update is subject to the revised terms to the extent allowed by applicable law.</p>

        <h2>8. Disclaimers and liability</h2>
        <p>EventFlow is a coordination tool, not a substitute for professional advice, emergency services, or an organization’s own safety and event-planning procedures. To the extent allowed by applicable law, the service is provided without warranties that cannot be excluded. Nothing in these terms limits rights or remedies that cannot legally be limited, or excludes liability that applicable law does not allow us to exclude.</p>

        <h2>9. Ending your use</h2>
        <p>You may stop using EventFlow and request account deletion through your account settings. Deletion removes or anonymizes some account information, but shared workspace history and audit records may be retained as described in the <a href="{{ route('legal.privacy') }}">Privacy Policy</a>. We may end or restrict access for the reasons described above, subject to applicable law.</p>

        <h2>10. Governing law and contact</h2>
        <p>These terms are intended to be governed by the laws of <strong>{{ config('legal.governing_jurisdiction') ?: 'the governing jurisdiction has not yet been selected' }}</strong>, without overriding mandatory consumer protections that apply to you. The operator must complete this jurisdiction before public launch. For questions or support, contact <a href="mailto:{{ config('legal.contact_email') }}">{{ config('legal.contact_email') }}</a>.</p>
    </article>
@endsection
