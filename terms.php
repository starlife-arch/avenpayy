<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" href="/brand/avenpay-icon.svg?v=3" sizes="any">
    <link rel="icon" href="/icon.png?v=3" type="image/png">
    <link rel="apple-touch-icon" href="/icon.png?v=3">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#ffffff">
    <meta name="description" content="Terms governing use of AvenPay API C2B STK Push and B2C payment services.">


    <title>Terms of Service - AvenPay</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        :root {
            --bg: #ffffff;
            --bg-soft: #f8fafc;
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

        h1, h2, h3 { color: var(--text); }

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
                <a href="/privacy">Privacy</a>
                <a href="/register" class="nav-cta">Create account</a>
            </div>
        </div>
    </nav>

    <header class="hero">
        <div class="container">
            <span class="eyebrow">Clear terms, confident payments</span>
            <h1>Terms of <span class="gradient">Service.</span></h1>
            <p>These terms explain the rules for using AvenPay API, including C2B/STK Push collections and B2C payouts.</p>
        </div>
    </header>

    <main class="container layout">
        <aside class="toc">
            <h2>On this page</h2>
            <nav>
                <a href="#about">About the service</a>
                <a href="#account">Accounts and security</a>
                <a href="#c2b">C2B / STK Push</a>
                <a href="#b2c">B2C payouts</a>
                <a href="#fees">Fees and settlement</a>
                <a href="#refunds">Refunds and disputes</a>
                <a href="#acceptable-use">Acceptable use</a>
                <a href="#availability">Availability and providers</a>
                <a href="#privacy">Privacy and data</a>
                <a href="#termination">Suspension and termination</a>
                <a href="#liability">Disclaimers and liability</a>
                <a href="#law">Governing law</a>
            </nav>
        </aside>

        <div>
            <article class="content-card">
                <p class="intro">
                    <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                    By creating an account or using AvenPay API, you agree to these Terms. If you use the Services for a business or organisation, you confirm that you are authorised to accept these Terms on its behalf.
                </p>

                <section class="legal-section" id="about">
                    <h2><span class="section-icon"><i class="fa-solid fa-layer-group"></i></span>About the service</h2>
                    <p>
                        AvenPay API (“AvenPay,” “we,” “us,” or “our”) provides software and API tools for eligible businesses to connect supported payment workflows. These may include customer-to-business (C2B) M-PESA STK Push requests, business-to-customer (B2C) payouts, transaction status checks, callbacks, and dashboard features.
                    </p>
                    <p>
                        AvenPay is a technology platform. Unless we expressly agree otherwise in writing, we are not your bank, mobile network operator, payment scheme, or the destination of customer funds. Payment processing and settlement may depend on third-party payment providers and financial institutions.
                    </p>
                    <p>
                        AvenPay is operated by <strong>[LEGAL BUSINESS NAME]</strong>, at <strong>[BUSINESS ADDRESS]</strong>. Replace these placeholders with your registered business details.
                    </p>
                </section>

                <section class="legal-section" id="account">
                    <h2><span class="section-icon"><i class="fa-solid fa-user-shield"></i></span>Accounts and security</h2>
                    <p>You agree to provide accurate account information and keep it up to date. You are responsible for:</p>
                    <ul>
                        <li>Using the Services only for lawful transactions you are authorised to initiate.</li>
                        <li>Keeping passwords, API keys, and other credentials confidential and secure.</li>
                        <li>Keeping secret API keys on your server—not in browser code, public repositories, or client-side applications.</li>
                        <li>Restricting account access to authorised people and promptly telling us about suspected account compromise or unauthorised activity.</li>
                    </ul>
                    <p>You are responsible for activity through your account, except to the extent applicable law provides otherwise or the activity results from our breach of these Terms.</p>
                </section>

                <section class="legal-section" id="c2b">
                    <h2><span class="section-icon"><i class="fa-solid fa-mobile-screen-button"></i></span>C2B / STK Push collections</h2>
                    <p>C2B means a customer pays your business. When you use an STK Push workflow:</p>
                    <ul>
                        <li>You must be authorised to request the payment and must clearly communicate the amount and purpose to the customer.</li>
                        <li>The customer approves the payment through the applicable M-PESA process. AvenPay does not need or ask for the customer’s M-PESA PIN.</li>
                        <li>An accepted request, sent prompt, or pending response is not proof that payment succeeded.</li>
                        <li>You must check the final transaction result through the callback or status-check process before treating an order as paid or providing goods or services.</li>
                        <li>Successful funds are directed to the till, bank, or Paybill configured for your payment setup. Settlement timing depends on the relevant provider and account arrangements.</li>
                    </ul>
                </section>

                <section class="legal-section" id="b2c">
                    <h2><span class="section-icon"><i class="fa-solid fa-arrow-up-right-from-square"></i></span>B2C payouts</h2>
                    <p>B2C means your business sends money to a recipient. When you request a payout:</p>
                    <ul>
                        <li>You are responsible for verifying the recipient, amount, purpose, and authority before submitting the request.</li>
                        <li>You must meet applicable account and provider requirements and ensure sufficient available funds.</li>
                        <li>A request may be rejected, delayed, reversed, or remain pending due to incorrect details, provider checks, network issues, compliance reviews, or other circumstances.</li>
                        <li>Do not treat an initial API response as proof that the recipient received funds. Check the final transaction result.</li>
                        <li>You are responsible for addressing requests submitted with incorrect recipient or transaction details, subject to applicable law and provider rules.</li>
                    </ul>
                </section>

                <section class="legal-section" id="fees">
                    <h2><span class="section-icon"><i class="fa-solid fa-receipt"></i></span>Fees, limits, and settlement</h2>
                    <p>
                        Applicable AvenPay fees, transaction limits, and settlement arrangements will be shown in your account, on our pricing page, or in a separate agreement. Third-party providers may apply their own fees, limits, eligibility requirements, or settlement rules.
                    </p>
                    <p>
                        Unless we agree otherwise in writing, we do not guarantee a particular settlement time. We will communicate material changes to our fees in accordance with applicable law and any agreement with you.
                    </p>
                </section>

                <section class="legal-section" id="refunds">
                    <h2><span class="section-icon"><i class="fa-solid fa-rotate-left"></i></span>Refunds, reversals, and disputes</h2>
                    <p>
                        You are responsible for your products or services, customer support, refund policy, and customer communications. Transactions may be disputed, reversed, refunded, or investigated under applicable law and payment-provider rules. You agree to provide relevant information and cooperate with reasonable transaction checks.
                    </p>
                </section>

                <section class="legal-section" id="acceptable-use">
                    <h2><span class="section-icon"><i class="fa-solid fa-shield-halved"></i></span>Acceptable use</h2>
                    <p>You must not use the Services to:</p>
                    <ul>
                        <li>Commit fraud, money laundering, terrorist financing, sanctions evasion, or any other unlawful activity.</li>
                        <li>Process transactions for prohibited products or services, or in breach of applicable law or provider rules.</li>
                        <li>Submit unauthorised payment or payout requests, mislead customers, or impersonate another person or business.</li>
                        <li>Interfere with the Services, attempt unauthorised access, upload malicious code, or bypass security and transaction controls.</li>
                        <li>Abuse the API or use it in a way that disrupts the Services or other users.</li>
                    </ul>
                </section>

                <section class="legal-section" id="availability">
                    <h2><span class="section-icon"><i class="fa-solid fa-plug"></i></span>API, callbacks, and third-party providers</h2>
                    <p>
                        You are responsible for securing your callback endpoints, validating callback data using the methods provided, handling repeated notifications safely, and keeping accurate transaction records.
                    </p>
                    <p>
                        The Services may rely on mobile network operators, payment providers, banks, hosting providers, and other third parties. Their systems may be unavailable or delayed, and their services may have separate terms. We do not control third-party systems or guarantee their availability or decisions.
                    </p>
                    <p>
                        We may update, suspend, or discontinue features when reasonably necessary for security, maintenance, legal compliance, or service operation.
                    </p>
                </section>

                <section class="legal-section" id="privacy">
                    <h2><span class="section-icon"><i class="fa-solid fa-lock"></i></span>Privacy and data</h2>
                    <p>
                        Our <a href="/privacy">Privacy Policy</a> explains how we handle personal data. You must provide any notices and obtain any permissions required for personal data you submit, including customer or recipient information.
                    </p>
                    <p>
                        Depending on the data flow, you may decide why and how customer or recipient data is processed, while AvenPay may process it to provide the Services. The parties may need a separate data-processing agreement.
                    </p>
                </section>

                <section class="legal-section" id="termination">
                    <h2><span class="section-icon"><i class="fa-solid fa-circle-stop"></i></span>Suspension and termination</h2>
                    <p>
                        You may stop using the Services and request account closure by contacting us. We may restrict, suspend, or terminate access where reasonably necessary to protect users or the Services, investigate suspected fraud or security issues, comply with law or provider requirements, collect amounts due, or address a material breach.
                    </p>
                    <p>
                        Where legally and practically possible, we will give notice and an opportunity to address the issue. Account closure does not cancel obligations relating to pending transactions, fees, disputes, or required recordkeeping.
                    </p>
                </section>

                <section class="legal-section" id="liability">
                    <h2><span class="section-icon"><i class="fa-solid fa-scale-balanced"></i></span>Disclaimers and liability</h2>
                    <p>
                        Except for commitments expressly stated in these Terms and rights that cannot legally be excluded, the Services are provided on an “as available” basis. We do not guarantee uninterrupted or error-free operation, a particular transaction outcome, or a specific settlement time.
                    </p>
                    <p>
                        To the extent permitted by law, neither party is liable to the other for indirect or consequential loss, loss of anticipated profits, or loss of business opportunity arising from use of the Services. Nothing in these Terms limits liability that cannot lawfully be limited.
                    </p>
                    <p>
                        Subject to the preceding paragraph and applicable law, our total liability arising from the affected Services will not exceed the fees you paid to AvenPay for those Services during the three months before the event giving rise to the claim. This limit does not apply where prohibited by law.
                    </p>
                </section>

                <section class="legal-section" id="changes">
                    <h2><span class="section-icon"><i class="fa-solid fa-pen-to-square"></i></span>Changes to these Terms</h2>
                    <p>
                        We may update these Terms to reflect changes to the Services, our operations, or legal requirements. We will publish the updated version on this page. Where required by law or where a change materially affects your rights, we will provide reasonable notice.
                    </p>
                </section>

                <section class="legal-section" id="law">
                    <h2><span class="section-icon"><i class="fa-solid fa-landmark"></i></span>Governing law</h2>
                    <p>
                        These Terms are governed by the laws of Kenya. Disputes are subject to the jurisdiction of the courts of Kenya, subject to any mandatory legal rights or dispute-resolution requirements.
                    </p>
                </section>

                <div class="contact-card">
                    <h2>Need help with these Terms?</h2>
                    <p>Contact the AvenPay team with questions about your account, integration, or these Terms.</p>
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