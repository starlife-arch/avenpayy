<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" href="/brand/avenpay-icon.svg?v=3" sizes="any">
    <link rel="icon" href="/icon.png?v=3" type="image/png">
    <link rel="apple-touch-icon" href="/icon.png?v=3">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#ffffff">
    <meta name="description" content="Learn how AvenPay API handles personal data for C2B STK Push collections and B2C payouts.">


    <title>Privacy Policy - AvenPay</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        :root {
            --bg: #ffffff;
            --panel: #ffffff;
            --line: rgba(15, 23, 42, .16);
            --text: #0f172a;
            --muted: #475569;
            --teal: #0b9a74;
            --violet: #0b9a74;
            --container: 1160px;
        }

        * { box-sizing: border-box; }

        html {
            scroll-behavior: smooth;
            scroll-padding-top: 90px;
        }

        body {
            margin: 0;
            color: var(--text);
            background:
                radial-gradient(ellipse at 85% 8%, rgba(11, 154, 116, .09), transparent 30%),
                radial-gradient(ellipse at 8% 20%, rgba(11, 154, 116, .09), transparent 30%),
                var(--bg);
            font: 15px/1.8 "DM Sans", sans-serif;
        }

        a { color: inherit; text-decoration: none; }
        .container { width: min(var(--container), calc(100% - 40px)); margin-inline: auto; }

        .navbar {
            position: sticky;
            top: 0;
            z-index: 10;
            border-bottom: 1px solid rgba(15, 23, 42, .1);
            background: rgba(255, 255, 255, .88);
            backdrop-filter: blur(18px);
        }

        .nav-inner {
            min-height: 72px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font: 800 16px "Manrope", sans-serif;
            letter-spacing: -.03em;
        }

        .brand img {
            width: 38px;
            height: 38px;
            border-radius: 12px;
            object-fit: cover;
        }

        .gradient {
            color: transparent;
            background: linear-gradient(100deg, var(--teal), #0b7a5c 52%, var(--violet));
            background-clip: text;
            -webkit-background-clip: text;
        }

        .nav-links { display: flex; align-items: center; gap: 20px; }

        .nav-links a {
            color: #334155;
            font-size: 12px;
            font-weight: 600;
        }

        .nav-links a:hover, .nav-links a.active { color: var(--teal); }

        .nav-cta {
            padding: 10px 14px;
            border-radius: 10px;
            color: #06211d !important;
            background: var(--teal);
        }

        .hero {
            padding: 72px 0 42px;
            text-align: center;
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            color: var(--teal);
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .15em;
            text-transform: uppercase;
        }

        .eyebrow::before {
            content: "";
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: var(--teal);
            box-shadow: 0 0 14px rgba(11, 154, 116, .8);
        }

        .hero h1 {
            margin: 16px 0 12px;
            font: 800 clamp(38px, 6vw, 60px)/1.08 "Manrope", sans-serif;
            letter-spacing: -.06em;
        }

        .hero p {
            max-width: 650px;
            margin: 0 auto;
            color: var(--muted);
            font-size: 14px;
        }

        .layout {
            display: grid;
            grid-template-columns: 245px minmax(0, 1fr);
            align-items: start;
            gap: 24px;
            padding-bottom: 80px;
        }

        .toc, .content-card, .contact-card {
            border: 1px solid var(--line);
            border-radius: 18px;
            background: linear-gradient(145deg, #ffffff, #f8fafc);
        }

        .toc {
            position: sticky;
            top: 94px;
            padding: 20px;
        }

        .toc h2 {
            margin: 0 0 12px;
            font: 700 13px "Manrope", sans-serif;
        }

        .toc a {
            display: block;
            padding: 7px 0;
            color: var(--muted);
            font-size: 11px;
        }

        .toc a:hover { color: var(--teal); }

        .content-card { padding: clamp(22px, 4vw, 42px); }

        .intro {
            margin: 0 0 28px;
            padding: 16px 18px;
            border: 1px solid rgba(11, 154, 116, .16);
            border-radius: 13px;
            color: #334155;
            background: rgba(11, 154, 116, .045);
            font-size: 13px;
        }

        .intro i { margin-right: 8px; color: var(--teal); }

        .legal-section {
            padding: 22px 0;
            border-bottom: 1px solid rgba(15, 23, 42, .1);
            scroll-margin-top: 90px;
        }

        .legal-section:last-child { border-bottom: 0; }

        .legal-section h2 {
            display: flex;
            align-items: center;
            gap: 11px;
            margin: 0 0 12px;
            font: 700 18px/1.4 "Manrope", sans-serif;
            letter-spacing: -.025em;
        }

        .section-icon {
            width: 34px;
            height: 34px;
            flex: 0 0 auto;
            display: grid;
            place-items: center;
            border: 1px solid rgba(11, 154, 116, .16);
            border-radius: 10px;
            color: var(--teal);
            background: rgba(11, 154, 116, .08);
            font-size: 13px;
        }

        .legal-section p, .legal-section li {
            color: #475569;
            font-size: 13px;
        }

        .legal-section p { margin: 9px 0; }

        .legal-section ul {
            display: grid;
            gap: 8px;
            margin: 12px 0;
            padding-left: 20px;
        }

        .legal-section li::marker { color: var(--teal); }

        .legal-section a { color: var(--teal); }
        .legal-section a:hover { text-decoration: underline; }

        .contact-card {
            margin-top: 24px;
            padding: 22px;
            background:
                radial-gradient(ellipse at 90% 20%, rgba(11, 154, 116, .1), transparent 40%),
                var(--panel);
        }

        .contact-card h2 {
            margin: 0 0 7px;
            font: 700 17px "Manrope", sans-serif;
        }

        .contact-card p { margin: 0; color: var(--muted); font-size: 12px; }

        .contact-links {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 15px;
        }

        .contact-links a {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 9px 12px;
            border: 1px solid var(--line);
            border-radius: 10px;
            color: #1e293b;
            background: rgba(15, 23, 42, 0.020000000000000004);
            font-size: 11px;
        }

        .contact-links a:hover { border-color: rgba(11, 154, 116, .35); color: var(--teal); }

        footer {
            padding: 22px 0;
            border-top: 1px solid var(--line);
            color: #8290a4;
            background: #070b13;
            font-size: 10px;
        }

        .footer-inner {
            display: flex;
            justify-content: space-between;
            gap: 15px;
        }

        .footer-links { display: flex; gap: 16px; }
        .footer-links a:hover { color: var(--teal); }

        @media (max-width: 800px) {
            .layout { grid-template-columns: 1fr; }
            .toc { position: static; }
            .toc nav { display: grid; grid-template-columns: repeat(2, 1fr); gap: 0 14px; }
        }

        @media (max-width: 560px) {
            .container { width: calc(100% - 28px); }
            .nav-inner { min-height: 64px; }
            .nav-links { gap: 11px; }
            .nav-links a { font-size: 10px; }
            .nav-links .nav-cta { padding: 8px 10px; }
            .brand { font-size: 14px; }
            .brand img { width: 34px; height: 34px; }
            .hero { padding: 52px 0 30px; }
            .toc nav { grid-template-columns: 1fr; }
            .footer-inner { flex-direction: column; }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="container nav-inner">
            <a href="/" class="brand" aria-label="AvenPay API home">
                <img src="/logo.png" alt="">
                <span>AvenPay <span class="gradient">API</span></span>
            </a>
            <div class="nav-links">
                <a href="/">Home</a>
                <a href="/terms">Terms</a>
                <a href="/register" class="nav-cta">Create account</a>
            </div>
        </div>
    </nav>

    <header class="hero">
        <div class="container">
            <span class="eyebrow">Your data, handled with care</span>
            <h1>Privacy <span class="gradient">Policy.</span></h1>
            <p>Here’s how AvenPay API handles personal data when you use our website, dashboard, API, C2B collections, and B2C payouts.</p>
        </div>
    </header>

    <main class="container layout">
        <aside class="toc">
            <h2>On this page</h2>
            <nav>
                <a href="#responsibility">Who is responsible?</a>
                <a href="#data">Data we collect</a>
                <a href="#use">How we use data</a>
                <a href="#payments">Payment information</a>
                <a href="#sharing">When we share data</a>
                <a href="#transfers">Providers and transfers</a>
                <a href="#retention">Data retention</a>
                <a href="#security">Security</a>
                <a href="#rights">Your rights</a>
                <a href="#cookies">Cookies</a>
                <a href="#children">Children</a>
                <a href="#changes">Policy changes</a>
            </nav>
        </aside>

        <div>
            <article class="content-card">
                <p class="intro">
                    <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                    This policy describes AvenPay API’s data practices. It should be read together with our <a href="/terms" style="color:#0b9a74">Terms of Service</a>. Please do not send us M-PESA PINs, passwords, or secret API credentials.
                </p>

                <section class="legal-section" id="responsibility">
                    <h2><span class="section-icon"><i class="fa-solid fa-building"></i></span>Who is responsible for personal data?</h2>
                    <p>
                        AvenPay API (“AvenPay,” “we,” “us,” or “our”) is operated by <strong>[LEGAL BUSINESS NAME]</strong>, located at <strong>[BUSINESS ADDRESS]</strong>. For privacy questions, contact <a href="mailto:support@avenpay.co.ke">support@avenpay.co.ke</a>.
                    </p>
                    <p>
                        Depending on the data flow, AvenPay may process information for its own account, security, support, and service operations, or process transaction information on behalf of a business customer. The business customer may decide why its customers’ or recipients’ information is collected and how it is used. The parties’ responsibilities may be further described in a separate data-processing agreement.
                    </p>
                </section>

                <section class="legal-section" id="data">
                    <h2><span class="section-icon"><i class="fa-solid fa-database"></i></span>Personal data we may collect</h2>
                    <p>Depending on how you use the Services, we may collect:</p>
                    <ul>
                        <li><strong>Account and business details:</strong> name, business name, email address, phone number, account identifiers, and information used to configure or verify an account.</li>
                        <li><strong>Transaction information:</strong> transaction references, amounts, currency, payment status, timestamps, service type, and relevant payment-provider responses.</li>
                        <li><strong>Customer or recipient details:</strong> information submitted by a business to request or reconcile a payment, which may include a mobile phone number and transaction details required for the workflow.</li>
                        <li><strong>Technical and security information:</strong> IP address, browser or device details, access logs, API activity, error reports, and security events.</li>
                        <li><strong>Support communications:</strong> messages and information you provide when contacting us.</li>
                        <li><strong>Cookies and similar technologies:</strong> information used for necessary site functionality, security, and—if enabled—analytics or preferences.</li>
                    </ul>
                    <p>We do not need your customer’s M-PESA PIN to initiate an STK Push. Never send PINs or secret credentials to support.</p>
                </section>

                <section class="legal-section" id="use">
                    <h2><span class="section-icon"><i class="fa-solid fa-gears"></i></span>How we use personal data</h2>
                    <p>We may use personal data to:</p>
                    <ul>
                        <li>Create and administer accounts and provide the Services.</li>
                        <li>Initiate and manage supported C2B/STK Push requests and B2C payout requests.</li>
                        <li>Provide transaction status, callbacks, records, and customer support.</li>
                        <li>Secure accounts and systems, investigate errors, and detect suspected fraud or misuse.</li>
                        <li>Meet legal, regulatory, accounting, and payment-provider requirements.</li>
                        <li>Send service notices, security alerts, and important updates.</li>
                        <li>Maintain and improve the Services using appropriate safeguards.</li>
                    </ul>
                    <p>
                        We process personal data for identified purposes and where an appropriate legal basis applies. Depending on the activity, this may include providing a requested service, meeting a legal obligation, pursuing legitimate interests where permitted, or relying on consent where required.
                    </p>
                </section>

                <section class="legal-section" id="payments">
                    <h2><span class="section-icon"><i class="fa-solid fa-money-bill-transfer"></i></span>C2B collections and B2C payouts</h2>
                    <p>
                        For C2B/STK Push, a business may submit information needed to request a customer payment and receive transaction updates. The customer approves the payment through the applicable payment provider’s process.
                    </p>
                    <p>
                        For B2C, a business may submit information needed to request a payout to a recipient. We may process transaction references, amounts, status, customer or recipient details, and provider responses to operate the requested workflow and maintain transaction records.
                    </p>
                    <p>
                        Payment processing and settlement depend on the relevant provider’s systems, rules, and arrangements. Independent payment providers may handle information under their own privacy notices.
                    </p>
                </section>

                <section class="legal-section" id="sharing">
                    <h2><span class="section-icon"><i class="fa-solid fa-share-nodes"></i></span>When we share personal data</h2>
                    <p>We may share relevant information with:</p>
                    <ul>
                        <li><strong>Payment providers and financial institutions</strong> where needed to process, verify, settle, investigate, or support a transaction.</li>
                        <li><strong>Service providers</strong> that help us operate hosting, security, communications, and technical support.</li>
                        <li><strong>Your business or organisation</strong> where you are connected with its account or transaction.</li>
                        <li><strong>Authorities or other parties</strong> where required by law or valid legal process, or where necessary to protect rights, safety, and security.</li>
                        <li><strong>Business transfer parties</strong> if we are involved in a merger, restructuring, or transfer, subject to appropriate safeguards and applicable law.</li>
                    </ul>
                    <p>We do not sell personal data.</p>
                </section>

                <section class="legal-section" id="transfers">
                    <h2><span class="section-icon"><i class="fa-solid fa-cloud"></i></span>Service providers and international transfers</h2>
                    <p>
                        Our service providers may include payment providers, hosting or cloud services, email or customer-support services, and security providers. Complete this list with the providers you actually use:
                    </p>
                    <ul>
                        <li><strong>Payment provider:</strong> [ADD ACTUAL PROVIDER]</li>
                        <li><strong>Hosting or cloud provider:</strong> [ADD ACTUAL PROVIDER]</li>
                        <li><strong>Email or support provider:</strong> [ADD ACTUAL PROVIDER]</li>
                    </ul>
                    <p>
                        If personal data is transferred outside Kenya, we will use the safeguards and legal basis required by applicable law. Contact us for information about relevant safeguards, subject to lawful limits.
                    </p>
                </section>

                <section class="legal-section" id="retention">
                    <h2><span class="section-icon"><i class="fa-solid fa-box-archive"></i></span>How long we keep data</h2>
                    <p>
                        We keep personal data only for as long as reasonably necessary for the purposes described in this policy, including providing the Services, maintaining transaction records, resolving disputes, preventing fraud, and meeting legal or provider requirements.
                    </p>
                    <p>
                        Add your actual retention periods or criteria here. For example, specify how long you retain account records, transaction logs, support messages, and security logs: <strong>[ADD YOUR RETENTION PERIODS OR CRITERIA]</strong>. When information is no longer required, we will delete it, anonymise it, or securely restrict access, subject to applicable law.
                    </p>
                </section>

                <section class="legal-section" id="security">
                    <h2><span class="section-icon"><i class="fa-solid fa-shield-halved"></i></span>How we protect data</h2>
                    <p>
                        We use technical and organisational measures designed to protect personal data against unauthorised access, loss, misuse, or alteration. No online service or transmission method can be guaranteed completely secure.
                    </p>
                    <p>
                        You are responsible for protecting your account credentials and keeping API secrets on your server, not in public or client-side code.
                    </p>
                </section>

                <section class="legal-section" id="rights">
                    <h2><span class="section-icon"><i class="fa-solid fa-user-check"></i></span>Your privacy rights and choices</h2>
                    <p>
                        Subject to applicable law, you may have the right to request access to, correction of, or deletion of personal data; object to or request restriction of certain processing; withdraw consent where processing relies on consent; or complain to the relevant data protection authority.
                    </p>
                    <p>
                        To make a privacy request, email <a href="mailto:support@avenpay.co.ke">support@avenpay.co.ke</a>. We may need to verify your identity. If a business customer submitted your information and controls the relevant processing, we may direct you to contact that business. Some information may need to be retained where required by law or for legitimate transaction, security, or dispute purposes.
                    </p>
                </section>

                <section class="legal-section" id="cookies">
                    <h2><span class="section-icon"><i class="fa-solid fa-cookie-bite"></i></span>Cookies and similar technologies</h2>
                    <p>
                        Our website may use cookies needed for functionality, security, or remembering preferences. If we use analytics or advertising cookies, add details about those cookies, the providers, and any controls available: <strong>[ADD YOUR ACTUAL COOKIE AND ANALYTICS DETAILS]</strong>.
                    </p>
                    <p>
                        You can manage cookies through your browser settings. Disabling essential cookies may affect how parts of the website work.
                    </p>
                </section>

                <section class="legal-section" id="children">
                    <h2><span class="section-icon"><i class="fa-solid fa-child"></i></span>Children’s information</h2>
                    <p>
                        The Services are intended for businesses and their authorised users, not for children to create accounts. If you believe a child’s personal data has been provided to us, contact us so we can assess and address the request under applicable law.
                    </p>
                </section>

                <section class="legal-section" id="changes">
                    <h2><span class="section-icon"><i class="fa-solid fa-pen-to-square"></i></span>Changes to this policy</h2>
                    <p>
                        We may update this Privacy Policy to reflect changes to our Services, data practices, or legal requirements. We will publish the updated policy on this page and provide additional notice or request consent where required.
                    </p>
                </section>

                <div class="contact-card">
                    <h2>Questions or privacy requests?</h2>
                    <p>Contact us about personal data, privacy requests, or how AvenPay API handles payment information.</p>
                    <div class="contact-links">
                        <a href="mailto:support@avenpay.co.ke"><i class="fa-solid fa-envelope"></i> support@avenpay.co.ke</a>
                        <a href="tel:+254748600179"><i class="fa-solid fa-phone"></i> +254 748 600 179</a>
                        <a href="https://api.whatsapp.com/send?phone=254748600179" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i> WhatsApp</a>
                    </div>
                </div>
            </article>
        </div>
    </main>

    <footer>
        <div class="container footer-inner">
            <span>&copy; <?= date('Y') ?> AvenPay API. All rights reserved.</span>
            <div class="footer-links">
                <a href="/privacy">Privacy</a>
                <a href="/terms">Terms</a>
                <a href="/">Home</a>
            </div>
        </div>
    </footer>
</body>
</html>