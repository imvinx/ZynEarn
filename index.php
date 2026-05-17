<?php
session_start();
if (isset($_SESSION['user_id'])) {
    header('Location: /user/dashboard.php');
    exit;
}
$site_name = 'ZynEarn';
$site_description = 'Turn your time into real money. Complete tasks, surveys, offers and more. Withdraw instantly!';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
<meta name="description" content="<?php echo $site_description; ?>">
<meta name="keywords" content="earn money online, paid tasks, surveys, offerwall, make money from home, ZynEarn">
<meta name="robots" content="index, follow">
<meta name="theme-color" content="#0a0a1a">

<!-- Open Graph -->
<meta property="og:title" content="<?php echo $site_name; ?> - Turn Your Time Into Real Money">
<meta property="og:description" content="<?php echo $site_description; ?>">
<meta property="og:type" content="website">
<meta property="og:url" content="https://zynearn.com">
<meta property="og:image" content="/assets/img/og-image.png">
<meta property="og:site_name" content="<?php echo $site_name; ?>">

<!-- Twitter -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?php echo $site_name; ?> - Turn Your Time Into Real Money">
<meta name="twitter:description" content="<?php echo $site_description; ?>">
<meta name="twitter:image" content="/assets/img/og-image.png">

<!-- PWA -->
<link rel="manifest" href="/pwa/manifest.json">
<link rel="apple-touch-icon" href="/assets/img/apple-touch-icon.png">
<link rel="icon" type="image/png" sizes="32x32" href="/assets/img/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16" href="/assets/img/favicon-16x16.png">
<link rel="shortcut icon" href="/favicon.ico">

<!-- Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Space+Grotesk:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">

<!-- Font Awesome 6 -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">

<!-- Styles -->
<link rel="stylesheet" href="/assets/css/style.css">
<link rel="stylesheet" href="/assets/css/landing.css">

<style>
/* ========== RESET & BASE ========== */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --primary:#6c5ce7;
  --primary-dark:#5a4bd1;
  --secondary:#00cec9;
  --accent:#fd79a8;
  --accent2:#fdcb6e;
  --gradient-main:linear-gradient(135deg,#6c5ce7,#a29bfe,#00cec9);
  --gradient-warm:linear-gradient(135deg,#fd79a8,#fdcb6e);
  --gradient-premium:linear-gradient(135deg,#6c5ce7,#e17055,#00cec9);
  --dark-bg:#0a0a1a;
  --dark-surface:#13132b;
  --dark-card:#1a1a3e;
  --dark-border:rgba(108,92,231,.15);
  --light-bg:#f8f9ff;
  --light-surface:#ffffff;
  --light-card:#ffffff;
  --light-border:rgba(108,92,231,.1);
  --text-primary:#ffffff;
  --text-secondary:rgba(255,255,255,.7);
  --text-dark:#1a1a2e;
  --text-dark-secondary:rgba(26,26,46,.6);
  --radius:16px;
  --radius-sm:10px;
  --radius-lg:24px;
  --shadow:0 8px 32px rgba(108,92,231,.2);
  --shadow-glow:0 0 40px rgba(108,92,231,.3);
  --glass:rgba(255,255,255,.05);
  --glass-border:rgba(255,255,255,.1);
  --transition:.3s cubic-bezier(.4,0,.2,1);
  --font-primary:'Inter',sans-serif;
  --font-display:'Space Grotesk',sans-serif;
  --font-mono:'JetBrains Mono',monospace;
}
[data-theme="light"]{
  --dark-bg:#f8f9ff;
  --dark-surface:#ffffff;
  --dark-card:#ffffff;
  --dark-border:rgba(108,92,231,.1);
  --text-primary:#1a1a2e;
  --text-secondary:rgba(26,26,46,.6);
  --glass:rgba(108,92,231,.03);
  --glass-border:rgba(108,92,231,.1);
  --shadow:0 8px 32px rgba(108,92,231,.1);
}
html{scroll-behavior:smooth;font-size:16px}
body{
  font-family:var(--font-primary);
  background:var(--dark-bg);
  color:var(--text-primary);
  overflow-x:hidden;
  line-height:1.6;
  transition:background var(--transition),color var(--transition);
}
a{text-decoration:none;color:inherit}
ul{list-style:none}
img{max-width:100%;height:auto}
button{cursor:pointer;border:none;outline:none;font-family:inherit}
input,textarea{font-family:inherit;outline:none}
.container{max-width:1200px;margin:0 auto;padding:0 20px}
.section{padding:100px 0;position:relative}
.section-label{
  display:inline-block;
  font-size:.75rem;
  font-weight:700;
  letter-spacing:2px;
  text-transform:uppercase;
  background:var(--gradient-main);
  -webkit-background-clip:text;
  -webkit-text-fill-color:transparent;
  background-clip:text;
  margin-bottom:12px;
}
.section-title{
  font-family:var(--font-display);
  font-size:clamp(1.8rem,4vw,3rem);
  font-weight:700;
  margin-bottom:16px;
  line-height:1.2;
}
.section-subtitle{
  color:var(--text-secondary);
  font-size:1.05rem;
  max-width:600px;
  line-height:1.7;
}
.gradient-text{
  background:var(--gradient-main);
  -webkit-background-clip:text;
  -webkit-text-fill-color:transparent;
  background-clip:text;
}
.gradient-text-warm{
  background:var(--gradient-warm);
  -webkit-background-clip:text;
  -webkit-text-fill-color:transparent;
  background-clip:text;
}
.glass{
  background:var(--glass);
  backdrop-filter:blur(20px) saturate(1.8);
  -webkit-backdrop-filter:blur(20px) saturate(1.8);
  border:1px solid var(--glass-border);
}
.glass-card{
  background:var(--dark-card);
  border:1px solid var(--dark-border);
  border-radius:var(--radius);
  transition:all var(--transition);
}
.glass-card:hover{
  transform:translateY(-4px);
  box-shadow:var(--shadow-glow);
  border-color:rgba(108,92,231,.3);
}
.btn{
  display:inline-flex;
  align-items:center;
  gap:8px;
  padding:14px 28px;
  border-radius:50px;
  font-weight:600;
  font-size:.9rem;
  transition:all var(--transition);
  position:relative;
  overflow:hidden;
}
.btn-primary{
  background:var(--gradient-main);
  color:#fff;
  box-shadow:0 4px 20px rgba(108,92,231,.4);
}
.btn-primary:hover{
  transform:translateY(-2px);
  box-shadow:0 8px 30px rgba(108,92,231,.5);
}
.btn-outline{
  background:transparent;
  color:var(--text-primary);
  border:2px solid var(--glass-border);
}
.btn-outline:hover{
  border-color:var(--primary);
  background:rgba(108,92,231,.1);
}
.btn-glow{
  animation:pulse-glow 2s ease-in-out infinite;
}
@keyframes pulse-glow{
  0%,100%{box-shadow:0 0 20px rgba(108,92,231,.3)}
  50%{box-shadow:0 0 40px rgba(108,92,231,.6),0 0 60px rgba(108,92,231,.3)}
}
.btn-sm{padding:10px 20px;font-size:.8rem}

/* ========== PRELOADER ========== */
#preloader{
  position:fixed;
  inset:0;
  z-index:99999;
  background:var(--dark-bg);
  display:flex;
  flex-direction:column;
  align-items:center;
  justify-content:center;
  transition:opacity .6s ease,visibility .6s ease;
}
#preloader.hidden{opacity:0;visibility:hidden;pointer-events:none}
.preloader-logo{
  font-family:var(--font-display);
  font-size:2.5rem;
  font-weight:700;
  background:var(--gradient-main);
  -webkit-background-clip:text;
  -webkit-text-fill-color:transparent;
  background-clip:text;
  margin-bottom:10px;
}
.preloader-logo i{font-size:2rem;margin-right:10px}
.preloader-sub{color:var(--text-secondary);font-size:.85rem;margin-bottom:30px}
.preloader-bar-wrap{
  width:240px;height:4px;
  background:rgba(255,255,255,.1);
  border-radius:4px;overflow:hidden;
}
.preloader-bar{
  height:100%;width:0%;
  background:var(--gradient-main);
  border-radius:4px;
  transition:width .3s ease;
}
.preloader-percent{
  font-family:var(--font-mono);
  font-size:.8rem;
  color:var(--text-secondary);
  margin-top:10px;
}

/* ========== NAVBAR ========== */
.navbar{
  position:fixed;
  top:0;left:0;right:0;
  z-index:999;
  padding:16px 0;
  transition:all var(--transition);
}
.navbar.scrolled{
  background:rgba(10,10,26,.85);
  backdrop-filter:blur(20px) saturate(1.8);
  -webkit-backdrop-filter:blur(20px) saturate(1.8);
  border-bottom:1px solid var(--glass-border);
  padding:10px 0;
}
[data-theme="light"] .navbar.scrolled{
  background:rgba(255,255,255,.85);
}
.navbar .container{
  display:flex;
  align-items:center;
  justify-content:space-between;
}
.nav-logo{
  display:flex;
  align-items:center;
  gap:10px;
  font-family:var(--font-display);
  font-size:1.5rem;
  font-weight:700;
}
.nav-logo i{
  font-size:1.6rem;
  background:var(--gradient-main);
  -webkit-background-clip:text;
  -webkit-text-fill-color:transparent;
  background-clip:text;
}
.nav-links{display:flex;align-items:center;gap:24px}
.nav-links a{
  font-size:.85rem;
  font-weight:500;
  color:var(--text-secondary);
  transition:color var(--transition);
  position:relative;
}
.nav-links a::after{
  content:'';position:absolute;
  bottom:-4px;left:0;
  width:0;height:2px;
  background:var(--gradient-main);
  transition:width var(--transition);
  border-radius:2px;
}
.nav-links a:hover{color:var(--text-primary)}
.nav-links a:hover::after{width:100%}
.nav-actions{display:flex;align-items:center;gap:12px}
.theme-toggle{
  width:40px;height:40px;
  display:flex;align-items:center;justify-content:center;
  border-radius:50%;
  background:var(--glass);
  border:1px solid var(--glass-border);
  color:var(--text-secondary);
  font-size:1rem;
  transition:all var(--transition);
}
.theme-toggle:hover{color:var(--primary);border-color:var(--primary)}
.hamburger{
  display:none;
  flex-direction:column;
  gap:5px;
  background:none;
  padding:8px;
}
.hamburger span{
  display:block;
  width:24px;height:2px;
  background:var(--text-primary);
  border-radius:2px;
  transition:all var(--transition);
}
.hamburger.active span:nth-child(1){transform:rotate(45deg) translate(5px,5px)}
.hamburger.active span:nth-child(2){opacity:0}
.hamburger.active span:nth-child(3){transform:rotate(-45deg) translate(5px,-5px)}

/* ========== HERO ========== */
.hero{
  min-height:100vh;
  display:flex;
  align-items:center;
  position:relative;
  overflow:hidden;
  padding-top:80px;
}
#particles-canvas{
  position:absolute;
  inset:0;
  z-index:0;
  pointer-events:none;
}
.hero-bg-gradient{
  position:absolute;
  inset:0;
  z-index:0;
  background:radial-gradient(ellipse 80% 60% at 50% 40%,rgba(108,92,231,.15),transparent),
             radial-gradient(ellipse 60% 50% at 80% 80%,rgba(0,206,201,.1),transparent),
             radial-gradient(ellipse 50% 50% at 20% 60%,rgba(253,121,168,.08),transparent);
  pointer-events:none;
}
.hero .container{
  position:relative;
  z-index:1;
  display:grid;
  grid-template-columns:1fr 1fr;
  gap:60px;
  align-items:center;
}
.hero-content{position:relative}
.hero-badge{
  display:inline-flex;
  align-items:center;
  gap:8px;
  padding:8px 16px;
  border-radius:50px;
  background:rgba(108,92,231,.15);
  border:1px solid rgba(108,92,231,.25);
  font-size:.8rem;
  font-weight:500;
  margin-bottom:24px;
  color:var(--text-secondary);
}
.hero-badge i{color:var(--secondary);font-size:.7rem}
.hero h1{
  font-family:var(--font-display);
  font-size:clamp(2.2rem,5vw,3.8rem);
  font-weight:800;
  line-height:1.15;
  margin-bottom:20px;
}
.typing-cursor{animation:blink 1s step-end infinite;font-weight:100;background:var(--gradient-main);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
@keyframes blink{50%{opacity:0}}
.hero p{
  color:var(--text-secondary);
  font-size:1.1rem;
  line-height:1.8;
  max-width:480px;
  margin-bottom:32px;
}
.hero-buttons{display:flex;gap:16px;flex-wrap:wrap;margin-bottom:40px}
.hero-stats{
  display:flex;
  gap:40px;
  padding:20px 0;
  border-top:1px solid var(--glass-border);
}
.hero-stat h3{
  font-family:var(--font-display);
  font-size:1.5rem;
  font-weight:700;
}
.hero-stat p{
  font-size:.8rem;
  color:var(--text-secondary);
  margin-bottom:0;
}
.hero-visual{position:relative;min-height:500px}
.hero-floating-card{
  position:absolute;
  background:var(--dark-card);
  border:1px solid var(--dark-border);
  border-radius:var(--radius);
  padding:20px;
  box-shadow:0 20px 60px rgba(0,0,0,.3);
  animation:float 6s ease-in-out infinite;
  width:200px;
  backdrop-filter:blur(10px);
}
.hero-floating-card:nth-child(1){
  top:10%;right:5%;
  animation-delay:0s;
}
.hero-floating-card:nth-child(2){
  top:40%;left:0%;
  animation-delay:2s;
  width:180px;
}
.hero-floating-card:nth-child(3){
  bottom:15%;right:15%;
  animation-delay:4s;
  width:160px;
}
.hero-floating-card .card-icon{
  width:40px;height:40px;
  border-radius:12px;
  display:flex;align-items:center;justify-content:center;
  margin-bottom:10px;
  font-size:1.2rem;
}
.hero-floating-card .card-icon.purple{background:rgba(108,92,231,.2);color:var(--primary)}
.hero-floating-card .card-icon.green{background:rgba(0,206,201,.2);color:var(--secondary)}
.hero-floating-card .card-icon.pink{background:rgba(253,121,168,.2);color:var(--accent)}
.hero-floating-card h4{font-size:.9rem;font-weight:600;margin-bottom:4px}
.hero-floating-card p{font-size:.75rem;color:var(--text-secondary);margin-bottom:0}
.hero-floating-card .amount{
  font-family:var(--font-mono);
  font-size:1.3rem;
  font-weight:700;
  color:var(--secondary);
  margin-top:6px;
}
@keyframes float{
  0%,100%{transform:translateY(0px)}
  50%{transform:translateY(-20px)}
}

.hero-notification{
  position:absolute;
  bottom:5%;left:5%;
  display:flex;
  align-items:center;
  gap:10px;
  padding:10px 16px;
  border-radius:12px;
  background:rgba(26,26,62,.9);
  backdrop-filter:blur(10px);
  border:1px solid var(--glass-border);
  font-size:.8rem;
  animation:slideUp 3s ease-in-out infinite;
  white-space:nowrap;
}
.hero-notification .avatar{
  width:28px;height:28px;
  border-radius:50%;
  background:var(--gradient-main);
  display:flex;align-items:center;justify-content:center;
  font-size:.7rem;font-weight:600;color:#fff;
  flex-shrink:0;
}
.hero-notification strong{color:var(--secondary);font-family:var(--font-mono)}
@keyframes slideUp{
  0%,100%{transform:translateY(0);opacity:1}
  50%{transform:translateY(-8px);opacity:1}
}

/* ========== FLOATING NOTIFICATIONS ========== */
.floating-toasts{
  position:fixed;
  bottom:100px;
  right:20px;
  z-index:998;
  display:flex;
  flex-direction:column;
  gap:10px;
  pointer-events:none;
}
.toast-notification{
  background:var(--dark-card);
  border:1px solid var(--glass-border);
  border-radius:12px;
  padding:12px 16px;
  display:flex;
  align-items:center;
  gap:10px;
  backdrop-filter:blur(10px);
  box-shadow:0 10px 30px rgba(0,0,0,.3);
  font-size:.8rem;
  transform:translateX(120%);
  opacity:0;
  transition:all .5s ease;
  pointer-events:auto;
  max-width:280px;
}
.toast-notification.show{transform:translateX(0);opacity:1}
.toast-notification .t-avatar{
  width:30px;height:30px;
  border-radius:50%;
  background:var(--gradient-warm);
  display:flex;align-items:center;justify-content:center;
  font-size:.65rem;font-weight:600;color:#fff;
  flex-shrink:0;
}
.toast-notification .t-info{flex:1;min-width:0}
.toast-notification .t-name{font-weight:600;font-size:.75rem}
.toast-notification .t-action{color:var(--text-secondary);font-size:.7rem}
.toast-notification .t-amount{
  font-family:var(--font-mono);
  font-weight:700;
  color:var(--secondary);
  font-size:.85rem;
}

/* ========== TRUST BADGES ========== */
.trust-section{
  padding:50px 0;
  background:rgba(108,92,231,.03);
  border-top:1px solid var(--glass-border);
  border-bottom:1px solid var(--glass-border);
}
.trust-label{
  text-align:center;
  font-size:.75rem;
  text-transform:uppercase;
  letter-spacing:2px;
  color:var(--text-secondary);
  margin-bottom:30px;
  font-weight:600;
}
.trust-track{
  display:flex;
  gap:60px;
  align-items:center;
  justify-content:center;
  flex-wrap:wrap;
  opacity:.6;
}
.trust-item{
  display:flex;
  align-items:center;
  gap:10px;
  font-size:.85rem;
  font-weight:500;
  color:var(--text-secondary);
  transition:opacity var(--transition);
}
.trust-item i{font-size:1.5rem}
.trust-item:hover{opacity:1}
.trust-item .big{font-family:var(--font-display);font-size:1.4rem;font-weight:700}

/* ========== FEATURES ========== */
.features-grid{
  display:grid;
  grid-template-columns:repeat(4,1fr);
  gap:20px;
  margin-top:50px;
}
.feature-card{
  background:var(--dark-card);
  border:1px solid var(--dark-border);
  border-radius:var(--radius);
  padding:28px 24px;
  text-align:center;
  transition:all var(--transition);
  position:relative;
  overflow:hidden;
}
.feature-card::before{
  content:'';
  position:absolute;
  top:0;left:0;
  right:0;height:3px;
  background:var(--gradient-main);
  opacity:0;
  transition:opacity var(--transition);
}
.feature-card:hover::before{opacity:1}
.feature-card:hover{
  transform:translateY(-6px);
  box-shadow:var(--shadow-glow);
  border-color:rgba(108,92,231,.3);
}
.feature-card .f-icon{
  width:56px;height:56px;
  border-radius:16px;
  display:flex;align-items:center;justify-content:center;
  font-size:1.4rem;
  margin:0 auto 16px;
  background:rgba(108,92,231,.1);
  color:var(--primary);
  transition:all var(--transition);
}
.feature-card:hover .f-icon{background:var(--gradient-main);color:#fff;transform:scale(1.1) rotate(-5deg)}
.feature-card h3{font-size:1rem;font-weight:600;margin-bottom:8px}
.feature-card p{font-size:.82rem;color:var(--text-secondary);line-height:1.6}

/* ========== HOW IT WORKS ========== */
.how-steps{
  display:grid;
  grid-template-columns:repeat(4,1fr);
  gap:30px;
  margin-top:50px;
  position:relative;
}
.how-steps::before{
  content:'';
  position:absolute;
  top:60px;left:calc(12.5% + 15px);
  right:calc(12.5% + 15px);
  height:2px;
  background:linear-gradient(90deg,var(--primary),var(--secondary));
  z-index:0;
}
.step-card{
  text-align:center;
  position:relative;
  z-index:1;
}
.step-number{
  width:60px;height:60px;
  border-radius:50%;
  display:flex;align-items:center;justify-content:center;
  font-family:var(--font-display);
  font-size:1.4rem;
  font-weight:700;
  margin:0 auto 20px;
  background:var(--gradient-main);
  color:#fff;
  box-shadow:0 0 30px rgba(108,92,231,.3);
  position:relative;
}
.step-number::after{
  content:'';
  position:absolute;
  inset:-4px;
  border-radius:50%;
  border:2px solid rgba(108,92,231,.2);
  animation:pulse-ring 2s ease-in-out infinite;
}
@keyframes pulse-ring{
  0%,100%{transform:scale(1);opacity:1}
  50%{transform:scale(1.1);opacity:.5}
}
.step-icon{
  font-size:1rem;
  margin-bottom:8px;
  display:block;
}
.step-card h3{font-size:1.05rem;font-weight:600;margin-bottom:8px}
.step-card p{font-size:.82rem;color:var(--text-secondary);line-height:1.6;max-width:220px;margin:0 auto}

/* ========== EARNING METHODS (TABS) ========== */
.tabs-nav{
  display:flex;
  gap:8px;
  justify-content:center;
  margin-top:40px;
  flex-wrap:wrap;
}
.tab-btn{
  padding:10px 24px;
  border-radius:50px;
  background:var(--glass);
  border:1px solid var(--glass-border);
  color:var(--text-secondary);
  font-weight:500;
  font-size:.85rem;
  transition:all var(--transition);
}
.tab-btn:hover,.tab-btn.active{
  background:var(--gradient-main);
  color:#fff;
  border-color:transparent;
  box-shadow:0 4px 15px rgba(108,92,231,.3);
}
.tab-content{display:none;margin-top:40px}
.tab-content.active{display:block}
.methods-grid{
  display:grid;
  grid-template-columns:repeat(3,1fr);
  gap:20px;
}
.method-card{
  background:var(--dark-card);
  border:1px solid var(--dark-border);
  border-radius:var(--radius);
  padding:24px;
  display:flex;
  gap:16px;
  align-items:flex-start;
  transition:all var(--transition);
}
.method-card:hover{
  transform:translateY(-3px);
  border-color:rgba(108,92,231,.3);
  box-shadow:var(--shadow-glow);
}
.method-card .m-icon{
  width:48px;height:48px;
  border-radius:14px;
  display:flex;align-items:center;justify-content:center;
  font-size:1.2rem;
  flex-shrink:0;
}
.method-card .m-icon.purple{background:rgba(108,92,231,.15);color:var(--primary)}
.method-card .m-icon.green{background:rgba(0,206,201,.15);color:var(--secondary)}
.method-card .m-icon.pink{background:rgba(253,121,168,.15);color:var(--accent)}
.method-card .m-icon.orange{background:rgba(253,203,110,.15);color:var(--accent2)}
.method-card .m-icon.blue{background:rgba(116,185,255,.15);color:#74b9ff}
.method-card h4{font-size:.95rem;font-weight:600;margin-bottom:4px}
.method-card p{font-size:.78rem;color:var(--text-secondary);line-height:1.5}
.method-card .earnings{font-family:var(--font-mono);font-size:.85rem;color:var(--secondary);margin-top:6px;display:block}

/* ========== STATS SECTION ========== */
.stats-section{
  background:linear-gradient(135deg,rgba(108,92,231,.05),rgba(0,206,201,.05));
  overflow:hidden;
}
.stats-grid{
  display:grid;
  grid-template-columns:repeat(3,1fr);
  gap:30px;
  margin-bottom:60px;
}
.stat-card{
  text-align:center;
  padding:40px 20px;
  background:var(--dark-card);
  border:1px solid var(--dark-border);
  border-radius:var(--radius-lg);
  transition:all var(--transition);
}
.stat-card:hover{transform:translateY(-4px);box-shadow:var(--shadow-glow)}
.stat-card .stat-icon{
  font-size:2rem;
  margin-bottom:16px;
  display:block;
}
.stat-card h3{
  font-family:var(--font-display);
  font-size:2.5rem;
  font-weight:700;
  margin-bottom:4px;
}
.stat-card p{
  color:var(--text-secondary);
  font-size:.85rem;
}
.live-activity{
  max-width:600px;
  margin:0 auto;
  background:var(--dark-card);
  border:1px solid var(--dark-border);
  border-radius:var(--radius-lg);
  padding:30px;
}
.live-activity h4{
  font-family:var(--font-display);
  font-size:1.1rem;
  margin-bottom:20px;
  display:flex;
  align-items:center;
  gap:8px;
}
.live-activity h4 i{color:var(--secondary);font-size:.7rem}
.live-feed{display:flex;flex-direction:column;gap:12px}
.live-item{
  display:flex;
  align-items:center;
  gap:12px;
  padding:10px 0;
  border-bottom:1px solid var(--glass-border);
  animation:fadeFeed .5s ease;
}
.live-item:last-child{border-bottom:none}
.live-item .l-avatar{
  width:32px;height:32px;
  border-radius:50%;
  font-size:.7rem;
  font-weight:600;
  display:flex;align-items:center;justify-content:center;
  color:#fff;
  flex-shrink:0;
}
.live-item .l-name{font-weight:500;font-size:.85rem;flex:1}
.live-item .l-action{color:var(--text-secondary);font-size:.75rem}
.live-item .l-amount{font-family:var(--font-mono);font-weight:700;color:var(--secondary);font-size:.85rem}
@keyframes fadeFeed{
  from{opacity:0;transform:translateY(-10px)}
  to{opacity:1;transform:translateY(0)}
}

/* ========== PRICING / VIP ========== */
.pricing-grid{
  display:grid;
  grid-template-columns:repeat(5,1fr);
  gap:16px;
  margin-top:50px;
}
.pricing-card{
  background:var(--dark-card);
  border:1px solid var(--dark-border);
  border-radius:var(--radius-lg);
  padding:30px 20px;
  text-align:center;
  transition:all var(--transition);
  position:relative;
  overflow:hidden;
}
.pricing-card.featured{
  border-color:var(--primary);
  transform:scale(1.05);
  box-shadow:0 0 40px rgba(108,92,231,.2);
}
.pricing-card.featured::before{
  content:'Most Popular';
  position:absolute;
  top:16px;right:-32px;
  background:var(--gradient-main);
  color:#fff;
  font-size:.65rem;
  font-weight:700;
  padding:4px 40px;
  transform:rotate(45deg);
}
.pricing-card:hover{transform:translateY(-6px);box-shadow:var(--shadow-glow)}
.pricing-card.featured:hover{transform:scale(1.05) translateY(-6px)}
.pricing-card .p-plan{
  font-size:.75rem;
  text-transform:uppercase;
  letter-spacing:2px;
  color:var(--text-secondary);
  margin-bottom:8px;
  font-weight:600;
}
.pricing-card .p-price{
  font-family:var(--font-display);
  font-size:2.2rem;
  font-weight:700;
  margin-bottom:4px;
}
.pricing-card .p-price span{font-size:1rem;color:var(--text-secondary)}
.pricing-card .p-multiplier{
  display:inline-block;
  padding:4px 12px;
  border-radius:50px;
  background:rgba(108,92,231,.15);
  color:var(--primary);
  font-size:.75rem;
  font-weight:600;
  margin:12px 0;
}
.pricing-card ul{margin:20px 0;text-align:left;padding:0}
.pricing-card ul li{
  padding:8px 0;
  font-size:.82rem;
  color:var(--text-secondary);
  display:flex;
  align-items:center;
  gap:8px;
}
.pricing-card ul li i{color:var(--secondary);font-size:.7rem;width:16px}

/* ========== TESTIMONIALS ========== */
.testimonials-grid{
  display:grid;
  grid-template-columns:repeat(3,1fr);
  gap:20px;
  margin-top:50px;
}
.testimonial-card{
  background:var(--dark-card);
  border:1px solid var(--dark-border);
  border-radius:var(--radius);
  padding:28px 24px;
  transition:all var(--transition);
}
.testimonial-card:hover{transform:translateY(-4px);border-color:rgba(108,92,231,.3)}
.testimonial-card .t-head{
  display:flex;
  align-items:center;
  gap:14px;
  margin-bottom:16px;
}
.testimonial-card .t-avatar{
  width:44px;height:44px;
  border-radius:50%;
  display:flex;align-items:center;justify-content:center;
  font-weight:600;font-size:.85rem;color:#fff;
  flex-shrink:0;
}
.testimonial-card .t-name{font-weight:600;font-size:.9rem}
.testimonial-card .t-earned{font-size:.75rem;color:var(--secondary);font-family:var(--font-mono)}
.testimonial-card .t-stars{color:var(--accent2);font-size:.75rem;margin-bottom:12px}
.testimonial-card .t-text{font-size:.85rem;color:var(--text-secondary);line-height:1.7}

/* ========== REFERRAL ========== */
.referral-grid{
  display:grid;
  grid-template-columns:1fr 1fr;
  gap:60px;
  align-items:center;
  margin-top:50px;
}
.referral-visual{
  text-align:center;
  min-height:400px;
  display:flex;
  align-items:center;
  justify-content:center;
  position:relative;
}
.ref-tree{
  position:relative;
  width:300px;height:300px;
}
.ref-node{
  position:absolute;
  width:64px;height:64px;
  border-radius:50%;
  display:flex;align-items:center;justify-content:center;
  font-size:1.5rem;
  transition:all var(--transition);
}
.ref-node.level0{
  background:var(--gradient-main);
  color:#fff;
  top:50%;left:50%;
  transform:translate(-50%,-50%);
  width:80px;height:80px;
  font-size:2rem;
  box-shadow:0 0 40px rgba(108,92,231,.4);
}
.ref-node.level1{
  background:rgba(108,92,231,.2);
  color:var(--primary);
  border:2px solid rgba(108,92,231,.3);
}
.ref-node.level1:nth-child(2){top:15%;left:20%}
.ref-node.level1:nth-child(3){top:15%;right:20%}
.ref-node.level2{
  background:rgba(0,206,201,.15);
  color:var(--secondary);
  border:2px solid rgba(0,206,201,.2);
  width:48px;height:48px;
  font-size:1rem;
}
.ref-node.level2:nth-child(4){bottom:20%;left:10%}
.ref-node.level2:nth-child(5){bottom:20%;left:38%}
.ref-node.level2:nth-child(6){bottom:20%;right:38%}
.ref-node.level2:nth-child(7){bottom:20%;right:10%}
.ref-lines{
  position:absolute;
  top:0;left:0;
  width:100%;height:100%;
  pointer-events:none;
}
.ref-lines svg{width:100%;height:100%}
.referral-content h3{
  font-family:var(--font-display);
  font-size:1.8rem;
  font-weight:700;
  margin-bottom:16px;
}
.referral-content p{color:var(--text-secondary);margin-bottom:24px;line-height:1.7}
.ref-levels{display:flex;flex-direction:column;gap:12px;margin-bottom:24px}
.ref-level{
  display:flex;align-items:center;gap:16px;
  padding:14px 18px;
  background:var(--dark-card);
  border:1px solid var(--dark-border);
  border-radius:var(--radius-sm);
}
.ref-level .rl-badge{
  padding:4px 10px;
  border-radius:50px;
  font-size:.7rem;
  font-weight:600;
  flex-shrink:0;
}
.ref-level .rl-badge.l1{background:rgba(108,92,231,.15);color:var(--primary)}
.ref-level .rl-badge.l2{background:rgba(0,206,201,.15);color:var(--secondary)}
.ref-level .rl-badge.l3{background:rgba(253,121,168,.15);color:var(--accent)}
.ref-level .rl-pct{font-family:var(--font-display);font-size:1.1rem;font-weight:700;margin-left:auto}
.ref-calc{
  display:flex;gap:10px;
}
.ref-calc input{
  flex:1;
  padding:12px 16px;
  border-radius:50px;
  background:var(--dark-card);
  border:1px solid var(--dark-border);
  color:var(--text-primary);
  font-size:.85rem;
}
.ref-calc input::placeholder{color:var(--text-secondary)}
.ref-result{
  margin-top:12px;
  font-family:var(--font-display);
  font-size:1.2rem;
  font-weight:700;
}
.ref-result span{color:var(--secondary)}

/* ========== FAQ ========== */
.faq-list{
  max-width:700px;
  margin:50px auto 0;
}
.faq-item{
  background:var(--dark-card);
  border:1px solid var(--dark-border);
  border-radius:var(--radius-sm);
  margin-bottom:10px;
  overflow:hidden;
  transition:all var(--transition);
}
.faq-item:hover{border-color:rgba(108,92,231,.2)}
.faq-question{
  width:100%;
  padding:18px 24px;
  background:none;
  display:flex;
  align-items:center;
  justify-content:space-between;
  font-size:.9rem;
  font-weight:500;
  color:var(--text-primary);
  text-align:left;
  gap:16px;
}
.faq-question i{
  font-size:.8rem;
  color:var(--primary);
  transition:transform var(--transition);
  flex-shrink:0;
}
.faq-item.active .faq-question i{transform:rotate(180deg)}
.faq-answer{
  max-height:0;
  overflow:hidden;
  transition:max-height .4s ease,padding .4s ease;
}
.faq-item.active .faq-answer{
  max-height:300px;
  padding:0 24px 18px;
}
.faq-answer p{
  color:var(--text-secondary);
  font-size:.85rem;
  line-height:1.7;
}

/* ========== BLOG ========== */
.blog-grid{
  display:grid;
  grid-template-columns:repeat(3,1fr);
  gap:24px;
  margin-top:50px;
}
.blog-card{
  background:var(--dark-card);
  border:1px solid var(--dark-border);
  border-radius:var(--radius);
  overflow:hidden;
  transition:all var(--transition);
}
.blog-card:hover{transform:translateY(-4px);box-shadow:var(--shadow-glow)}
.blog-card .b-img{
  height:180px;
  background:var(--gradient-main);
  display:flex;align-items:center;justify-content:center;
  font-size:2.5rem;
  opacity:.8;
}
.blog-card .b-body{padding:20px}
.blog-card .b-tag{
  display:inline-block;
  padding:4px 12px;
  border-radius:50px;
  background:rgba(108,92,231,.15);
  color:var(--primary);
  font-size:.7rem;
  font-weight:600;
  margin-bottom:10px;
}
.blog-card h4{font-size:1rem;font-weight:600;margin-bottom:6px;line-height:1.4}
.blog-card p{font-size:.8rem;color:var(--text-secondary);margin-bottom:12px;line-height:1.5}
.blog-card .b-date{font-size:.72rem;color:var(--text-secondary)}

/* ========== NEWSLETTER ========== */
.newsletter-section{
  padding:80px 0;
  background:linear-gradient(135deg,rgba(108,92,231,.1),rgba(0,206,201,.05));
  text-align:center;
}
.newsletter-box{
  max-width:520px;
  margin:0 auto;
}
.newsletter-box h3{
  font-family:var(--font-display);
  font-size:1.8rem;
  font-weight:700;
  margin-bottom:12px;
}
.newsletter-box p{color:var(--text-secondary);margin-bottom:28px}
.newsletter-form{
  display:flex;
  gap:10px;
  background:var(--dark-card);
  border:1px solid var(--dark-border);
  border-radius:50px;
  padding:4px;
}
.newsletter-form input{
  flex:1;
  padding:12px 20px;
  background:none;
  border:none;
  color:var(--text-primary);
  font-size:.9rem;
}
.newsletter-form input::placeholder{color:var(--text-secondary)}
.newsletter-form button{
  padding:12px 28px;
  border-radius:50px;
  background:var(--gradient-main);
  color:#fff;
  font-weight:600;
  font-size:.85rem;
  white-space:nowrap;
  transition:all var(--transition);
}
.newsletter-form button:hover{box-shadow:0 4px 20px rgba(108,92,231,.4)}

/* ========== APP DOWNLOAD ========== */
.download-section{text-align:center;padding:80px 0}
.download-section h3{
  font-family:var(--font-display);
  font-size:1.8rem;
  font-weight:700;
  margin-bottom:12px;
}
.download-section p{color:var(--text-secondary);margin-bottom:30px}
.download-badges{
  display:flex;
  gap:16px;
  justify-content:center;
  flex-wrap:wrap;
}
.download-badge{
  display:flex;
  align-items:center;
  gap:12px;
  padding:14px 28px;
  background:var(--dark-card);
  border:1px solid var(--dark-border);
  border-radius:var(--radius);
  transition:all var(--transition);
}
.download-badge:hover{transform:translateY(-3px);border-color:var(--primary)}
.download-badge i{font-size:2rem}
.download-badge .db-label{font-size:.65rem;color:var(--text-secondary);text-transform:uppercase;letter-spacing:1px}
.download-badge .db-name{font-weight:600;font-size:.95rem}

/* ========== FOOTER ========== */
.footer{
  background:var(--dark-surface);
  border-top:1px solid var(--dark-border);
  padding:80px 0 0;
  position:relative;
  overflow:hidden;
}
.footer::before{
  content:'';
  position:absolute;
  top:0;left:0;right:0;
  height:1px;
  background:linear-gradient(90deg,transparent,var(--primary),var(--secondary),transparent);
}
.footer-grid{
  display:grid;
  grid-template-columns:2fr 1fr 1fr 1fr 1fr;
  gap:30px;
  margin-bottom:50px;
}
.footer-brand .nav-logo{margin-bottom:14px}
.footer-brand p{font-size:.85rem;color:var(--text-secondary);line-height:1.7;max-width:300px}
.footer-col h5{
  font-size:.85rem;
  font-weight:600;
  margin-bottom:20px;
  text-transform:uppercase;
  letter-spacing:1px;
}
.footer-col ul li{margin-bottom:10px}
.footer-col ul li a{
  font-size:.83rem;
  color:var(--text-secondary);
  transition:color var(--transition);
}
.footer-col ul li a:hover{color:var(--primary)}
.footer-social{
  display:flex;
  gap:12px;
  margin-top:20px;
}
.footer-social a{
  width:40px;height:40px;
  border-radius:50%;
  display:flex;align-items:center;justify-content:center;
  background:var(--glass);
  border:1px solid var(--glass-border);
  color:var(--text-secondary);
  transition:all var(--transition);
  font-size:.9rem;
}
.footer-social a:hover{background:var(--gradient-main);color:#fff;border-color:transparent}
.footer-bottom{
  display:flex;
  align-items:center;
  justify-content:space-between;
  padding:24px 0;
  border-top:1px solid var(--glass-border);
  font-size:.8rem;
  color:var(--text-secondary);
}
.footer-bottom .payment-icons{display:flex;gap:12px;font-size:1.4rem;color:var(--text-secondary)}
.footer-bottom .payment-icons i{transition:color var(--transition)}
.footer-bottom .payment-icons i:hover{color:var(--primary)}

/* ========== BACK TO TOP ========== */
.back-to-top{
  position:fixed;
  bottom:30px;right:30px;
  z-index:99;
  width:44px;height:44px;
  border-radius:50%;
  background:var(--gradient-main);
  color:#fff;
  display:flex;align-items:center;justify-content:center;
  font-size:1rem;
  box-shadow:0 4px 20px rgba(108,92,231,.4);
  opacity:0;
  visibility:hidden;
  transform:translateY(20px);
  transition:all var(--transition);
}
.back-to-top.visible{opacity:1;visibility:visible;transform:translateY(0)}
.back-to-top:hover{transform:translateY(-3px);box-shadow:0 8px 30px rgba(108,92,231,.5)}

/* ========== MOBILE FLOATING CTA ========== */
.mobile-cta{
  display:none;
  position:fixed;
  bottom:0;left:0;right:0;
  z-index:99;
  padding:12px 20px;
  background:rgba(10,10,26,.95);
  backdrop-filter:blur(20px);
  border-top:1px solid var(--glass-border);
}
.mobile-cta .btn{width:100%;justify-content:center}
[data-theme="light"] .mobile-cta{background:rgba(255,255,255,.95)}

/* ========== RESPONSIVE ========== */
@media(max-width:1024px){
  .features-grid{grid-template-columns:repeat(3,1fr)}
  .pricing-grid{grid-template-columns:repeat(3,1fr)}
  .pricing-card.featured{transform:scale(1)}
  .pricing-card.featured:hover{transform:translateY(-6px)}
  .footer-grid{grid-template-columns:1fr 1fr;gap:30px}
  .hero .container{grid-template-columns:1fr}
  .hero-visual{min-height:400px}
  .testimonials-grid{grid-template-columns:1fr 1fr}
}
@media(max-width:768px){
  .nav-links{display:none}
  .nav-actions .btn{display:none}
  .hamburger{display:flex}
  .nav-links.open{
    display:flex;
    flex-direction:column;
    position:absolute;
    top:100%;left:0;right:0;
    background:rgba(10,10,26,.98);
    backdrop-filter:blur(20px);
    padding:20px;
    border-bottom:1px solid var(--glass-border);
    gap:16px;
    align-items:stretch;
  }
  [data-theme="light"] .nav-links.open{background:rgba(255,255,255,.98)}
  .nav-links.open a{padding:8px 0}
  .nav-links.open .btn{display:flex;justify-content:center}
  .hero h1{font-size:2rem}
  .hero-stats{gap:20px;flex-wrap:wrap}
  .hero-stat h3{font-size:1.2rem}
  .hero-floating-card{width:140px;padding:14px}
  .hero-floating-card:nth-child(1){top:5%;right:2%}
  .hero-floating-card:nth-child(2){top:35%;left:0%}
  .hero-floating-card:nth-child(3){bottom:20%;right:5%}
  .features-grid{grid-template-columns:1fr 1fr}
  .how-steps{grid-template-columns:1fr 1fr}
  .how-steps::before{display:none}
  .methods-grid{grid-template-columns:1fr}
  .stats-grid{grid-template-columns:1fr}
  .pricing-grid{grid-template-columns:1fr;max-width:380px;margin:50px auto 0}
  .testimonials-grid{grid-template-columns:1fr}
  .referral-grid{grid-template-columns:1fr}
  .blog-grid{grid-template-columns:1fr}
  .footer-grid{grid-template-columns:1fr 1fr}
  .footer-bottom{flex-direction:column;gap:12px;text-align:center}
  .floating-toasts{display:none}
  .mobile-cta{display:block}
  .back-to-top{bottom:80px}
  .section{padding:60px 0}
  .live-activity{margin:0 10px}
}
@media(max-width:480px){
  .features-grid{grid-template-columns:1fr}
  .how-steps{grid-template-columns:1fr}
  .hero-buttons{flex-direction:column}
  .hero-buttons .btn{width:100%;justify-content:center}
  .trust-track{gap:30px}
}
</style>
</head>
<body data-theme="dark">

<!-- ==================== PRELOADER ==================== -->
<div id="preloader">
  <div class="preloader-logo"><i class="fas fa-rocket"></i> ZynEarn</div>
  <div class="preloader-sub">Loading amazing experience...</div>
  <div class="preloader-bar-wrap"><div class="preloader-bar" id="preloader-bar"></div></div>
  <div class="preloader-percent" id="preloader-percent">0%</div>
</div>

<!-- ==================== FLOATING TOASTS ==================== -->
<div class="floating-toasts" id="floatingToasts"></div>

<!-- ==================== NAVBAR ==================== -->
<nav class="navbar" id="navbar">
  <div class="container">
    <a href="#" class="nav-logo"><i class="fas fa-rocket"></i> ZynEarn</a>
    <div class="nav-links" id="navLinks">
      <a href="#home">Home</a>
      <a href="#features">Features</a>
      <a href="#how">How It Works</a>
      <a href="#earnings">Earnings</a>
      <a href="#testimonials">Testimonials</a>
      <a href="#faq">FAQ</a>
      <a href="#contact">Contact</a>
      <a href="/auth/login.php" class="btn btn-primary btn-sm">Login <i class="fas fa-arrow-right"></i></a>
    </div>
    <div class="nav-actions">
      <button class="theme-toggle" id="themeToggle" aria-label="Toggle theme"><i class="fas fa-moon"></i></button>
      <a href="/auth/login.php" class="btn btn-primary btn-sm desktop-only">Get Started <i class="fas fa-arrow-right"></i></a>
      <button class="hamburger" id="hamburger" aria-label="Menu">
        <span></span><span></span><span></span>
      </button>
    </div>
  </div>
</nav>

<!-- ==================== HERO ==================== -->
<section class="hero" id="home">
  <canvas id="particles-canvas"></canvas>
  <div class="hero-bg-gradient"></div>
  <div class="container">
    <div class="hero-content">
      <div class="hero-badge"><i class="fas fa-circle"></i> Live &bull; 2,847 earning now</div>
      <h1>Turn Your Time<br>Into <span class="gradient-text" id="typedText">Real Money</span><span class="typing-cursor">|</span></h1>
      <p>Join the premium earning platform where thousands complete tasks, surveys, offers and more. Start earning cash rewards instantly — no experience needed.</p>
      <div class="hero-buttons">
        <a href="/auth/register.php" class="btn btn-primary btn-glow">Start Earning Now <i class="fas fa-rocket"></i></a>
        <a href="#features" class="btn btn-outline">Learn More <i class="fas fa-chevron-right"></i></a>
      </div>
      <div class="hero-stats">
        <div class="hero-stat"><h3 class="counter" data-target="2480000" data-prefix="$" data-suffix="+">$0</h3><p>Total Paid</p></div>
        <div class="hero-stat"><h3 class="counter" data-target="50000" data-suffix="+">0</h3><p>Total Users</p></div>
        <div class="hero-stat"><h3 class="counter" data-target="2847">0</h3><p>Active Now</p></div>
      </div>
    </div>
    <div class="hero-visual">
      <div class="hero-floating-card">
        <div class="card-icon purple"><i class="fas fa-tasks"></i></div>
        <h4>Tasks Completed</h4>
        <p>Daily earnings available</p>
        <div class="amount">$12.50</div>
      </div>
      <div class="hero-floating-card">
        <div class="card-icon green"><i class="fas fa-users"></i></div>
        <h4>Referral Bonus</h4>
        <p>Invite & earn 20%</p>
        <div class="amount">$8.40</div>
      </div>
      <div class="hero-floating-card">
        <div class="card-icon pink"><i class="fas fa-gift"></i></div>
        <h4>Daily Bonus</h4>
        <p>Claim every day</p>
        <div class="amount">$0.50</div>
      </div>
      <div class="hero-notification">
        <div class="avatar">S</div>
        <span>Sarah just earned <strong>$4.20</strong> from surveys</span>
      </div>
    </div>
  </div>
</section>

<!-- ==================== TRUST BADGES ==================== -->
<section class="trust-section">
  <div class="container">
    <div class="trust-label">Trusted by thousands worldwide</div>
    <div class="trust-track">
      <div class="trust-item"><i class="fas fa-shield-alt" style="color:#6c5ce7"></i> Secure Payments</div>
      <div class="trust-item"><i class="fas fa-bolt" style="color:#00cec9"></i> Instant Withdrawals</div>
      <div class="trust-item"><i class="fas fa-headset" style="color:#fd79a8"></i> 24/7 Support</div>
      <div class="trust-item"><span class="big">150+</span> Countries</div>
      <div class="trust-item"><span class="big">4.9★</span> Trustpilot</div>
      <div class="trust-item"><span class="big">99.9%</span> Uptime</div>
    </div>
  </div>
</section>

<!-- ==================== FEATURES ==================== -->
<section class="section" id="features">
  <div class="container">
    <div style="text-align:center;margin-bottom:10px">
      <span class="section-label">Features</span>
      <h2 class="section-title">Earn Money <span class="gradient-text">Your Way</span></h2>
      <p class="section-subtitle" style="margin:0 auto">Choose from dozens of earning methods that suit your style and schedule.</p>
    </div>
    <div class="features-grid">
      <div class="feature-card"><div class="f-icon"><i class="fas fa-table-cells-large"></i></div><h3>Offerwall</h3><p>Complete offers and surveys from top advertisers worldwide.</p></div>
      <div class="feature-card"><div class="f-icon"><i class="fas fa-link"></i></div><h3>Shortlinks</h3><p>Earn by shortening and sharing links across your network.</p></div>
      <div class="feature-card"><div class="f-icon"><i class="fas fa-water"></i></div><h3>Faucet</h3><p>Claim free rewards every few minutes with our crypto faucet.</p></div>
      <div class="feature-card"><div class="f-icon"><i class="fas fa-spinner"></i></div><h3>Spin Wheel</h3><p>Try your luck and win big prizes on the daily spin wheel.</p></div>
      <div class="feature-card"><div class="f-icon"><i class="fas fa-rectangle-ad"></i></div><h3>Scratch Cards</h3><p>Scratch to reveal instant cash rewards and bonuses.</p></div>
      <div class="feature-card"><div class="f-icon"><i class="fas fa-question-circle"></i></div><h3>Quizzes</h3><p>Test your knowledge and earn rewards for correct answers.</p></div>
      <div class="feature-card"><div class="f-icon"><i class="fas fa-poll"></i></div><h3>Surveys</h3><p>Share your opinion and get paid for market research surveys.</p></div>
      <div class="feature-card"><div class="f-icon"><i class="fas fa-check-double"></i></div><h3>Tasks</h3><p>Complete simple tasks like signups, downloads, and more.</p></div>
      <div class="feature-card"><div class="f-icon"><i class="fas fa-video"></i></div><h3>Videos</h3><p>Watch video ads and earn rewards for your attention.</p></div>
      <div class="feature-card"><div class="f-icon"><i class="fas fa-user-friends"></i></div><h3>Referrals</h3><p>Invite friends and earn a lifetime commission on their earnings.</p></div>
      <div class="feature-card"><div class="f-icon"><i class="fas fa-calendar-check"></i></div><h3>Daily Bonus</h3><p>Claim your daily streak bonus and never miss a day.</p></div>
      <div class="feature-card"><div class="f-icon"><i class="fas fa-percentage"></i></div><h3>Cashback</h3><p>Get cashback on purchases from partner stores and services.</p></div>
    </div>
  </div>
</section>

<!-- ==================== HOW IT WORKS ==================== -->
<section class="section" id="how" style="background:rgba(108,92,231,.02)">
  <div class="container">
    <div style="text-align:center;margin-bottom:10px">
      <span class="section-label">How It Works</span>
      <h2 class="section-title">Start Earning in <span class="gradient-text">4 Simple Steps</span></h2>
      <p class="section-subtitle" style="margin:0 auto">Getting started takes just minutes. Here's how it works.</p>
    </div>
    <div class="how-steps">
      <div class="step-card">
        <div class="step-number"><span class="step-icon"><i class="fas fa-user-plus"></i></span> 1</div>
        <h3>Sign Up Free</h3>
        <p>Create your account in seconds. No credit card required — just an email and you're in.</p>
      </div>
      <div class="step-card">
        <div class="step-number"><span class="step-icon"><i class="fas fa-tasks"></i></span> 2</div>
        <h3>Complete Tasks</h3>
        <p>Choose from hundreds of tasks, surveys, offers and earning opportunities available daily.</p>
      </div>
      <div class="step-card">
        <div class="step-number"><span class="step-icon"><i class="fas fa-coins"></i></span> 3</div>
        <h3>Earn Rewards</h3>
        <p>Watch your balance grow in real-time as you complete each task. Transparent tracking.</p>
      </div>
      <div class="step-card">
        <div class="step-number"><span class="step-icon"><i class="fas fa-hand-holding-usd"></i></span> 4</div>
        <h3>Withdraw Instantly</h3>
        <p>Cash out your earnings via PayPal, crypto, gift cards, or bank transfer — instantly.</p>
      </div>
    </div>
  </div>
</section>

<!-- ==================== EARNING METHODS (TABS) ==================== -->
<section class="section" id="earnings">
  <div class="container">
    <div style="text-align:center;margin-bottom:10px">
      <span class="section-label">Earning Methods</span>
      <h2 class="section-title">Choose How <span class="gradient-text">You Earn</span></h2>
      <p class="section-subtitle" style="margin:0 auto">Diverse earning opportunities tailored to your preferences.</p>
    </div>
    <div class="tabs-nav" id="tabsNav">
      <button class="tab-btn active" data-tab="all">All</button>
      <button class="tab-btn" data-tab="offers">Offers</button>
      <button class="tab-btn" data-tab="tasks">Tasks</button>
      <button class="tab-btn" data-tab="games">Games</button>
      <button class="tab-btn" data-tab="surveys">Surveys</button>
      <button class="tab-btn" data-tab="referrals">Referrals</button>
    </div>
    <div class="tab-content active" data-content="all">
      <div class="methods-grid">
        <div class="method-card"><div class="m-icon purple"><i class="fas fa-table-cells-large"></i></div><div><h4>Offerwall</h4><p>Complete offers from top brands</p><span class="earnings">Up to $50/offer</span></div></div>
        <div class="method-card"><div class="m-icon green"><i class="fas fa-link"></i></div><div><h4>Shortlinks</h4><p>Earn per link click</p><span class="earnings">$0.50-$2/click</span></div></div>
        <div class="method-card"><div class="m-icon pink"><i class="fas fa-water"></i></div><div><h4>Faucet</h4><p>Claim every 5 minutes</p><span class="earnings">Up to $0.10/claim</span></div></div>
        <div class="method-card"><div class="m-icon orange"><i class="fas fa-spinner"></i></div><div><h4>Spin Wheel</h4><p>Daily lucky spin</p><span class="earnings">Up to $100/win</span></div></div>
        <div class="method-card"><div class="m-icon blue"><i class="fas fa-rectangle-ad"></i></div><div><h4>Scratch Cards</h4><p>Instant win scratch</p><span class="earnings">Up to $25/card</span></div></div>
        <div class="method-card"><div class="m-icon purple"><i class="fas fa-poll"></i></div><div><h4>Surveys</h4><p>Paid market research</p><span class="earnings">$1-$15/survey</span></div></div>
        <div class="method-card"><div class="m-icon green"><i class="fas fa-check-double"></i></div><div><h4>Tasks</h4><p>Simple signups & downloads</p><span class="earnings">$0.50-$10/task</span></div></div>
        <div class="method-card"><div class="m-icon pink"><i class="fas fa-video"></i></div><div><h4>Videos</h4><p>Watch & earn</p><span class="earnings">$0.01-$0.10/video</span></div></div>
        <div class="method-card"><div class="m-icon orange"><i class="fas fa-user-friends"></i></div><div><h4>Referrals</h4><p>Lifetime commission</p><span class="earnings">20% forever</span></div></div>
      </div>
    </div>
    <div class="tab-content" data-content="offers">
      <div class="methods-grid">
        <div class="method-card"><div class="m-icon purple"><i class="fas fa-table-cells-large"></i></div><div><h4>Featured Offers</h4><p>Premium offers with high payouts</p><span class="earnings">Up to $50/offer</span></div></div>
        <div class="method-card"><div class="m-icon green"><i class="fas fa-gamepad"></i></div><div><h4>Game Offers</h4><p>Download & play games</p><span class="earnings">Up to $30/offer</span></div></div>
        <div class="method-card"><div class="m-icon pink"><i class="fas fa-credit-card"></i></div><div><h4>Financial Offers</h4><p>Credit cards, loans & more</p><span class="earnings">Up to $100/offer</span></div></div>
      </div>
    </div>
    <div class="tab-content" data-content="tasks">
      <div class="methods-grid">
        <div class="method-card"><div class="m-icon purple"><i class="fas fa-check-double"></i></div><div><h4>Simple Tasks</h4><p>Quick signups & verifications</p><span class="earnings">$0.50-$2/task</span></div></div>
        <div class="method-card"><div class="m-icon green"><i class="fas fa-download"></i></div><div><h4>App Downloads</h4><p>Install and try apps</p><span class="earnings">$1-$5/task</span></div></div>
        <div class="method-card"><div class="m-icon blue"><i class="fas fa-globe"></i></div><div><h4>Website Visits</h4><p>Visit & explore websites</p><span class="earnings">$0.10-$1/task</span></div></div>
      </div>
    </div>
    <div class="tab-content" data-content="games">
      <div class="methods-grid">
        <div class="method-card"><div class="m-icon orange"><i class="fas fa-spinner"></i></div><div><h4>Spin Wheel</h4><p>Try your luck daily</p><span class="earnings">Up to $100/win</span></div></div>
        <div class="method-card"><div class="m-icon pink"><i class="fas fa-rectangle-ad"></i></div><div><h4>Scratch Cards</h4><p>Instant win scratch cards</p><span class="earnings">Up to $25/card</span></div></div>
        <div class="method-card"><div class="m-icon green"><i class="fas fa-dice"></i></div><div><h4>Lucky Draw</h4><p>Monthly prize drawings</p><span class="earnings">Up to $500</span></div></div>
      </div>
    </div>
    <div class="tab-content" data-content="surveys">
      <div class="methods-grid">
        <div class="method-card"><div class="m-icon purple"><i class="fas fa-poll"></i></div><div><h4>Market Surveys</h4><p>Share your opinion</p><span class="earnings">$1-$15/survey</span></div></div>
        <div class="method-card"><div class="m-icon blue"><i class="fas fa-clipboard-list"></i></div><div><h4>Product Testing</h4><p>Test & review products</p><span class="earnings">$5-$25/survey</span></div></div>
        <div class="method-card"><div class="m-icon orange"><i class="fas fa-video"></i></div><div><h4>Video Surveys</h4><p>Record your feedback</p><span class="earnings">$10-$50/survey</span></div></div>
      </div>
    </div>
    <div class="tab-content" data-content="referrals">
      <div class="methods-grid">
        <div class="method-card"><div class="m-icon pink"><i class="fas fa-user-friends"></i></div><div><h4>Direct Referrals</h4><p>Earn from your referrals</p><span class="earnings">20% commission</span></div></div>
        <div class="method-card"><div class="m-icon green"><i class="fas fa-tree"></i></div><div><h4>Sub-Referrals</h4><p>Earn from their referrals too</p><span class="earnings">5% commission</span></div></div>
        <div class="method-card"><div class="m-icon purple"><i class="fas fa-trophy"></i></div><div><h4>Top Referrer Bonus</h4><p>Monthly leaderboard prizes</p><span class="earnings">Up to $1,000</span></div></div>
      </div>
    </div>
  </div>
</section>

<!-- ==================== REAL-TIME STATS ==================== -->
<section class="section stats-section" id="stats">
  <div class="container">
    <div style="text-align:center;margin-bottom:10px">
      <span class="section-label">Live Stats</span>
      <h2 class="section-title">Real-Time <span class="gradient-text">Platform Stats</span></h2>
      <p class="section-subtitle" style="margin:0 auto">See how our community is growing and earning in real-time.</p>
    </div>
    <div class="stats-grid">
      <div class="stat-card">
        <span class="stat-icon">💰</span>
        <h3 class="counter" data-target="2400000" data-prefix="$" data-suffix="+">$0</h3>
        <p>Total Paid to Members</p>
      </div>
      <div class="stat-card">
        <span class="stat-icon">👥</span>
        <h3 class="counter" data-target="50000" data-suffix="+">0</h3>
        <p>Registered Users</p>
      </div>
      <div class="stat-card">
        <span class="stat-icon">✅</span>
        <h3 class="counter" data-target="1200000" data-suffix="+">0</h3>
        <p>Tasks Completed</p>
      </div>
    </div>
    <div class="live-activity">
      <h4><i class="fas fa-circle"></i> Live Activity Feed</h4>
      <div class="live-feed" id="liveFeed">
        <div class="live-item">
          <div class="l-avatar" style="background:#6c5ce7">M</div>
          <span class="l-name">Mike R.</span>
          <span class="l-action">completed survey</span>
          <span class="l-amount">+$3.50</span>
        </div>
        <div class="live-item">
          <div class="l-avatar" style="background:#00cec9">J</div>
          <span class="l-name">Jessica K.</span>
          <span class="l-action">claimed daily bonus</span>
          <span class="l-amount">+$0.75</span>
        </div>
        <div class="live-item">
          <div class="l-avatar" style="background:#fd79a8">A</div>
          <span class="l-name">Alex T.</span>
          <span class="l-action">completed offer</span>
          <span class="l-amount">+$12.00</span>
        </div>
        <div class="live-item">
          <div class="l-avatar" style="background:#fdcb6e">S</div>
          <span class="l-name">Sarah L.</span>
          <span class="l-action">withdrawn to PayPal</span>
          <span class="l-amount">$25.00</span>
        </div>
        <div class="live-item">
          <div class="l-avatar" style="background:#74b9ff">D</div>
          <span class="l-name">David W.</span>
          <span class="l-action">referred a friend</span>
          <span class="l-amount">+$2.00</span>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ==================== VIP / PRICING ==================== -->
<section class="section" id="pricing">
  <div class="container">
    <div style="text-align:center;margin-bottom:10px">
      <span class="section-label">VIP Membership</span>
      <h2 class="section-title">Unlock <span class="gradient-text">Premium Earnings</span></h2>
      <p class="section-subtitle" style="margin:0 auto">Upgrade your membership for higher earnings multipliers and exclusive perks.</p>
    </div>
    <div class="pricing-grid">
      <div class="pricing-card">
        <div class="p-plan">Free</div>
        <div class="p-price">$0<span>/forever</span></div>
        <div class="p-multiplier">1x Earnings</div>
        <ul>
          <li><i class="fas fa-check"></i> Basic tasks access</li>
          <li><i class="fas fa-check"></i> Standard offers</li>
          <li><i class="fas fa-check"></i> Daily bonus</li>
          <li><i class="fas fa-times" style="color:#ff7675"></i> Priority support</li>
          <li><i class="fas fa-times" style="color:#ff7675"></i> Exclusive offers</li>
        </ul>
        <a href="/auth/register.php" class="btn btn-outline" style="width:100%;justify-content:center">Get Started</a>
      </div>
      <div class="pricing-card">
        <div class="p-plan">Silver</div>
        <div class="p-price">$5<span>/month</span></div>
        <div class="p-multiplier">1.5x Earnings</div>
        <ul>
          <li><i class="fas fa-check"></i> All free features</li>
          <li><i class="fas fa-check"></i> Silver offers</li>
          <li><i class="fas fa-check"></i> Priority withdrawals</li>
          <li><i class="fas fa-check"></i> Email support</li>
          <li><i class="fas fa-times" style="color:#ff7675"></i> Exclusive offers</li>
        </ul>
        <a href="/auth/register.php" class="btn btn-outline" style="width:100%;justify-content:center">Upgrade</a>
      </div>
      <div class="pricing-card featured">
        <div class="p-plan">Gold</div>
        <div class="p-price">$15<span>/month</span></div>
        <div class="p-multiplier">2.5x Earnings</div>
        <ul>
          <li><i class="fas fa-check"></i> All silver features</li>
          <li><i class="fas fa-check"></i> Gold exclusive offers</li>
          <li><i class="fas fa-check"></i> Instant withdrawals</li>
          <li><i class="fas fa-check"></i> Live chat support</li>
          <li><i class="fas fa-check"></i> Weekly bonus</li>
        </ul>
        <a href="/auth/register.php" class="btn btn-primary" style="width:100%;justify-content:center">Upgrade Now</a>
      </div>
      <div class="pricing-card">
        <div class="p-plan">Platinum</div>
        <div class="p-price">$50<span>/month</span></div>
        <div class="p-multiplier">4x Earnings</div>
        <ul>
          <li><i class="fas fa-check"></i> All gold features</li>
          <li><i class="fas fa-check"></i> Platinum offers</li>
          <li><i class="fas fa-check"></i> VIP support</li>
          <li><i class="fas fa-check"></i> Monthly cashback</li>
          <li><i class="fas fa-check"></i> Early access</li>
        </ul>
        <a href="/auth/register.php" class="btn btn-outline" style="width:100%;justify-content:center">Upgrade</a>
      </div>
      <div class="pricing-card">
        <div class="p-plan">VIP</div>
        <div class="p-price">$100<span>/month</span></div>
        <div class="p-multiplier">6x Earnings</div>
        <ul>
          <li><i class="fas fa-check"></i> All platinum features</li>
          <li><i class="fas fa-check"></i> VIP exclusive offers</li>
          <li><i class="fas fa-check"></i> Dedicated manager</li>
          <li><i class="fas fa-check"></i> 2x referral bonus</li>
          <li><i class="fas fa-check"></i> All access pass</li>
        </ul>
        <a href="/auth/register.php" class="btn btn-outline" style="width:100%;justify-content:center">Join VIP</a>
      </div>
    </div>
  </div>
</section>

<!-- ==================== TESTIMONIALS ==================== -->
<section class="section" id="testimonials" style="background:rgba(108,92,231,.02)">
  <div class="container">
    <div style="text-align:center;margin-bottom:10px">
      <span class="section-label">Testimonials</span>
      <h2 class="section-title">What Our <span class="gradient-text">Members Say</span></h2>
      <p class="section-subtitle" style="margin:0 auto">Join thousands of satisfied earners who turned their time into real money.</p>
    </div>
    <div class="testimonials-grid" id="testimonialsGrid">
      <div class="testimonial-card">
        <div class="t-head">
          <div class="t-avatar" style="background:#6c5ce7">SM</div>
          <div><div class="t-name">Sarah Mitchell</div><div class="t-earned">Earned $2,450</div></div>
        </div>
        <div class="t-stars"><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i></div>
        <div class="t-text">"I never thought earning money online could be this easy. ZynEarn has completely changed my financial situation. Highly recommended!"</div>
      </div>
      <div class="testimonial-card">
        <div class="t-head">
          <div class="t-avatar" style="background:#00cec9">JD</div>
          <div><div class="t-name">James D.</div><div class="t-earned">Earned $1,870</div></div>
        </div>
        <div class="t-stars"><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i></div>
        <div class="t-text">"The referral program is amazing. I've been earning passive income every month just by sharing my link. Truly a game-changer!"</div>
      </div>
      <div class="testimonial-card">
        <div class="t-head">
          <div class="t-avatar" style="background:#fd79a8">EL</div>
          <div><div class="t-name">Emma L.</div><div class="t-earned">Earned $3,200</div></div>
        </div>
        <div class="t-stars"><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i></div>
        <div class="t-text">"I've tried many platforms but ZynEarn is by far the best. Fast withdrawals, great support, and so many ways to earn. Absolutely love it!"</div>
      </div>
      <div class="testimonial-card">
        <div class="t-head">
          <div class="t-avatar" style="background:#fdcb6e">MK</div>
          <div><div class="t-name">Michael K.</div><div class="t-earned">Earned $5,100</div></div>
        </div>
        <div class="t-stars"><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i></div>
        <div class="t-text">"Gold membership pays for itself in days. The 2.5x multiplier is insane. Making $50+ daily now with minimal effort. Thank you ZynEarn!"</div>
      </div>
      <div class="testimonial-card">
        <div class="t-head">
          <div class="t-avatar" style="background:#74b9ff">AR</div>
          <div><div class="t-name">Anna R.</div><div class="t-earned">Earned $980</div></div>
        </div>
        <div class="t-stars"><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i></div>
        <div class="t-text">"Perfect for students! I earn enough for my daily expenses just by completing surveys and tasks between classes. Best decision ever."</div>
      </div>
      <div class="testimonial-card">
        <div class="t-head">
          <div class="t-avatar" style="background:#e17055">CW</div>
          <div><div class="t-name">Chris W.</div><div class="t-earned">Earned $7,800</div></div>
        </div>
        <div class="t-stars"><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i></div>
        <div class="t-text">"VIP membership is absolutely worth it. The 6x multiplier combined with dedicated support makes this a serious income source. Highly recommended!"</div>
      </div>
    </div>
  </div>
</section>

<!-- ==================== REFERRAL ==================== -->
<section class="section" id="referral">
  <div class="container">
    <div style="text-align:center;margin-bottom:10px">
      <span class="section-label">Referral Program</span>
      <h2 class="section-title">Invite Friends, <span class="gradient-text">Earn Forever</span></h2>
      <p class="section-subtitle" style="margin:0 auto">Build your referral network and earn commissions for life.</p>
    </div>
    <div class="referral-grid">
      <div class="referral-visual">
        <div class="ref-tree">
          <div class="ref-node level0"><i class="fas fa-user"></i></div>
          <div class="ref-node level1" style="top:15%;left:20%"><i class="fas fa-user-friends"></i></div>
          <div class="ref-node level1" style="top:15%;right:20%"><i class="fas fa-user-friends"></i></div>
          <div class="ref-node level2" style="bottom:20%;left:10%"><i class="fas fa-user"></i></div>
          <div class="ref-node level2" style="bottom:20%;left:38%"><i class="fas fa-user"></i></div>
          <div class="ref-node level2" style="bottom:20%;right:38%"><i class="fas fa-user"></i></div>
          <div class="ref-node level2" style="bottom:20%;right:10%"><i class="fas fa-user"></i></div>
          <svg class="ref-lines" viewBox="0 0 300 300">
            <line x1="150" y1="140" x2="90" y2="60" stroke="rgba(108,92,231,.2)" stroke-width="1.5"/>
            <line x1="150" y1="140" x2="210" y2="60" stroke="rgba(108,92,231,.2)" stroke-width="1.5"/>
            <line x1="90" y1="60" x2="45" y2="200" stroke="rgba(0,206,201,.15)" stroke-width="1"/>
            <line x1="90" y1="60" x2="110" y2="200" stroke="rgba(0,206,201,.15)" stroke-width="1"/>
            <line x1="210" y1="60" x2="195" y2="200" stroke="rgba(0,206,201,.15)" stroke-width="1"/>
            <line x1="210" y1="60" x2="260" y2="200" stroke="rgba(0,206,201,.15)" stroke-width="1"/>
          </svg>
        </div>
      </div>
      <div class="referral-content">
        <h3>Earn 20% Commission <span class="gradient-text">For Life</span></h3>
        <p>Share your unique referral link with friends and earn 20% of everything they earn — plus 5% from their referrals too. It's passive income that keeps growing.</p>
        <div class="ref-levels">
          <div class="ref-level"><span class="rl-badge l1">Level 1</span> Direct Referrals <span class="rl-pct gradient-text">20%</span></div>
          <div class="ref-level"><span class="rl-badge l2">Level 2</span> Sub-Referrals <span class="rl-pct gradient-text">5%</span></div>
          <div class="ref-level"><span class="rl-badge l3">Level 3</span> Top Referrer Bonus <span class="rl-pct gradient-text">$1,000</span></div>
        </div>
        <div class="ref-calc">
          <input type="number" id="refInput" placeholder="Enter referrals count..." min="1">
          <button class="btn btn-primary" id="refCalcBtn">Calculate</button>
        </div>
        <div class="ref-result">Your potential monthly earnings: <span id="refResult">$0</span></div>
      </div>
    </div>
  </div>
</section>

<!-- ==================== FAQ ==================== -->
<section class="section" id="faq" style="background:rgba(108,92,231,.02)">
  <div class="container">
    <div style="text-align:center;margin-bottom:10px">
      <span class="section-label">FAQ</span>
      <h2 class="section-title">Frequently Asked <span class="gradient-text">Questions</span></h2>
      <p class="section-subtitle" style="margin:0 auto">Everything you need to know about ZynEarn.</p>
    </div>
    <div class="faq-list">
      <div class="faq-item active">
        <button class="faq-question">What is ZynEarn? <i class="fas fa-chevron-down"></i></button>
        <div class="faq-answer"><p>ZynEarn is a premium online earning platform where users complete tasks, surveys, offers, and other activities to earn real money. We connect you with advertisers and businesses who pay for your attention and actions.</p></div>
      </div>
      <div class="faq-item">
        <button class="faq-question">Is ZynEarn free to join? <i class="fas fa-chevron-down"></i></button>
        <div class="faq-answer"><p>Yes! Creating a ZynEarn account is completely free. You can start earning immediately without any upfront payment. We also offer premium membership plans for users who want to maximize their earnings.</p></div>
      </div>
      <div class="faq-item">
        <button class="faq-question">How do I get paid? <i class="fas fa-chevron-down"></i></button>
        <div class="faq-answer"><p>You can withdraw your earnings via PayPal, cryptocurrency (Bitcoin, Ethereum, USDT), gift cards (Amazon, Google Play, Steam), or direct bank transfer. Minimum withdrawal starts at just $1.</p></div>
      </div>
      <div class="faq-item">
        <button class="faq-question">How much can I earn? <i class="fas fa-chevron-down"></i></button>
        <div class="faq-answer"><p>Earnings vary based on the methods you use and your membership level. Free members can earn $5-20/day, while VIP members earn $50-200+/day. Many top earners make over $1,000/month.</p></div>
      </div>
      <div class="faq-item">
        <button class="faq-question">How long do withdrawals take? <i class="fas fa-chevron-down"></i></button>
        <div class="faq-answer"><p>Withdrawals are processed instantly for Gold members and above. Free and Silver members typically receive their funds within 24-48 hours. We prioritize fast payouts for all members.</p></div>
      </div>
      <div class="faq-item">
        <button class="faq-question">Is ZynEarn available worldwide? <i class="fas fa-chevron-down"></i></button>
        <div class="faq-answer"><p>Yes! ZynEarn is available in over 150 countries worldwide. Some offers and surveys may be region-specific, but we constantly add new opportunities for all geographic locations.</p></div>
      </div>
      <div class="faq-item">
        <button class="faq-question">Can I refer friends? <i class="fas fa-chevron-down"></i></button>
        <div class="faq-answer"><p>Absolutely! Our referral program lets you earn 20% commission on your direct referrals' earnings for life, plus 5% on their referrals. Top referrers also win monthly bonus prizes of up to $1,000.</p></div>
      </div>
      <div class="faq-item">
        <button class="faq-question">Is my data safe? <i class="fas fa-chevron-down"></i></button>
        <div class="faq-answer"><p>We take security seriously. Your personal data is encrypted and protected using industry-standard SSL technology. We never share your information with third parties without your consent.</p></div>
      </div>
    </div>
  </div>
</section>

<!-- ==================== BLOG ==================== -->
<section class="section" id="blog">
  <div class="container">
    <div style="text-align:center;margin-bottom:10px">
      <span class="section-label">Blog</span>
      <h2 class="section-title">Latest <span class="gradient-text">Updates & Tips</span></h2>
      <p class="section-subtitle" style="margin:0 auto">Stay informed with the latest earning strategies and platform updates.</p>
    </div>
    <div class="blog-grid">
      <div class="blog-card">
        <div class="b-img"><i class="fas fa-chart-line"></i></div>
        <div class="b-body">
          <span class="b-tag">Tips</span>
          <h4>10 Proven Strategies to Maximize Your Daily Earnings</h4>
          <p>Learn the best techniques used by top earners to boost their daily income on ZynEarn.</p>
          <span class="b-date">May 15, 2026</span>
        </div>
      </div>
      <div class="blog-card">
        <div class="b-img"><i class="fas fa-user-friends"></i></div>
        <div class="b-body">
          <span class="b-tag">Referrals</span>
          <h4>How to Build a Passive Income Stream with Referrals</h4>
          <p>Discover how top referrers earn thousands monthly through our referral program.</p>
          <span class="b-date">May 12, 2026</span>
        </div>
      </div>
      <div class="blog-card">
        <div class="b-img"><i class="fas fa-crown"></i></div>
        <div class="b-body">
          <span class="b-tag">VIP</span>
          <h4>Is the VIP Membership Worth It? Real Earnings Breakdown</h4>
          <p>An honest analysis of the ROI on each membership tier with real member data.</p>
          <span class="b-date">May 10, 2026</span>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ==================== NEWSLETTER ==================== -->
<section class="newsletter-section" id="contact">
  <div class="container">
    <div class="newsletter-box">
      <h3>Stay <span class="gradient-text">Updated</span></h3>
      <p>Get the latest earning opportunities, tips, and exclusive offers delivered to your inbox.</p>
      <form class="newsletter-form" id="newsletterForm" action="/subscribe" method="POST">
        <input type="email" name="email" placeholder="Enter your email address" required>
        <button type="submit">Subscribe <i class="fas fa-paper-plane"></i></button>
      </form>
    </div>
  </div>
</section>

<!-- ==================== APP DOWNLOAD ==================== -->
<section class="download-section">
  <div class="container">
    <h3>Download the <span class="gradient-text">ZynEarn App</span></h3>
    <p>Earn on the go with our mobile app. Available on iOS and Android.</p>
    <div class="download-badges">
      <a href="#" class="download-badge">
        <i class="fab fa-apple" style="color:#a2a2a2"></i>
        <div><div class="db-label">Download on</div><div class="db-name">App Store</div></div>
      </a>
      <a href="#" class="download-badge">
        <i class="fab fa-google-play" style="color:#34a853"></i>
        <div><div class="db-label">GET IT ON</div><div class="db-name">Google Play</div></div>
      </a>
    </div>
  </div>
</section>

<!-- ==================== FOOTER ==================== -->
<footer class="footer" id="footer">
  <div class="container">
    <div class="footer-grid">
      <div class="footer-brand">
        <a href="#" class="nav-logo"><i class="fas fa-rocket"></i> ZynEarn</a>
        <p>The premium platform where thousands earn real money completing tasks, surveys, offers and more. Join the future of online earning.</p>
        <div class="footer-social">
          <a href="#" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
          <a href="#" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
          <a href="#" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
          <a href="#" aria-label="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
          <a href="#" aria-label="YouTube"><i class="fab fa-youtube"></i></a>
          <a href="#" aria-label="Discord"><i class="fab fa-discord"></i></a>
        </div>
      </div>
      <div class="footer-col">
        <h5>Quick Links</h5>
        <ul>
          <li><a href="#home">Home</a></li>
          <li><a href="#features">Features</a></li>
          <li><a href="#how">How It Works</a></li>
          <li><a href="#pricing">Pricing</a></li>
          <li><a href="#testimonials">Testimonials</a></li>
          <li><a href="#faq">FAQ</a></li>
        </ul>
      </div>
      <div class="footer-col">
        <h5>Resources</h5>
        <ul>
          <li><a href="#">Blog</a></li>
          <li><a href="#">Help Center</a></li>
          <li><a href="#">Community</a></li>
          <li><a href="#">API Docs</a></li>
          <li><a href="#">Earning Guide</a></li>
          <li><a href="#">Tutorials</a></li>
        </ul>
      </div>
      <div class="footer-col">
        <h5>Support</h5>
        <ul>
          <li><a href="#">Contact Us</a></li>
          <li><a href="#">Live Chat</a></li>
          <li><a href="#">Report Issue</a></li>
          <li><a href="#">Feedback</a></li>
          <li><a href="#">System Status</a></li>
        </ul>
      </div>
      <div class="footer-col">
        <h5>Legal</h5>
        <ul>
          <li><a href="#">Terms of Service</a></li>
          <li><a href="#">Privacy Policy</a></li>
          <li><a href="#">Cookie Policy</a></li>
          <li><a href="#">Refund Policy</a></li>
          <li><a href="#">GDPR</a></li>
        </ul>
      </div>
    </div>
    <div class="footer-bottom">
      <span>&copy; <?php echo date('Y'); ?> ZynEarn. All rights reserved.</span>
      <div class="payment-icons">
        <i class="fab fa-cc-visa" title="Visa"></i>
        <i class="fab fa-cc-mastercard" title="Mastercard"></i>
        <i class="fab fa-cc-paypal" title="PayPal"></i>
        <i class="fab fa-cc-amex" title="Amex"></i>
        <i class="fab fa-bitcoin" title="Bitcoin"></i>
        <i class="fab fa-ethereum" title="Ethereum"></i>
      </div>
    </div>
  </div>
</footer>

<!-- ==================== BACK TO TOP ==================== -->
<button class="back-to-top" id="backToTop" aria-label="Back to top"><i class="fas fa-arrow-up"></i></button>

<!-- ==================== MOBILE CTA ==================== -->
<div class="mobile-cta">
  <a href="/auth/register.php" class="btn btn-primary btn-glow">Start Earning Now <i class="fas fa-rocket"></i></a>
</div>

<!-- ==================== SCRIPTS ==================== -->
<script defer src="/assets/js/app.js"></script>
<script>
/* ========== PRELOADER ========== */
(function(){
  const bar = document.getElementById('preloader-bar');
  const pct = document.getElementById('preloader-percent');
  let progress = 0;
  const interval = setInterval(() => {
    progress += Math.random() * 15 + 5;
    if(progress > 100) progress = 100;
    bar.style.width = progress + '%';
    pct.textContent = Math.floor(progress) + '%';
    if(progress >= 100) {
      clearInterval(interval);
      setTimeout(() => document.getElementById('preloader').classList.add('hidden'), 400);
    }
  }, 200);
})();

/* ========== THEME TOGGLE ========== */
(function(){
  const toggle = document.getElementById('themeToggle');
  const icon = toggle.querySelector('i');
  const html = document.body;
  const saved = localStorage.getItem('theme');
  if(saved) html.setAttribute('data-theme', saved);
  const updateIcon = () => {
    const theme = html.getAttribute('data-theme');
    icon.className = theme === 'light' ? 'fas fa-sun' : 'fas fa-moon';
  };
  updateIcon();
  toggle.addEventListener('click', () => {
    const theme = html.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
    html.setAttribute('data-theme', theme);
    localStorage.setItem('theme', theme);
    updateIcon();
  });
})();

/* ========== NAVBAR SCROLL ========== */
(function(){
  const nav = document.getElementById('navbar');
  window.addEventListener('scroll', () => { nav.classList.toggle('scrolled', window.scrollY > 50); });
})();

/* ========== HAMBURGER MOBILE ========== */
(function(){
  const hamburger = document.getElementById('hamburger');
  const links = document.getElementById('navLinks');
  hamburger.addEventListener('click', () => {
    hamburger.classList.toggle('active');
    links.classList.toggle('open');
  });
  links.querySelectorAll('a').forEach(a => a.addEventListener('click', () => {
    hamburger.classList.remove('active');
    links.classList.remove('open');
  }));
})();

/* ========== TYPING EFFECT ========== */
(function(){
  const el = document.getElementById('typedText');
  const words = ['Real Money', 'Cash Rewards', 'Easy Cash', 'Passive Income', 'Big Wins'];
  let idx = 0, charIdx = 0, isDeleting = false;
  function type(){
    const word = words[idx];
    if(!isDeleting){
      el.textContent = word.substring(0, charIdx + 1);
      charIdx++;
      if(charIdx === word.length){ isDeleting = true; setTimeout(type, 2000); return; }
    } else {
      el.textContent = word.substring(0, charIdx - 1);
      charIdx--;
      if(charIdx === 0){ isDeleting = false; idx = (idx + 1) % words.length; }
    }
    setTimeout(type, isDeleting ? 50 : 100);
  }
  type();
})();

/* ========== COUNTERS ========== */
(function(){
  const counters = document.querySelectorAll('.counter');
  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if(entry.isIntersecting){
        const el = entry.target;
        const target = parseInt(el.dataset.target);
        const prefix = el.dataset.prefix || '';
        const suffix = el.dataset.suffix || '';
        const duration = 2000;
        const start = performance.now();
        function update(now){
          const elapsed = now - start;
          const progress = Math.min(elapsed / duration, 1);
          const eased = 1 - Math.pow(1 - progress, 3);
          const current = Math.floor(eased * target);
          el.textContent = prefix + current.toLocaleString() + suffix;
          if(progress < 1) requestAnimationFrame(update);
          else el.textContent = prefix + target.toLocaleString() + suffix;
        }
        requestAnimationFrame(update);
        observer.unobserve(el);
      }
    });
  }, { threshold: .3 });
  counters.forEach(c => observer.observe(c));
})();

/* ========== PARTICLES ========== */
(function(){
  const canvas = document.getElementById('particles-canvas');
  const ctx = canvas.getContext('2d');
  let particles = [];
  function resize(){
    canvas.width = canvas.parentElement.offsetWidth;
    canvas.height = canvas.parentElement.offsetHeight;
  }
  resize();
  window.addEventListener('resize', resize);
  for(let i = 0; i < 80; i++){
    particles.push({
      x: Math.random() * canvas.width,
      y: Math.random() * canvas.height,
      vx: (Math.random() - .5) * .5,
      vy: (Math.random() - .5) * .5,
      r: Math.random() * 2 + 1,
      a: Math.random() * .5 + .1
    });
  }
  function draw(){
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    particles.forEach(p => {
      p.x += p.vx; p.y += p.vy;
      if(p.x < 0) p.x = canvas.width;
      if(p.x > canvas.width) p.x = 0;
      if(p.y < 0) p.y = canvas.height;
      if(p.y > canvas.height) p.y = 0;
      ctx.beginPath();
      ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
      ctx.fillStyle = `rgba(108, 92, 231, ${p.a})`;
      ctx.fill();
    });
    particles.forEach((a, i) => {
      for(let j = i + 1; j < particles.length; j++){
        const b = particles[j];
        const dx = a.x - b.x, dy = a.y - b.y;
        const dist = Math.sqrt(dx * dx + dy * dy);
        if(dist < 120){
          ctx.beginPath();
          ctx.moveTo(a.x, a.y);
          ctx.lineTo(b.x, b.y);
          ctx.strokeStyle = `rgba(108, 92, 231, ${.06 * (1 - dist / 120)})`;
          ctx.stroke();
        }
      }
    });
    requestAnimationFrame(draw);
  }
  draw();
})();

/* ========== FLOATING TOASTS ========== */
(function(){
  const container = document.getElementById('floatingToasts');
  const names = ['John D.', 'Maria K.', 'Alex S.', 'Emma R.', 'Chris M.', 'Sophia L.', 'James W.', 'Olivia P.'];
  const actions = ['completed a survey', 'claimed daily bonus', 'completed an offer', 'won spin wheel', 'referred a friend', 'completed tasks', 'watched videos'];
  const amounts = [1.20, 2.50, 5.00, 0.75, 3.00, 8.40, 1.50, 12.00, 0.50, 4.20, 6.00, 15.00];
  function showToast(){
    const toast = document.createElement('div');
    toast.className = 'toast-notification';
    const name = names[Math.floor(Math.random() * names.length)];
    const action = actions[Math.floor(Math.random() * actions.length)];
    const amount = amounts[Math.floor(Math.random() * amounts.length)];
    const initial = name.charAt(0);
    const colors = ['#6c5ce7','#00cec9','#fd79a8','#fdcb6e','#74b9ff','#e17055','#55efc4','#a29bfe'];
    toast.innerHTML = `
      <div class="t-avatar" style="background:${colors[Math.floor(Math.random() * colors.length)]}">${initial}</div>
      <div class="t-info">
        <div class="t-name">${name}</div>
        <div class="t-action">${action}</div>
      </div>
      <div class="t-amount">+$${amount.toFixed(2)}</div>
    `;
    container.appendChild(toast);
    requestAnimationFrame(() => toast.classList.add('show'));
    setTimeout(() => {
      toast.classList.remove('show');
      setTimeout(() => toast.remove(), 500);
    }, 4000);
  }
  showToast();
  setInterval(showToast, 5000 + Math.random() * 4000);
})();

/* ========== TABS ========== */
(function(){
  const tabs = document.querySelectorAll('.tab-btn');
  tabs.forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
      document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
      btn.classList.add('active');
      document.querySelector(`[data-content="${btn.dataset.tab}"]`).classList.add('active');
    });
  });
})();

/* ========== FAQ ACCORDION ========== */
(function(){
  const items = document.querySelectorAll('.faq-item');
  items.forEach(item => {
    const q = item.querySelector('.faq-question');
    q.addEventListener('click', () => {
      const isActive = item.classList.contains('active');
      items.forEach(i => i.classList.remove('active'));
      if(!isActive) item.classList.add('active');
    });
  });
})();

/* ========== REFERRAL CALCULATOR ========== */
(function(){
  const input = document.getElementById('refInput');
  const btn = document.getElementById('refCalcBtn');
  const result = document.getElementById('refResult');
  btn.addEventListener('click', () => {
    const count = parseInt(input.value) || 0;
    const avgEarning = 50;
    const l1 = count * avgEarning * .2;
    const l2 = count * 3 * avgEarning * .05;
    result.textContent = '$' + (l1 + l2).toFixed(2);
  });
  if(input) input.addEventListener('keydown', (e) => { if(e.key === 'Enter') btn.click(); });
})();

/* ========== BACK TO TOP ========== */
(function(){
  const btn = document.getElementById('backToTop');
  window.addEventListener('scroll', () => btn.classList.toggle('visible', window.scrollY > 500));
  btn.addEventListener('click', () => window.scrollTo({top:0, behavior:'smooth'}));
})();

/* ========== LIVE ACTIVITY FEED ========== */
(function(){
  const feed = document.getElementById('liveFeed');
  const fNames = ['Mike R.', 'Jessica K.', 'Alex T.', 'Sarah L.', 'David W.', 'Emily C.', 'Ryan M.', 'Lisa B.'];
  const fActions = ['completed survey', 'claimed daily bonus', 'completed offer', 'withdrawn to PayPal', 'referred a friend', 'won spin wheel', 'completed tasks', 'scratched card'];
  const fAmounts = [3.50, 0.75, 12.00, 25.00, 2.00, 8.00, 5.50, 1.20];
  const fColors = ['#6c5ce7','#00cec9','#fd79a8','#fdcb6e','#74b9ff','#e17055','#55efc4','#a29bfe'];
  function addFeed(){
    const el = document.createElement('div');
    el.className = 'live-item';
    const n = fNames[Math.floor(Math.random() * fNames.length)];
    const a = fActions[Math.floor(Math.random() * fActions.length)];
    const am = fAmounts[Math.floor(Math.random() * fAmounts.length)];
    const c = fColors[Math.floor(Math.random() * fColors.length)];
    const isWithdrawal = a.includes('withdrawn');
    el.innerHTML = `
      <div class="l-avatar" style="background:${c}">${n.charAt(0)}</div>
      <span class="l-name">${n}</span>
      <span class="l-action">${a}</span>
      <span class="l-amount">${isWithdrawal ? '$' + am.toFixed(2) : '+' + '$' + am.toFixed(2)}</span>
    `;
    feed.insertBefore(el, feed.firstChild);
    if(feed.children.length > 6) feed.removeChild(feed.lastChild);
  }
  setInterval(addFeed, 6000);
})();

/* ========== NEWSLETTER FORM ========== */
(function(){
  const form = document.getElementById('newsletterForm');
  if(form) {
    form.addEventListener('submit', (e) => {
      e.preventDefault();
      const btn = form.querySelector('button');
      btn.innerHTML = 'Subscribed! <i class="fas fa-check"></i>';
      btn.style.background = 'linear-gradient(135deg, #00b894, #00cec9)';
      setTimeout(() => {
        btn.innerHTML = 'Subscribe <i class="fas fa-paper-plane"></i>';
        btn.style.background = '';
        form.reset();
      }, 3000);
    });
  }
})();

/* ========== SMOOTH SCROLL ========== */
document.querySelectorAll('a[href^="#"]').forEach(a => {
  a.addEventListener('click', e => {
    const id = a.getAttribute('href');
    if(id === '#') return;
    const el = document.querySelector(id);
    if(el) { e.preventDefault(); el.scrollIntoView({behavior:'smooth'}); }
  });
});
</script>
</body>
</html>
