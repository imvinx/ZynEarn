<?php
require_once __DIR__ . '/../includes/auth_middleware.php';
$pageTitle = 'Privacy Policy - ZynEarn';
$pageDescription = 'ZynEarn Privacy Policy - How we collect, use, and protect your personal information.';
$bodyClass = 'legal-page';
include __DIR__ . '/../includes/header.php';
?>

<main class="legal-content">
    <div class="legal-hero">
        <div class="container">
            <h1>Privacy Policy</h1>
            <p class="legal-subtitle">Last updated: May 17, 2026</p>
        </div>
    </div>

    <div class="container">
        <div class="legal-body">
            <section>
                <h2>1. Introduction</h2>
                <p>ZynEarn ("we," "our," or "us") is committed to protecting your privacy. This Privacy Policy explains how we collect, use, disclose, and safeguard your information when you visit our website and use our services.</p>
                <p>By using ZynEarn, you agree to the collection and use of information in accordance with this policy.</p>
            </section>

            <section>
                <h2>2. Information We Collect</h2>
                <h3>Personal Information</h3>
                <p>We may collect personally identifiable information such as:</p>
                <ul>
                    <li>Email address</li>
                    <li>Username</li>
                    <li>Profile information (avatar, display name)</li>
                    <li>Payment account details (PayPal email, crypto wallet addresses)</li>
                    <li>Social media profile data (when using OAuth login)</li>
                </ul>

                <h3>Non-Personal Information</h3>
                <p>We automatically collect certain information when you visit our platform:</p>
                <ul>
                    <li>IP address and location data</li>
                    <li>Browser type and version</li>
                    <li>Device type and operating system</li>
                    <li>Pages visited and time spent</li>
                    <li>Referring URL</li>
                    <li>Cookies and similar tracking technologies</li>
                </ul>
            </section>

            <section>
                <h2>3. How We Use Your Information</h2>
                <p>We use the collected information for the following purposes:</p>
                <ul>
                    <li>To create and manage your account</li>
                    <li>To process your earnings and withdrawals</li>
                    <li>To provide customer support</li>
                    <li>To send administrative communications</li>
                    <li>To improve our platform and user experience</li>
                    <li>To detect and prevent fraud or abuse</li>
                    <li>To comply with legal obligations</li>
                </ul>
            </section>

            <section>
                <h2>4. Data Storage and Security</h2>
                <p>We implement industry-standard security measures to protect your data:</p>
                <ul>
                    <li>SSL/TLS encryption for all data transmission</li>
                    <li>Encrypted password storage using Argon2id with pepper</li>
                    <li>Regular security audits and monitoring</li>
                    <li>Access controls and authentication requirements</li>
                    <li>Secure servers with firewall protection</li>
                </ul>
                <p>While we strive to protect your personal information, no method of transmission or storage is 100% secure.</p>
            </section>

            <section>
                <h2>5. Cookies</h2>
                <p>We use cookies and similar tracking technologies to enhance your experience. Types of cookies we use:</p>
                <ul>
                    <li><strong>Essential Cookies:</strong> Required for platform functionality, including authentication and security</li>
                    <li><strong>Preference Cookies:</strong> Remember your settings and preferences</li>
                    <li><strong>Analytics Cookies:</strong> Help us understand how you use our platform</li>
                </ul>
                <p>You can control cookie preferences through your browser settings. Disabling certain cookies may affect platform functionality.</p>
            </section>

            <section>
                <h2>6. Third-Party Services</h2>
                <p>We may share your information with trusted third parties who assist us in operating our platform:</p>
                <ul>
                    <li><strong>Payment Processors:</strong> PayPal, Stripe, Razorpay, Binance Pay, FaucetPay</li>
                    <li><strong>Analytics:</strong> Google Analytics (if enabled)</li>
                    <li><strong>Authentication:</strong> Google, Discord, Telegram (when you use OAuth)</li>
                </ul>
                <p>These third parties have their own privacy policies governing the use of your information.</p>
            </section>

            <section>
                <h2>7. Data Retention</h2>
                <p>We retain your personal information for as long as your account is active or as needed to provide services. You may request deletion of your account and associated data by contacting support.</p>
                <p>Upon account deletion, we will remove or anonymize your personal information within 30 days, except where retention is required by law.</p>
            </section>

            <section>
                <h2>8. Your Rights</h2>
                <p>Depending on your jurisdiction, you may have the following rights:</p>
                <ul>
                    <li><strong>Access:</strong> Request a copy of your personal data</li>
                    <li><strong>Rectification:</strong> Correct inaccurate or incomplete data</li>
                    <li><strong>Erasure:</strong> Request deletion of your data</li>
                    <li><strong>Portability:</strong> Receive your data in a structured format</li>
                    <li><strong>Objection:</strong> Object to processing of your data</li>
                    <li><strong>Restriction:</strong> Restrict processing of your data</li>
                </ul>
                <p>To exercise these rights, contact us at <a href="mailto:support@zynearn.com">support@zynearn.com</a>.</p>
            </section>

            <section>
                <h2>9. Children's Privacy</h2>
                <p>Our platform is not intended for individuals under the age of 13 (or 16 in certain jurisdictions). We do not knowingly collect personal information from children. If we become aware that a child has provided us with personal data, we will delete it immediately.</p>
            </section>

            <section>
                <h2>10. International Data Transfers</h2>
                <p>Your information may be transferred to and processed in countries other than your own. We ensure appropriate safeguards are in place to protect your data in accordance with applicable laws.</p>
            </section>

            <section>
                <h2>11. Changes to This Policy</h2>
                <p>We may update this Privacy Policy from time to time. We will notify you of any changes by posting the new policy on this page and updating the "Last updated" date. We encourage you to review this policy periodically.</p>
            </section>

            <section>
                <h2>12. Contact Us</h2>
                <p>If you have any questions about this Privacy Policy, please contact us:</p>
                <ul>
                    <li>Email: <a href="mailto:support@zynearn.com">support@zynearn.com</a></li>
                    <li>Website: <a href="https://zynearn.com" target="_blank">https://zynearn.com</a></li>
                    <li>Discord: <a href="https://discord.gg/zynearn" target="_blank">Join our Discord</a></li>
                </ul>
            </section>
        </div>
    </div>
</main>

<style>
.legal-hero {
    padding: 120px 0 60px;
    text-align: center;
    background: linear-gradient(135deg, rgba(108,92,231,.1), rgba(0,206,201,.05));
}
.legal-hero h1 {
    font-family: var(--font-display);
    font-size: clamp(2rem, 4vw, 3rem);
    font-weight: 700;
    margin-bottom: 8px;
}
.legal-subtitle {
    color: var(--text-secondary);
    font-size: .9rem;
}
.legal-body {
    max-width: 800px;
    margin: 0 auto;
    padding: 60px 20px 100px;
}
.legal-body section {
    margin-bottom: 40px;
}
.legal-body h2 {
    font-family: var(--font-display);
    font-size: 1.5rem;
    font-weight: 700;
    margin-bottom: 16px;
    color: var(--primary);
}
.legal-body h3 {
    font-size: 1.1rem;
    font-weight: 600;
    margin: 20px 0 10px;
}
.legal-body p {
    color: var(--text-secondary);
    line-height: 1.8;
    margin-bottom: 12px;
}
.legal-body ul {
    list-style: disc;
    padding-left: 24px;
    margin-bottom: 16px;
}
.legal-body ul li {
    color: var(--text-secondary);
    line-height: 1.8;
    margin-bottom: 6px;
}
.legal-body a {
    color: var(--primary);
    text-decoration: underline;
}
.legal-body a:hover {
    color: var(--secondary);
}
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>
