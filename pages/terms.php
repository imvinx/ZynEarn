<?php
require_once __DIR__ . '/../includes/auth_middleware.php';
$pageTitle = 'Terms of Service - ZynEarn';
$pageDescription = 'ZynEarn Terms of Service - Rules and guidelines for using our platform.';
$bodyClass = 'legal-page';
include __DIR__ . '/../includes/header.php';
?>

<main class="legal-content">
    <div class="legal-hero">
        <div class="container">
            <h1>Terms of Service</h1>
            <p class="legal-subtitle">Last updated: May 17, 2026</p>
        </div>
    </div>

    <div class="container">
        <div class="legal-body">
            <section>
                <h2>1. Acceptance of Terms</h2>
                <p>By accessing or using ZynEarn ("the Platform"), you agree to be bound by these Terms of Service. If you do not agree with any part of these terms, you must not use our services.</p>
                <p>We reserve the right to modify these terms at any time. Continued use of the platform after changes constitutes acceptance of the new terms.</p>
            </section>

            <section>
                <h2>2. Eligibility</h2>
                <p>By creating an account, you represent and warrant that:</p>
                <ul>
                    <li>You are at least 13 years of age (or 16 in certain jurisdictions)</li>
                    <li>You have the legal capacity to enter into a binding agreement</li>
                    <li>You are not located in a country subject to sanctions or embargoes</li>
                    <li>You will not use the platform for any illegal or unauthorized purpose</li>
                    <li>All information provided is accurate and complete</li>
                </ul>
            </section>

            <section>
                <h2>3. Account Registration</h2>
                <p>You are responsible for maintaining the confidentiality of your account credentials. You must notify us immediately of any unauthorized use of your account. We are not liable for any loss arising from unauthorized access.</p>
                <p>Each user may maintain only one account. Duplicate or multiple accounts are prohibited and may result in account suspension and forfeiture of earnings.</p>
            </section>

            <section>
                <h2>4. Prohibited Activities</h2>
                <p>You agree not to engage in any of the following:</p>
                <ul>
                    <li>Using automated scripts, bots, or software to complete tasks</li>
                    <li>Creating fake or fraudulent engagements</li>
                    <li>Manipulating referral system through self-referrals or fake accounts</li>
                    <li>Using VPNs, proxies, or other IP masking services to bypass restrictions</li>
                    <li>Engaging in any activity that defrauds advertisers or the platform</li>
                    <li>Attempting to hack, exploit, or compromise platform security</li>
                    <li>Posting offensive, illegal, or inappropriate content</li>
                    <li>Harassing other users or staff members</li>
                    <li>Using the platform for money laundering or illegal transactions</li>
                </ul>
                <p>Violation of these rules may result in immediate account termination and forfeiture of all earnings.</p>
            </section>

            <section>
                <h2>5. Earnings and Withdrawals</h2>
                <h3>Earning Credits</h3>
                <p>Users earn credits by completing tasks, surveys, offers, and other activities on the platform. Credits are credited to your account upon successful completion as determined by our verification process.</p>

                <h3>Minimum Withdrawal</h3>
                <p>The minimum withdrawal amount is $1.00 (or equivalent). Some withdrawal methods may have higher minimums.</p>

                <h3>Processing Time</h3>
                <p>Withdrawals are processed within 24-48 hours for standard members and instantly for premium members. We reserve the right to hold withdrawals for review if suspicious activity is detected.</p>

                <h3>Fees</h3>
                <p>A processing fee of 2% + $0.50 may be applied to withdrawals. VIP members may receive reduced or waived fees.</p>

                <h3>Chargebacks</h3>
                <p>If a chargeback is initiated for any reason, your account will be suspended, and any earnings may be forfeited to cover the chargeback amount plus a processing fee.</p>
            </section>

            <section>
                <h2>6. Referral Program</h2>
                <p>The referral program allows you to earn commission on referred users' earnings. Terms apply:</p>
                <ul>
                    <li>Self-referrals are strictly prohibited</li>
                    <li>Commission is earned on legitimate earnings only</li>
                    <li>We reserve the right to withhold referral bonuses if fraudulent activity is detected</li>
                    <li>Referral terms may be modified at any time</li>
                </ul>
            </section>

            <section>
                <h2>7. Membership Tiers</h2>
                <p>Premium membership tiers (Silver, Gold, Platinum, VIP) offer enhanced earning multipliers and additional features. Membership fees are non-refundable. We reserve the right to modify tier benefits with reasonable notice.</p>
            </section>

            <section>
                <h2>8. Intellectual Property</h2>
                <p>The ZynEarn name, logo, design, and all related content are our intellectual property. You may not reproduce, distribute, or create derivative works without our express written consent.</p>
            </section>

            <section>
                <h2>9. Limitation of Liability</h2>
                <p>ZynEarn is provided "as is" without warranties of any kind. We are not liable for any indirect, incidental, or consequential damages arising from your use of the platform. Our total liability shall not exceed the amount of earnings you have accrued on the platform.</p>
            </section>

            <section>
                <h2>10. Account Termination</h2>
                <p>We reserve the right to suspend or terminate accounts at our discretion, including for violation of these terms. Upon termination:</p>
                <ul>
                    <li>Access to the platform will be revoked</li>
                    <li>Pending earnings may be forfeited if termination is for violation of terms</li>
                    <li>You may request a final payout if termination is not for violation (subject to review)</li>
                </ul>
            </section>

            <section>
                <h2>11. Dispute Resolution</h2>
                <p>Any disputes arising from these terms shall be resolved through binding arbitration in accordance with the laws of the jurisdiction in which the company is registered. You agree to attempt to resolve disputes informally before pursuing arbitration.</p>
            </section>

            <section>
                <h2>12. Severability</h2>
                <p>If any provision of these terms is found to be unenforceable, the remaining provisions shall remain in full force and effect.</p>
            </section>

            <section>
                <h2>13. Entire Agreement</h2>
                <p>These terms constitute the entire agreement between you and ZynEarn regarding your use of the platform, superseding any prior agreements or understandings.</p>
            </section>

            <section>
                <h2>14. Contact</h2>
                <p>For questions about these terms, contact us at <a href="mailto:support@zynearn.com">support@zynearn.com</a>.</p>
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
