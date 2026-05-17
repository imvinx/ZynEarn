<?php
require_once __DIR__ . '/../includes/auth_middleware.php';
$pageTitle = 'FAQ - ZynEarn';
$pageDescription = 'Frequently asked questions about ZynEarn. Find answers to common questions about earning, withdrawals, and more.';
$bodyClass = 'faq-page';
include __DIR__ . '/../includes/header.php';

$faqCategories = [];
$faqItems = [];

try {
    $db = getDB();
    $catStmt = $db->query("SELECT * FROM faq_categories WHERE is_active = 1 ORDER BY sort_order ASC");
    $faqCategories = $catStmt->fetchAll();

    $itemsStmt = $db->query("SELECT f.*, c.name as category_name FROM faqs f JOIN faq_categories c ON f.category_id = c.id WHERE f.is_active = 1 AND c.is_active = 1 ORDER BY c.sort_order ASC, f.sort_order ASC");
    $allItems = $itemsStmt->fetchAll();

    foreach ($allItems as $item) {
        $faqItems[$item['category_id']][] = $item;
    }
} catch (Exception $e) {
    logError('FAQ fetch error', ['message' => $e->getMessage()]);
}
?>

<main class="faq-main">
    <div class="legal-hero">
        <div class="container">
            <h1>Frequently Asked Questions</h1>
            <p class="legal-subtitle">Find answers to the most common questions about ZynEarn.</p>

            <div class="faq-search">
                <i class="fas fa-search"></i>
                <input type="text" id="faqSearch" placeholder="Search questions..." autocomplete="off">
            </div>
        </div>
    </div>

    <div class="container">
        <div class="faq-layout">
            <nav class="faq-sidebar" id="faqSidebar">
                <ul>
                    <?php if (!empty($faqCategories)): ?>
                        <?php foreach ($faqCategories as $cat): ?>
                            <li><a href="#cat-<?= $cat['id'] ?>" class="faq-nav-link" data-cat="<?= $cat['id'] ?>"><i class="fas fa-chevron-right"></i> <?= htmlspecialchars($cat['name']) ?></a></li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li><a href="#general" class="faq-nav-link"><i class="fas fa-chevron-right"></i> General</a></li>
                        <li><a href="#account" class="faq-nav-link"><i class="fas fa-chevron-right"></i> Account</a></li>
                        <li><a href="#earnings" class="faq-nav-link"><i class="fas fa-chevron-right"></i> Earnings</a></li>
                        <li><a href="#withdrawals" class="faq-nav-link"><i class="fas fa-chevron-right"></i> Withdrawals</a></li>
                        <li><a href="#referrals" class="faq-nav-link"><i class="fas fa-chevron-right"></i> Referrals</a></li>
                    <?php endif; ?>
                </ul>
            </nav>

            <div class="faq-content">
                <?php if (!empty($faqCategories)): ?>
                    <?php foreach ($faqCategories as $cat):
                        $items = $faqItems[$cat['id']] ?? [];
                    ?>
                    <section class="faq-section" id="cat-<?= $cat['id'] ?>">
                        <h2 class="faq-category-title"><?= htmlspecialchars($cat['name']) ?></h2>
                        <?php if (!empty($cat['description'])): ?>
                            <p class="faq-category-desc"><?= htmlspecialchars($cat['description']) ?></p>
                        <?php endif; ?>
                        <div class="faq-list">
                            <?php foreach ($items as $item): ?>
                            <div class="faq-item">
                                <button class="faq-question">
                                    <?= htmlspecialchars($item['question']) ?>
                                    <i class="fas fa-chevron-down"></i>
                                </button>
                                <div class="faq-answer">
                                    <p><?= nl2br(htmlspecialchars($item['answer'])) ?></p>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </section>
                    <?php endforeach; ?>
                <?php else: ?>
                    <section class="faq-section" id="general">
                        <h2 class="faq-category-title">General</h2>
                        <div class="faq-list">
                            <div class="faq-item active">
                                <button class="faq-question">What is ZynEarn? <i class="fas fa-chevron-down"></i></button>
                                <div class="faq-answer"><p>ZynEarn is a premium online earning platform where users complete tasks, surveys, offers, and other activities to earn real money. We connect you with advertisers and businesses who pay for your attention and actions.</p></div>
                            </div>
                            <div class="faq-item">
                                <button class="faq-question">Is ZynEarn free to join? <i class="fas fa-chevron-down"></i></button>
                                <div class="faq-answer"><p>Yes! Creating a ZynEarn account is completely free. You can start earning immediately without any upfront payment. Premium membership plans are available for users who want to maximize their earnings.</p></div>
                            </div>
                            <div class="faq-item">
                                <button class="faq-question">Is ZynEarn available worldwide? <i class="fas fa-chevron-down"></i></button>
                                <div class="faq-answer"><p>Yes! ZynEarn is available in over 150 countries worldwide. Some offers and surveys may be region-specific, but we constantly add new opportunities for all geographic locations.</p></div>
                            </div>
                        </div>
                    </section>

                    <section class="faq-section" id="account">
                        <h2 class="faq-category-title">Account</h2>
                        <div class="faq-list">
                            <div class="faq-item">
                                <button class="faq-question">How do I create an account? <i class="fas fa-chevron-down"></i></button>
                                <div class="faq-answer"><p>Click the "Get Started" button on our homepage or visit the registration page. Enter your username, email, and a strong password. Verify your email address and you are ready to start earning!</p></div>
                            </div>
                            <div class="faq-item">
                                <button class="faq-question">I forgot my password. What should I do? <i class="fas fa-chevron-down"></i></button>
                                <div class="faq-answer"><p>Click the "Forgot Password" link on the login page. Enter your email address and we will send you a password reset link. Follow the instructions to create a new password.</p></div>
                            </div>
                            <div class="faq-item">
                                <button class="faq-question">Can I delete my account? <i class="fas fa-chevron-down"></i></button>
                                <div class="faq-answer"><p>Yes, you can request account deletion by contacting our support team. Please note that any remaining balance may be forfeited upon account deletion. We will process your request within 30 days.</p></div>
                            </div>
                        </div>
                    </section>

                    <section class="faq-section" id="earnings">
                        <h2 class="faq-category-title">Earnings</h2>
                        <div class="faq-list">
                            <div class="faq-item">
                                <button class="faq-question">How much can I earn? <i class="fas fa-chevron-down"></i></button>
                                <div class="faq-answer"><p>Earnings vary based on the methods you use and your membership level. Free members can earn $5-20/day, while VIP members earn $50-200+/day. Many top earners make over $1,000/month.</p></div>
                            </div>
                            <div class="faq-item">
                                <button class="faq-question">How do I earn money? <i class="fas fa-chevron-down"></i></button>
                                <div class="faq-answer"><p>You can earn by completing offers, surveys, tasks, shortlinks, watching videos, playing games (spin wheel, scratch cards), taking quizzes, using the faucet, and referring friends. Each method has different payout rates.</p></div>
                            </div>
                            <div class="faq-item">
                                <button class="faq-question">How is my balance calculated? <i class="fas fa-chevron-down"></i></button>
                                <div class="faq-answer"><p>Your balance is calculated as total earnings from all completed activities minus any withdrawals you have made. Earnings are credited instantly for most activities upon completion.</p></div>
                            </div>
                        </div>
                    </section>

                    <section class="faq-section" id="withdrawals">
                        <h2 class="faq-category-title">Withdrawals</h2>
                        <div class="faq-list">
                            <div class="faq-item">
                                <button class="faq-question">How do I get paid? <i class="fas fa-chevron-down"></i></button>
                                <div class="faq-answer"><p>You can withdraw your earnings via PayPal, cryptocurrency (Bitcoin, Ethereum, USDT), gift cards (Amazon, Google Play, Steam), or direct bank transfer. Minimum withdrawal starts at just $1.</p></div>
                            </div>
                            <div class="faq-item">
                                <button class="faq-question">How long do withdrawals take? <i class="fas fa-chevron-down"></i></button>
                                <div class="faq-answer"><p>Withdrawals are processed instantly for Gold members and above. Free and Silver members typically receive their funds within 24-48 hours. We prioritize fast payouts for all members.</p></div>
                            </div>
                            <div class="faq-item">
                                <button class="faq-question">Are there any withdrawal fees? <i class="fas fa-chevron-down"></i></button>
                                <div class="faq-answer"><p>A small processing fee may apply to withdrawals. The standard fee is 2% + $0.50 per transaction. VIP members receive reduced or waived fees depending on their membership tier.</p></div>
                            </div>
                        </div>
                    </section>

                    <section class="faq-section" id="referrals">
                        <h2 class="faq-category-title">Referrals</h2>
                        <div class="faq-list">
                            <div class="faq-item">
                                <button class="faq-question">How does the referral program work? <i class="fas fa-chevron-down"></i></button>
                                <div class="faq-answer"><p>Share your unique referral link with friends. When they sign up and start earning, you earn a lifetime commission of 20% on their earnings. You also earn 5% on their referrals' earnings.</p></div>
                            </div>
                            <div class="faq-item">
                                <button class="faq-question">Is there a limit to how many people I can refer? <i class="fas fa-chevron-down"></i></button>
                                <div class="faq-answer"><p>There is no limit! You can refer as many people as you want. The more active referrals you have, the more passive income you earn. Top referrers also win monthly bonus prizes.</p></div>
                            </div>
                        </div>
                    </section>
                <?php endif; ?>

                <div class="faq-cta">
                    <h3>Still have questions?</h3>
                    <p>Our support team is ready to help you.</p>
                    <a href="/pages/contact.php" class="btn btn-primary">Contact Support <i class="fas fa-arrow-right"></i></a>
                </div>
            </div>
        </div>
    </div>
</main>

<style>
.legal-hero {
    padding: 120px 0 60px;
    text-align: center;
    background: linear-gradient(135deg, rgba(108,92,231,.1), rgba(0,206,201,.05));
    position: relative;
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
    margin-bottom: 30px;
}
.faq-search {
    max-width: 500px;
    margin: 0 auto;
    position: relative;
}
.faq-search i {
    position: absolute;
    left: 16px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-secondary);
    font-size: .9rem;
}
.faq-search input {
    width: 100%;
    padding: 14px 16px 14px 44px;
    border-radius: 50px;
    background: var(--dark-card);
    border: 1px solid var(--dark-border);
    color: var(--text-primary);
    font-size: .9rem;
    transition: all var(--transition);
}
.faq-search input:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(108,92,231,.1);
    outline: none;
}
.faq-search input::placeholder {
    color: var(--text-secondary);
}
.faq-layout {
    display: grid;
    grid-template-columns: 220px 1fr;
    gap: 40px;
    padding: 50px 20px 100px;
    max-width: 1100px;
    margin: 0 auto;
}
.faq-sidebar {
    position: sticky;
    top: 100px;
    align-self: start;
}
.faq-sidebar ul {
    list-style: none;
    padding: 0;
    margin: 0;
}
.faq-sidebar ul li {
    margin-bottom: 4px;
}
.faq-nav-link {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 14px;
    border-radius: var(--radius-sm);
    font-size: .85rem;
    color: var(--text-secondary);
    transition: all var(--transition);
}
.faq-nav-link i {
    font-size: .65rem;
    color: var(--primary);
    transition: transform var(--transition);
}
.faq-nav-link:hover, .faq-nav-link.active {
    background: rgba(108,92,231,.1);
    color: var(--primary);
}
.faq-nav-link:hover i, .faq-nav-link.active i {
    transform: translateX(3px);
}
.faq-section {
    margin-bottom: 40px;
    scroll-margin-top: 110px;
}
.faq-category-title {
    font-family: var(--font-display);
    font-size: 1.4rem;
    font-weight: 700;
    margin-bottom: 6px;
}
.faq-category-desc {
    color: var(--text-secondary);
    font-size: .85rem;
    margin-bottom: 20px;
}
.faq-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.faq-item {
    background: var(--dark-card);
    border: 1px solid var(--dark-border);
    border-radius: var(--radius-sm);
    overflow: hidden;
    transition: all var(--transition);
}
.faq-item:hover {
    border-color: rgba(108,92,231,.2);
}
.faq-question {
    width: 100%;
    padding: 18px 24px;
    background: none;
    border: none;
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: .9rem;
    font-weight: 500;
    color: var(--text-primary);
    text-align: left;
    gap: 16px;
    cursor: pointer;
}
.faq-question i {
    font-size: .8rem;
    color: var(--primary);
    transition: transform var(--transition);
    flex-shrink: 0;
}
.faq-item.active .faq-question i {
    transform: rotate(180deg);
}
.faq-answer {
    max-height: 0;
    overflow: hidden;
    transition: max-height .4s ease, padding .4s ease;
}
.faq-item.active .faq-answer {
    max-height: 500px;
    padding: 0 24px 18px;
}
.faq-answer p {
    color: var(--text-secondary);
    font-size: .85rem;
    line-height: 1.7;
    margin: 0;
}
.faq-cta {
    text-align: center;
    padding: 40px;
    background: var(--dark-card);
    border: 1px solid var(--dark-border);
    border-radius: var(--radius-lg);
    margin-top: 40px;
}
.faq-cta h3 {
    font-family: var(--font-display);
    font-size: 1.3rem;
    font-weight: 700;
    margin-bottom: 8px;
}
.faq-cta p {
    color: var(--text-secondary);
    margin-bottom: 20px;
}
@media (max-width: 768px) {
    .faq-layout {
        grid-template-columns: 1fr;
        padding: 30px 16px 80px;
    }
    .faq-sidebar {
        position: static;
        display: flex;
        overflow-x: auto;
        gap: 8px;
        padding-bottom: 12px;
    }
    .faq-sidebar ul {
        display: flex;
        gap: 8px;
    }
    .faq-sidebar ul li {
        white-space: nowrap;
        margin-bottom: 0;
    }
    .faq-nav-link {
        padding: 8px 14px;
        border: 1px solid var(--dark-border);
        border-radius: 50px;
        font-size: .8rem;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const items = document.querySelectorAll('.faq-item');
    items.forEach(function(item) {
        const q = item.querySelector('.faq-question');
        q.addEventListener('click', function() {
            const isActive = item.classList.contains('active');
            items.forEach(function(i) { i.classList.remove('active'); });
            if (!isActive) item.classList.add('active');
        });
    });

    const searchInput = document.getElementById('faqSearch');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const query = this.value.toLowerCase().trim();
            const sections = document.querySelectorAll('.faq-section');
            sections.forEach(function(section) {
                let hasVisible = false;
                const items = section.querySelectorAll('.faq-item');
                items.forEach(function(item) {
                    const question = item.querySelector('.faq-question').textContent.toLowerCase();
                    const answer = item.querySelector('.faq-answer p').textContent.toLowerCase();
                    const matches = !query || question.includes(query) || answer.includes(query);
                    item.style.display = matches ? '' : 'none';
                    if (matches) hasVisible = true;
                });
                section.style.display = hasVisible ? '' : 'none';
            });
        });
    }

    const navLinks = document.querySelectorAll('.faq-nav-link');
    navLinks.forEach(function(link) {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
            navLinks.forEach(function(l) { l.classList.remove('active'); });
            this.classList.add('active');
        });
    });

    if ('IntersectionObserver' in window) {
        const sections = document.querySelectorAll('.faq-section');
        const observer = new IntersectionObserver(function(entries) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    const id = entry.target.getAttribute('id');
                    navLinks.forEach(function(link) {
                        link.classList.toggle('active', link.getAttribute('href') === '#' + id);
                    });
                }
            });
        }, { rootMargin: '-100px 0px -60% 0px' });
        sections.forEach(function(s) { observer.observe(s); });
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
