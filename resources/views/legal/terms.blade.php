@extends('layouts.base')

@section('title', 'Terms and Conditions')

@php
    $app = config('app.name');
    $op = config('legal.operator');
    $updated = \Carbon\Carbon::parse(config('legal.terms_updated'))->format('j F Y');
    $sections = [
        'acceptance' => 'Acceptance of these Terms',
        'definitions' => 'Definitions',
        'accounts' => 'Eligibility and accounts',
        'roles' => 'Roles and the information you add',
        'pledges' => 'Pledges, contributions and payments',
        'outreach' => 'AI assistant, messages and calls',
        'marketplace' => 'Service provider marketplace',
        'content' => 'Your content',
        'use' => 'Acceptable use',
        'fees' => 'Fees and packages',
        'privacy' => 'Privacy and data protection',
        'third' => 'Third-party services',
        'availability' => 'Availability and changes to the service',
        'termination' => 'Suspension and termination',
        'disclaimers' => 'Disclaimers',
        'liability' => 'Limitation of liability',
        'indemnity' => 'Indemnity',
        'law' => 'Governing law and disputes',
        'changes' => 'Changes to these Terms',
        'contact' => 'Contact us',
    ];
@endphp

@section('body')
<div class="min-h-screen">
    <header class="max-w-3xl mx-auto px-6 pt-10 flex items-center justify-between">
        <a href="{{ route('home') }}" class="font-serif text-xl text-inktext">Ar<span class="text-gold">oo</span>s</a>
        <a href="{{ auth()->check() ? route('weddings.index') : route('login') }}" class="text-sm text-gold hover:underline">{{ auth()->check() ? 'Back to my weddings' : 'Sign in' }}</a>
    </header>

    <main class="max-w-3xl mx-auto px-6 py-12">
        <p class="eyebrow">Legal</p>
        <h1 class="font-serif text-4xl mt-2">Terms and Conditions</h1>
        <p class="text-sm text-muted mt-3">Last updated: {{ $updated }}</p>

        <nav class="card p-5 mt-8" aria-label="Contents">
            <p class="eyebrow mb-3">Contents</p>
            <ol class="text-sm space-y-1 list-decimal list-inside columns-1 sm:columns-2">
                @foreach($sections as $id => $title)
                    <li><a href="#{{ $id }}" class="hover:text-gold">{{ $title }}</a></li>
                @endforeach
            </ol>
        </nav>

        <div class="legal mt-10 space-y-10 text-[15px] leading-7">
            <section id="acceptance">
                <h2>1. {{ $sections['acceptance'] }}</h2>
                <p>{{ $app }} is a wedding planning platform operated by {{ $op }} ("we", "us", "our"). These Terms and Conditions ("Terms") govern your use of the {{ $app }} website, mobile app, wedding websites, messages and related services (together, the "Service").</p>
                <p>By creating an account, using the Service, or clicking a link we send you, you agree to these Terms. If you use the Service on behalf of a business or a wedding committee, you confirm that you are authorised to accept these Terms for them. If you do not agree, do not use the Service.</p>
            </section>

            <section id="definitions">
                <h2>2. {{ $sections['definitions'] }}</h2>
                <ul>
                    <li><strong>Wedding</strong>: a wedding workspace on the Service, including its guests, budget, pledges, website and settings.</li>
                    <li><strong>Organiser</strong>: a planner, couple, committee member or collaborator who manages a Wedding.</li>
                    <li><strong>Guest</strong> or <strong>Pledger</strong>: a person whose details an Organiser adds to a Wedding, including people who pledge or contribute.</li>
                    <li><strong>Provider</strong>: a business or individual offering wedding services through the marketplace.</li>
                    <li><strong>Content</strong>: any text, photo, video, link, document or data added to the Service.</li>
                </ul>
            </section>

            <section id="accounts">
                <h2>3. {{ $sections['accounts'] }}</h2>
                <ul>
                    <li>You must be at least 18 years old and able to enter into a binding contract.</li>
                    <li>The information you give us must be accurate and kept up to date.</li>
                    <li>You are responsible for keeping your password secret and for all activity under your account. Tell us immediately if you suspect unauthorised access.</li>
                    <li>One person may not hold accounts to avoid a suspension or ban.</li>
                </ul>
            </section>

            <section id="roles">
                <h2>4. {{ $sections['roles'] }}</h2>
                <p>The Organisers of a Wedding decide who is invited, which Guests and Pledgers are added, and how they are contacted. For that information, the Organisers are responsible for having a lawful basis to collect and use it, including any consent required by law, and we process it on their instructions.</p>
                <p>Organisers can invite collaborators with different permissions. Anyone you invite can see the parts of the Wedding their role allows. You are responsible for whom you invite and for removing access that is no longer needed.</p>
            </section>

            <section id="pledges">
                <h2>5. {{ $sections['pledges'] }}</h2>
                <ul>
                    <li>A pledge is a voluntary promise made by a Pledger to the Organisers. {{ $app }} records pledges and contributions; it is not a party to them and does not guarantee that any pledge will be paid.</li>
                    <li>We are not a bank, a mobile money operator or a payment service provider, and we do not hold wedding funds.</li>
                    <li>Mobile money and card payments started from the Service are processed by licensed payment partners (for example ClickPesa) under their own terms. Operator and partner fees may apply. A payment is recorded only after the partner confirms it.</li>
                    <li>Payments entered manually (cash, bank, mobile money) are recorded by the Organisers, who are responsible for their accuracy.</li>
                    <li>Refunds, disputes and how contributions are used are matters between the Pledger and the Organisers.</li>
                </ul>
            </section>

            <section id="outreach">
                <h2>6. {{ $sections['outreach'] }}</h2>
                <p>Organisers may use an AI assistant to contact Pledgers by WhatsApp, SMS or phone call, mainly in Kiswahili, to remind them about pledges, record when they expect to pay, and answer questions about the wedding.</p>
                <ul>
                    <li><strong>Consent.</strong> Organisers must only enable outreach for people who agreed to be contacted. Messages are sent only within the calling hours and frequency limits set on the Service.</li>
                    <li><strong>Stopping messages.</strong> A recipient can stop all outreach at any time by replying <strong>STOP</strong>, <strong>ACHA</strong> or <strong>SITAKI</strong>, or by saying so during a call. That number is then never contacted again for that Wedding.</li>
                    <li><strong>Records.</strong> Messages, call transcripts and the information the assistant records (such as payment promises) are stored so the Organisers can follow up.</li>
                    <li><strong>Accuracy.</strong> The assistant is automated and can make mistakes. It never changes payment records on its own. A message saying "I have paid" is checked by the Organisers. Important decisions should be confirmed with a person.</li>
                    <li>Organisers must not use the assistant to harass, threaten or mislead anyone, or to contact people about anything other than their Wedding.</li>
                </ul>
            </section>

            <section id="marketplace">
                <h2>7. {{ $sections['marketplace'] }}</h2>
                <ul>
                    <li>The marketplace helps Organisers find, request quotations from, negotiate with and book Providers. Any agreement for services is made directly between the Organiser and the Provider. {{ $op }} is not a party to it and is not responsible for the services provided.</li>
                    <li>Providers are reviewed before they are listed. Verification confirms the documents and details we were given at that time; it is not an endorsement or a guarantee of quality.</li>
                    <li>Providers see an Organiser's contact and event details only after that Wedding sends them a request, and may use them only to respond to and carry out that request.</li>
                    <li>Accepting a quotation confirms the booking at the agreed price and terms, which are saved for both sides. Payment, deposits and cancellations follow the Provider's stated policy and are arranged with the Provider.</li>
                    <li>Providers must keep prices, availability and portfolio honest and up to date, honour confirmed bookings, and hold any licences their services require.</li>
                    <li>Reviews must reflect a genuine experience with a confirmed booking. We may remove reviews or listings that break these Terms, and we may suspend or ban Providers.</li>
                </ul>
            </section>

            <section id="content">
                <h2>8. {{ $sections['content'] }}</h2>
                <p>You keep ownership of the Content you add. You give us a non-exclusive, worldwide, royalty-free licence to host, store, copy, display and process it only as needed to run and improve the Service, and, for Content you choose to make public (such as a wedding website or a Provider profile), to show it to the public.</p>
                <p>You confirm that you have the rights and permissions needed for your Content, including the consent of people who appear in photos or videos, and that it does not infringe anyone's rights.</p>
            </section>

            <section id="use">
                <h2>9. {{ $sections['use'] }}</h2>
                <p>You must not:</p>
                <ul>
                    <li>break any law, including the laws of the United Republic of Tanzania on electronic communications, cybercrime and data protection;</li>
                    <li>send spam or unsolicited messages, or contact people who have opted out;</li>
                    <li>upload content that is unlawful, defamatory, obscene, hateful, or that contains malware;</li>
                    <li>impersonate anyone, create fake listings or reviews, or misrepresent a business;</li>
                    <li>access other people's Weddings or data without permission, scrape the Service, or try to break its security;</li>
                    <li>use the Service to commit fraud or collect money under false pretences.</li>
                </ul>
            </section>

            <section id="fees">
                <h2>10. {{ $sections['fees'] }}</h2>
                <p>Some features are available through paid packages. The price, what is included and the period are shown before you buy. Prices are in Tanzanian Shillings unless stated otherwise and include applicable taxes where shown. Fees already paid are not refundable except where the law requires or we state otherwise.</p>
            </section>

            <section id="privacy">
                <h2>11. {{ $sections['privacy'] }}</h2>
                <p>We process personal data in line with the Personal Data Protection Act, 2022 of Tanzania and its regulations.</p>
                <ul>
                    <li><strong>What we collect:</strong> account details (name, email, phone), Wedding information added by Organisers (including guest and pledger names, phone numbers, pledges and payments), marketplace activity, messages and call transcripts from outreach, and technical data such as logs.</li>
                    <li><strong>Why:</strong> to provide the Service, send the messages Organisers request, process payments through our partners, keep the Service secure, prevent fraud, and meet legal obligations.</li>
                    <li><strong>Sharing:</strong> only with the people a Wedding grants access to, Providers a Wedding sends requests to, and service partners who help us run the Service (hosting, messaging such as WhatsApp and SMS gateways, payment partners, and AI model providers). Some of these partners may process data outside Tanzania under appropriate safeguards. We do not sell personal data.</li>
                    <li><strong>Retention:</strong> we keep data while the account or Wedding is active and for as long as needed for the purposes above or required by law, then delete or anonymise it. Opt-out records are kept so we never contact that number again.</li>
                    <li><strong>Your rights:</strong> you may ask to access, correct or delete your personal data, object to its use, or withdraw consent, by contacting us or the Organisers of the relevant Wedding. You may also complain to the Personal Data Protection Commission.</li>
                    <li><strong>Security:</strong> we use reasonable technical and organisational measures, including encryption of stored credentials and access controls, but no system is completely secure.</li>
                </ul>
            </section>

            <section id="third">
                <h2>12. {{ $sections['third'] }}</h2>
                <p>The Service connects to services run by others, such as WhatsApp, SMS and voice gateways, payment partners, maps and social media links. Their own terms and privacy policies apply, and we are not responsible for their availability or actions.</p>
            </section>

            <section id="availability">
                <h2>13. {{ $sections['availability'] }}</h2>
                <p>We work to keep the Service available but do not promise it will be uninterrupted or error-free. We may change, add or remove features. Where a change materially reduces a paid feature you have already bought, we will tell you in advance where reasonably possible. Keep your own copies of important information.</p>
            </section>

            <section id="termination">
                <h2>14. {{ $sections['termination'] }}</h2>
                <p>You may stop using the Service at any time. We may suspend or close an account, Wedding or listing, with notice where reasonable, if you break these Terms, if required by law, or to protect users or the Service. Sections that by their nature should continue (including content licences already exercised, disclaimers, limitation of liability, indemnity and governing law) survive termination.</p>
            </section>

            <section id="disclaimers">
                <h2>15. {{ $sections['disclaimers'] }}</h2>
                <p>To the extent permitted by law, the Service is provided "as is" and "as available". We do not guarantee that pledges will be paid, that Providers will perform, or that AI-generated messages, suggestions, budget estimates or match scores are complete or accurate. Nothing in these Terms limits any rights you have under the Fair Competition Act or other consumer laws that cannot be excluded.</p>
            </section>

            <section id="liability">
                <h2>16. {{ $sections['liability'] }}</h2>
                <p>To the extent permitted by law, {{ $op }} is not liable for indirect, incidental or consequential losses, lost profits, lost data, or losses arising from Providers, payment partners, messaging networks or other third parties. Our total liability for any claim relating to the Service is limited to the amount you paid us in the 12 months before the claim. Nothing in these Terms excludes liability for fraud, or for death or personal injury caused by negligence.</p>
            </section>

            <section id="indemnity">
                <h2>17. {{ $sections['indemnity'] }}</h2>
                <p>You agree to compensate {{ $op }} for claims, losses and reasonable costs arising from your Content, your breach of these Terms or the law, or, for Providers, the services you provide.</p>
            </section>

            <section id="law">
                <h2>18. {{ $sections['law'] }}</h2>
                <p>These Terms are governed by the laws of the United Republic of Tanzania. If a dispute arises, please contact us first so we can try to resolve it within 30 days. If it is not resolved, it will be decided by the competent courts of Tanzania.</p>
            </section>

            <section id="changes">
                <h2>19. {{ $sections['changes'] }}</h2>
                <p>We may update these Terms. We will change the "Last updated" date and, for material changes, notify you by email or in the Service before they take effect. Continuing to use the Service after that means you accept the updated Terms.</p>
            </section>

            <section id="contact">
                <h2>20. {{ $sections['contact'] }}</h2>
                <p>Questions about these Terms or your personal data:</p>
                <ul>
                    <li>{{ $op }}</li>
                    @if(config('legal.email'))<li>Email: <a href="mailto:{{ config('legal.email') }}" class="text-gold hover:underline">{{ config('legal.email') }}</a></li>@endif
                    @if(config('legal.phone'))<li>Phone: {{ config('legal.phone') }}</li>@endif
                    @if(config('legal.address'))<li>Address: {{ config('legal.address') }}</li>@endif
                    <li>Or use the <a href="{{ route('home') }}#contact" class="text-gold hover:underline">contact form</a>.</li>
                </ul>
            </section>
        </div>
    </main>

    @include('legal._footer')
</div>

<style>
    .legal h2 { font-family: "Playfair Display", serif; font-size: 1.4rem; margin-bottom: .75rem; color: #2D2820; }
    .legal p + p, .legal p + ul, .legal ul + p { margin-top: .75rem; }
    .legal ul { list-style: disc; padding-left: 1.25rem; }
    .legal li + li { margin-top: .4rem; }
    .legal section { scroll-margin-top: 1.5rem; }
</style>
@endsection
