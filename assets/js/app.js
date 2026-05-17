/**
 * ZynEarn - Premium Money Earning Platform
 * Main Application Script
 * Version 1.0.0
 */

const APP_URL = 'http://localhost/zyn-earn';
const APP_NAME = 'ZynEarn';
const APP_VERSION = '1.0.0';
const CSRF_TOKEN = (() => {
  const meta = document.querySelector('meta[name="csrf-token"]');
  return meta ? meta.getAttribute('content') : (window.csrfToken || '');
})();

class ZynEarn {
  constructor() {
    this.config = {
      balancePollInterval: 30000,
      notificationPollInterval: 15000,
      alertDismissDelay: 5000,
      skeletonDelay: 800,
      scrollDebounce: 100,
      infiniteScrollThreshold: 200,
      typingSpeed: 50,
      typingDeleteSpeed: 30,
      typingPause: 2000,
      particleCount: 50,
      confettiColors: ['#ff6b6b', '#ffd93d', '#6bcb77', '#4d96ff', '#ff6bff'],
      confettiCount: 150,
      wheelSegments: 8,
      wheelSpinDuration: 4000,
      scratchCardSize: 20,
      parallaxFactor: 0.5,
      toastDuration: 4000,
      copySuccessDuration: 2000,
      countdownInterval: 1000,
      progressAnimationDuration: 1000,
      pageTransitionDuration: 400,
      starRatingIcons: ['★', '☆'],
      moneySymbols: ['$', '€', '£', '₿', '₹', '₦', '₱', '₩', '₺', '₴'],
    };

    this.state = {
      theme: localStorage.getItem('zynearn_theme') || 'dark',
      balance: 0,
      notifications: [],
      unreadCount: 0,
      isOnline: navigator.onLine,
      activeModal: null,
      currentPage: 1,
      isLoadingMore: false,
      hasMorePages: true,
      typingIndex: 0,
      typingCharIndex: 0,
      isDeleting: false,
    };

    this.cache = {};
    this.intervals = [];
    this.observers = [];
    this.eventListeners = [];

    this.init();
  }

  init() {
    this.domReady(() => {
      this.cacheElements();
      this.setupTheme();
      this.setupMobileSidebar();
      this.setupSmoothScroll();
      this.setupStickyHeader();
      this.setupAnimatedCounters();
      this.setupScrollAnimations();
      this.setupToastSystem();
      this.setupModals();
      this.setupAjaxHandler();
      this.setupFormValidation();
      this.setupPasswordStrength();
      this.setupOtpInput();
      this.setupCopyToClipboard();
      this.setupCountdownTimers();
      this.setupProgressBars();
      this.setupCharts();
      this.setupSpinWheel();
      this.setupScratchCard();
      this.setupFaucetClaim();
      this.setupSkeletons();
      this.setupPageTransitions();
      this.setupDropdowns();
      this.setupTooltips();
      this.setupTabs();
      this.setupAccordion();
      this.setupStarRating();
      this.setupFileUpload();
      this.setupLazyLoading();
      this.setupInfiniteScroll();
      this.setupBackToTop();
      this.setupSearchFilters();
      this.setupFormWizard();
      this.setupConfetti();
      this.setupParticles();
      this.setupTypingEffect();
      this.setupParallax();
      this.setupAudioFeedback();
      this.setupKeyboardShortcuts();
      this.setupOnlineDetection();
      this.setupServiceWorker();
      this.setupAnalytics();
      this.setupAutoDismissAlerts();
      this.setupLiveActivityFeed();
      this.setupNotificationCenter();

      this.startBalancePolling();
      this.startNotificationPolling();

      document.dispatchEvent(new CustomEvent('zynearn:ready', { detail: { instance: this } }));
    });
  }

  domReady(fn) {
    if (document.readyState !== 'loading') {
      fn();
    } else {
      document.addEventListener('DOMContentLoaded', fn);
    }
  }

  cacheElements() {
    this.elements = {
      body: document.body,
      html: document.documentElement,
      themeToggle: document.querySelector('[data-theme-toggle]'),
      mobileToggle: document.querySelector('[data-mobile-toggle]'),
      sidebar: document.querySelector('[data-sidebar]'),
      overlay: document.querySelector('[data-overlay]'),
      header: document.querySelector('[data-header]'),
      backToTop: document.querySelector('[data-back-to-top]'),
      toastContainer: document.querySelector('[data-toast-container]'),
      modalContainer: document.querySelector('[data-modal-container]'),
      notificationBadge: document.querySelector('[data-notification-badge]'),
      notificationList: document.querySelector('[data-notification-list]'),
      notificationCenter: document.querySelector('[data-notification-center]'),
      searchInput: document.querySelector('[data-search-input]'),
      searchResults: document.querySelector('[data-search-results]'),
      balanceDisplay: document.querySelector('[data-balance]'),
      userMenu: document.querySelector('[data-user-menu]'),
      progressBars: document.querySelectorAll('[data-progress]'),
      counters: document.querySelectorAll('[data-counter]'),
      animatedElements: document.querySelectorAll('[data-animate]'),
      otpInputs: document.querySelectorAll('[data-otp-input]'),
      tooltips: document.querySelectorAll('[data-tooltip]'),
      lazyImages: document.querySelectorAll('[data-src]'),
      accordionItems: document.querySelectorAll('[data-accordion]'),
      tabGroups: document.querySelectorAll('[data-tabs]'),
      starRatings: document.querySelectorAll('[data-star-rating]'),
      fileInputs: document.querySelectorAll('[data-file-input]'),
      forms: document.querySelectorAll('[data-validate]'),
      passwordInputs: document.querySelectorAll('[data-password-strength]'),
      dropdownToggles: document.querySelectorAll('[data-dropdown-toggle]'),
      countdownElements: document.querySelectorAll('[data-countdown]'),
      wheelCanvas: document.querySelector('[data-wheel-canvas]'),
      scratchCanvas: document.querySelector('[data-scratch-canvas]'),
      claimButton: document.querySelector('[data-claim-button]'),
      claimResult: document.querySelector('[data-claim-result]'),
      skeletonElements: document.querySelectorAll('[data-skeleton]'),
      faucetTimer: document.querySelector('[data-faucet-timer]'),
      activityFeed: document.querySelector('[data-activity-feed]'),
      audioElements: {
        earn: document.querySelector('[data-audio="earn"]'),
        click: document.querySelector('[data-audio="click"]'),
        success: document.querySelector('[data-audio="success"]'),
      },
    };
  }

  setupTheme() {
    const { themeToggle } = this.elements;
    const savedTheme = localStorage.getItem('zynearn_theme') || 'dark';
    this.state.theme = savedTheme;
    document.documentElement.setAttribute('data-theme', savedTheme);

    if (themeToggle) {
      this.addEvent(themeToggle, 'click', () => {
        const current = document.documentElement.getAttribute('data-theme');
        const next = current === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', next);
        localStorage.setItem('zynearn_theme', next);
        this.state.theme = next;
        themeToggle.setAttribute('aria-label', `Switch to ${current} mode`);
        this.trackEvent('theme_toggle', { theme: next });
      });
    }
  }

  setupMobileSidebar() {
    const { mobileToggle, sidebar, overlay } = this.elements;

    if (mobileToggle && sidebar) {
      this.addEvent(mobileToggle, 'click', () => {
        sidebar.classList.toggle('active');
        overlay?.classList.toggle('active');
        document.body.classList.toggle('sidebar-open');
      });

      if (overlay) {
        this.addEvent(overlay, 'click', () => {
          sidebar.classList.remove('active');
          overlay.classList.remove('active');
          document.body.classList.remove('sidebar-open');
        });
      }

      document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && sidebar.classList.contains('active')) {
          sidebar.classList.remove('active');
          overlay?.classList.remove('active');
          document.body.classList.remove('sidebar-open');
        }
      });
    }
  }

  setupSmoothScroll() {
    document.addEventListener('click', (e) => {
      const link = e.target.closest('a[href^="#"]');
      if (!link) return;
      const target = document.querySelector(link.getAttribute('href'));
      if (!target) return;
      e.preventDefault();
      target.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
  }

  setupStickyHeader() {
    const { header } = this.elements;
    if (!header) return;
    let lastScroll = 0;

    this.addEvent(window, 'scroll', () => {
      const current = window.pageYOffset;
      if (current > 50) {
        header.classList.add('sticky');
        if (current > lastScroll && current > 200) {
          header.classList.add('hidden');
        } else {
          header.classList.remove('hidden');
        }
      } else {
        header.classList.remove('sticky', 'hidden');
      }
      lastScroll = current;
    }, { passive: true });
  }

  setupAnimatedCounters() {
    const { counters } = this.elements;
    if (!counters.length) return;

    const observer = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          const el = entry.target;
          const target = parseFloat(el.getAttribute('data-counter'));
          const prefix = el.getAttribute('data-counter-prefix') || '';
          const suffix = el.getAttribute('data-counter-suffix') || '';
          const decimals = parseInt(el.getAttribute('data-counter-decimals') || '0', 10);
          const duration = parseInt(el.getAttribute('data-counter-duration') || '2000', 10);

          this.animateCounter(el, target, prefix, suffix, decimals, duration);
          observer.unobserve(el);
        }
      });
    }, { threshold: 0.5 });

    counters.forEach((el) => observer.observe(el));
    this.observers.push(observer);
  }

  animateCounter(el, target, prefix, suffix, decimals, duration) {
    const start = performance.now();
    const startValue = 0;

    const update = (currentTime) => {
      const elapsed = currentTime - start;
      const progress = Math.min(elapsed / duration, 1);
      const eased = 1 - Math.pow(1 - progress, 3);
      const current = startValue + (target - startValue) * eased;

      el.textContent = prefix + current.toFixed(decimals) + suffix;

      if (progress < 1) {
        requestAnimationFrame(update);
      } else {
        el.textContent = prefix + target.toFixed(decimals) + suffix;
      }
    };

    requestAnimationFrame(update);
  }

  setupScrollAnimations() {
    const { animatedElements } = this.elements;
    if (!animatedElements.length) return;

    const observer = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          const el = entry.target;
          const animation = el.getAttribute('data-animate') || 'fadeInUp';
          const delay = parseInt(el.getAttribute('data-animate-delay') || '0', 10);
          el.style.animationDelay = `${delay}ms`;
          el.classList.add('animated', animation);
          observer.unobserve(el);
        }
      });
    }, { threshold: 0.1 });

    animatedElements.forEach((el) => observer.observe(el));
    this.observers.push(observer);
  }

  setupToastSystem() {
    let container = this.elements.toastContainer;
    if (!container) {
      container = document.createElement('div');
      container.setAttribute('data-toast-container', '');
      container.className = 'toast-container';
      document.body.appendChild(container);
      this.elements.toastContainer = container;
    }
  }

  toast(message, type = 'info', duration = null) {
    const container = this.elements.toastContainer;
    if (!container) return;

    duration = duration || this.config.toastDuration;

    const icons = {
      success: '✓',
      error: '✕',
      warning: '⚠',
      info: 'ℹ',
    };

    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.setAttribute('role', 'alert');
    toast.innerHTML = `
      <span class="toast-icon">${icons[type] || icons.info}</span>
      <span class="toast-message">${this.escapeHtml(message)}</span>
      <button class="toast-close" aria-label="Dismiss">&times;</button>
    `;

    container.appendChild(toast);

    requestAnimationFrame(() => toast.classList.add('toast-visible'));

    const closeBtn = toast.querySelector('.toast-close');
    this.addEvent(closeBtn, 'click', () => this.dismissToast(toast));

    if (duration > 0) {
      setTimeout(() => this.dismissToast(toast), duration);
    }

    this.trackEvent('toast_shown', { type, message: message.substring(0, 50) });
    return toast;
  }

  dismissToast(toast) {
    if (!toast || toast.classList.contains('toast-dismissing')) return;
    toast.classList.remove('toast-visible');
    toast.classList.add('toast-dismissing');
    setTimeout(() => toast.remove(), 300);
  }

  setupModals() {
    const { modalContainer } = this.elements;

    document.addEventListener('click', (e) => {
      const trigger = e.target.closest('[data-modal-open]');
      if (trigger) {
        e.preventDefault();
        const modalId = trigger.getAttribute('data-modal-open');
        this.openModal(modalId);
      }
    });

    document.addEventListener('click', (e) => {
      if (e.target.closest('[data-modal-close]') || e.target.closest('.modal-overlay')) {
        const modal = e.target.closest('[data-modal]');
        if (modal) this.closeModal(modal);
      }
    });

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && this.state.activeModal) {
        this.closeModal(this.state.activeModal);
      }
    });
  }

  openModal(id) {
    const modal = document.querySelector(`[data-modal="${id}"]`);
    if (!modal) return;

    this.state.activeModal = modal;
    modal.classList.add('modal-active');
    document.body.classList.add('modal-open');

    const event = new CustomEvent('modal:open', { detail: { modal, id } });
    document.dispatchEvent(event);
    this.trackEvent('modal_opened', { modal_id: id });
  }

  closeModal(modal) {
    if (!modal) return;
    modal.classList.remove('modal-active');
    document.body.classList.remove('modal-open');
    this.state.activeModal = null;

    const event = new CustomEvent('modal:close', { detail: { modal } });
    document.dispatchEvent(event);
  }

  setupAjaxHandler() {
    window.ZynAjax = {
      get: (url, params = {}, options = {}) => {
        const query = this.buildQuery(params);
        const fullUrl = query ? `${url}?${query}` : url;
        return this.fetchWithCsrf(fullUrl, { method: 'GET', ...options });
      },
      post: (url, data = {}, options = {}) => {
        return this.fetchWithCsrf(url, {
          method: 'POST',
          body: data instanceof FormData ? data : JSON.stringify(data),
          headers: data instanceof FormData ? {} : { 'Content-Type': 'application/json' },
          ...options,
        });
      },
      put: (url, data = {}, options = {}) => {
        return this.fetchWithCsrf(url, {
          method: 'PUT',
          body: JSON.stringify(data),
          headers: { 'Content-Type': 'application/json' },
          ...options,
        });
      },
      delete: (url, options = {}) => {
        return this.fetchWithCsrf(url, { method: 'DELETE', ...options });
      },
    };
  }

  fetchWithCsrf(url, options = {}) {
    const headers = options.headers || {};
    headers['X-Requested-With'] = 'XMLHttpRequest';
    headers['X-CSRF-TOKEN'] = CSRF_TOKEN;
    headers['Accept'] = 'application/json';

    if (!(options.body instanceof FormData)) {
      headers['Content-Type'] = headers['Content-Type'] || 'application/json';
    }

    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), 30000);

    return fetch(url, {
      ...options,
      headers,
      signal: controller.signal,
      credentials: 'same-origin',
    })
      .then((response) => {
        clearTimeout(timeout);
        if (!response.ok) {
          return response.json().then((err) => {
            const error = new Error(err.message || `HTTP ${response.status}`);
            error.status = response.status;
            error.data = err;
            throw error;
          }).catch(() => {
            const error = new Error(`HTTP ${response.status}`);
            error.status = response.status;
            throw error;
          });
        }
        return response.json();
      })
      .catch((error) => {
        clearTimeout(timeout);
        if (error.name === 'AbortError') {
          throw new Error('Request timed out');
        }
        throw error;
      });
  }

  buildQuery(params) {
    const filtered = Object.entries(params).filter(([, v]) => v !== null && v !== undefined && v !== '');
    if (!filtered.length) return '';
    return filtered.map(([k, v]) => `${encodeURIComponent(k)}=${encodeURIComponent(v)}`).join('&');
  }

  setupFormValidation() {
    const { forms } = this.elements;
    forms.forEach((form) => {
      const inputs = form.querySelectorAll('input, select, textarea');
      inputs.forEach((input) => {
        this.addEvent(input, 'blur', () => this.validateField(input));
        this.addEvent(input, 'input', () => {
          if (input.dataset.validated) {
            this.validateField(input);
          }
        });
      });

      this.addEvent(form, 'submit', (e) => {
        let isValid = true;
        inputs.forEach((input) => {
          if (!this.validateField(input)) {
            isValid = false;
          }
        });
        if (!isValid) {
          e.preventDefault();
          const firstError = form.querySelector('.field-error');
          firstError?.closest('.form-group')?.querySelector('input, select, textarea')?.focus();
        }
      });
    });
  }

  validateField(input) {
    const rules = this.getFieldRules(input);
    const value = input.value.trim();
    let error = '';

    for (const rule of rules) {
      const result = this.applyRule(rule, value, input);
      if (result) {
        error = result;
        break;
      }
    }

    const errorEl = input.closest('.form-group')?.querySelector('.field-error');
    if (error) {
      input.classList.add('field-invalid');
      input.classList.remove('field-valid');
      input.dataset.validated = 'true';
      if (errorEl) {
        errorEl.textContent = error;
        errorEl.style.display = 'block';
      }
      return false;
    } else {
      input.classList.remove('field-invalid');
      input.classList.add('field-valid');
      input.dataset.validated = 'true';
      if (errorEl) {
        errorEl.textContent = '';
        errorEl.style.display = 'none';
      }
      return true;
    }
  }

  getFieldRules(input) {
    const rules = [];
    const type = input.getAttribute('type');
    const required = input.hasAttribute('required');
    const minLength = input.getAttribute('minlength');
    const maxLength = input.getAttribute('maxlength');
    const min = input.getAttribute('min');
    const max = input.getAttribute('max');
    const pattern = input.getAttribute('pattern');

    if (required) rules.push({ name: 'required' });
    if (type === 'email') rules.push({ name: 'email' });
    if (type === 'url') rules.push({ name: 'url' });
    if (minLength) rules.push({ name: 'minLength', value: parseInt(minLength, 10) });
    if (maxLength) rules.push({ name: 'maxLength', value: parseInt(maxLength, 10) });
    if (min) rules.push({ name: 'min', value: parseFloat(min) });
    if (max) rules.push({ name: 'max', value: parseFloat(max) });
    if (pattern) rules.push({ name: 'pattern', value: new RegExp(pattern) });
    if (input.getAttribute('data-match')) rules.push({ name: 'match', value: input.getAttribute('data-match') });

    return rules;
  }

  applyRule(rule, value, input) {
    const labels = {
      required: 'This field is required',
      email: 'Please enter a valid email address',
      url: 'Please enter a valid URL',
      minLength: `Minimum ${rule.value} characters required`,
      maxLength: `Maximum ${rule.value} characters allowed`,
      min: `Minimum value is ${rule.value}`,
      max: `Maximum value is ${rule.value}`,
      pattern: 'Please match the requested format',
      match: 'Fields do not match',
    };

    switch (rule.name) {
      case 'required':
        if (!value) return labels.required;
        break;
      case 'email':
        if (value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) return labels.email;
        break;
      case 'url':
        if (value && !/^https?:\/\/.+/.test(value)) return labels.url;
        break;
      case 'minLength':
        if (value.length < rule.value) return labels.minLength;
        break;
      case 'maxLength':
        if (value.length > rule.value) return labels.maxLength;
        break;
      case 'min':
        if (value && parseFloat(value) < rule.value) return labels.min;
        break;
      case 'max':
        if (value && parseFloat(value) > rule.value) return labels.max;
        break;
      case 'pattern':
        if (value && !rule.value.test(value)) return labels.pattern;
        break;
      case 'match':
        const matchEl = document.querySelector(`[name="${rule.value}"]`);
        if (matchEl && value !== matchEl.value) return labels.match;
        break;
    }
    return '';
  }

  setupPasswordStrength() {
    const { passwordInputs } = this.elements;
    passwordInputs.forEach((input) => {
      const container = input.closest('.form-group');
      const meter = container?.querySelector('.password-strength-meter');
      const label = container?.querySelector('.password-strength-label');
      if (!meter) return;

      this.addEvent(input, 'input', () => {
        const result = this.evaluatePasswordStrength(input.value);
        meter.style.width = `${result.score}%`;
        meter.className = `password-strength-meter strength-${result.level}`;
        if (label) {
          label.textContent = result.text;
          label.className = `password-strength-label strength-${result.level}`;
        }
      });
    });
  }

  evaluatePasswordStrength(password) {
    let score = 0;
    if (password.length >= 8) score += 25;
    if (password.length >= 12) score += 10;
    if (/[a-z]/.test(password)) score += 10;
    if (/[A-Z]/.test(password)) score += 15;
    if (/[0-9]/.test(password)) score += 15;
    if (/[^a-zA-Z0-9]/.test(password)) score += 15;
    if (password.length >= 16) score += 10;

    score = Math.min(score, 100);

    let level, text;
    if (score < 30) { level = 'weak'; text = 'Weak'; }
    else if (score < 60) { level = 'fair'; text = 'Fair'; }
    else if (score < 80) { level = 'good'; text = 'Good'; }
    else { level = 'strong'; text = 'Strong'; }

    return { score, level, text };
  }

  setupOtpInput() {
    const { otpInputs } = this.elements;
    if (!otpInputs.length) return;

    otpInputs.forEach((input, index, array) => {
      this.addEvent(input, 'input', (e) => {
        const val = e.target.value.replace(/[^0-9]/g, '');
        e.target.value = val;
        if (val && index < array.length - 1) {
          array[index + 1].focus();
        }
        if (array.length - 1 === index && val) {
          this.handleOtpComplete(array);
        }
      });

      this.addEvent(input, 'keydown', (e) => {
        if (e.key === 'Backspace' && !e.target.value && index > 0) {
          array[index - 1].focus();
        }
        if (e.key === 'ArrowLeft' && index > 0) {
          array[index - 1].focus();
        }
        if (e.key === 'ArrowRight' && index < array.length - 1) {
          array[index + 1].focus();
        }
      });

      this.addEvent(input, 'focus', () => input.select());
    });

    otpInputs[0]?.focus();
  }

  handleOtpComplete(inputs) {
    const code = Array.from(inputs).map((i) => i.value).join('');
    const event = new CustomEvent('otp:complete', { detail: { code, inputs } });
    document.dispatchEvent(event);
  }

  setupCopyToClipboard() {
    document.addEventListener('click', (e) => {
      const trigger = e.target.closest('[data-copy]');
      if (!trigger) return;

      const text = trigger.getAttribute('data-copy') || trigger.textContent;
      const target = trigger.getAttribute('data-copy-target');
      let value = text;

      if (target) {
        const targetEl = document.querySelector(target);
        if (targetEl) value = targetEl.value || targetEl.textContent;
      }

      navigator.clipboard.writeText(value).then(() => {
        const original = trigger.innerHTML;
        const feedback = trigger.getAttribute('data-copy-feedback') || 'Copied!';
        trigger.innerHTML = `<span class="copy-feedback">${feedback}</span>`;
        setTimeout(() => { trigger.innerHTML = original; }, this.config.copySuccessDuration);
        this.trackEvent('copy_to_clipboard', { text: value.substring(0, 50) });
      }).catch(() => {
        this.toast('Failed to copy to clipboard', 'error');
      });
    });
  }

  setupCountdownTimers() {
    const { countdownElements } = this.elements;
    countdownElements.forEach((el) => {
      const targetDate = el.getAttribute('data-countdown');
      if (!targetDate) return;
      this.startCountdown(el, targetDate);
    });
  }

  startCountdown(el, targetDate) {
    const target = new Date(targetDate).getTime();

    const update = () => {
      const now = Date.now();
      const diff = target - now;

      if (diff <= 0) {
        el.textContent = el.getAttribute('data-countdown-expired') || 'Expired';
        el.classList.add('countdown-expired');
        const event = new CustomEvent('countdown:end', { detail: { element: el } });
        document.dispatchEvent(event);
        return;
      }

      const days = Math.floor(diff / (1000 * 60 * 60 * 24));
      const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
      const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
      const seconds = Math.floor((diff % (1000 * 60)) / 1000);

      const format = el.getAttribute('data-countdown-format') || 'DHMS';
      let output = '';

      if (format.includes('D')) output += `${days}d `;
      if (format.includes('H')) output += `${String(hours).padStart(2, '0')}h `;
      if (format.includes('M')) output += `${String(minutes).padStart(2, '0')}m `;
      if (format.includes('S')) output += `${String(seconds).padStart(2, '0')}s`;

      el.textContent = output.trim();
    };

    update();
    const interval = setInterval(update, this.config.countdownInterval);
    this.intervals.push(interval);
    el.dataset.countdownInterval = interval;
  }

  setupProgressBars() {
    const { progressBars } = this.elements;
    const observer = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          const bar = entry.target;
          const target = parseInt(bar.getAttribute('data-progress'), 10);
          this.animateProgressBar(bar, target);
          observer.unobserve(bar);
        }
      });
    }, { threshold: 0.3 });

    progressBars.forEach((bar) => observer.observe(bar));
    this.observers.push(observer);
  }

  animateProgressBar(bar, target) {
    const fill = bar.querySelector('.progress-fill') || bar;
    const label = bar.querySelector('.progress-label');
    const duration = this.config.progressAnimationDuration;
    const start = performance.now();

    const update = (currentTime) => {
      const elapsed = currentTime - start;
      const progress = Math.min(elapsed / duration, 1);
      const eased = 1 - Math.pow(1 - progress, 3);
      const current = target * eased;

      fill.style.width = `${current}%`;
      if (label) label.textContent = `${Math.round(current)}%`;

      if (progress < 1) {
        requestAnimationFrame(update);
      } else {
        fill.style.width = `${target}%`;
        if (label) label.textContent = `${target}%`;
      }
    };

    requestAnimationFrame(update);
  }

  setupCharts() {
    document.querySelectorAll('[data-chart]').forEach((canvas) => {
      const type = canvas.getAttribute('data-chart');
      const data = this.safeParseJSON(canvas.getAttribute('data-chart-data'));
      if (data) this.initChart(canvas, type, data);
    });
  }

  initChart(canvas, type, data) {
    const ctx = canvas.getContext('2d');
    if (!ctx) return;
    const dpr = window.devicePixelRatio || 1;
    const rect = canvas.getBoundingClientRect();
    canvas.width = rect.width * dpr;
    canvas.height = rect.height * dpr;
    ctx.scale(dpr, dpr);

    const colors = data.colors || ['#4d96ff', '#6bcb77', '#ffd93d', '#ff6b6b', '#ff6bff'];
    const labels = data.labels || [];
    const values = data.values || [];
    const maxVal = Math.max(...values, 1);

    const padding = { top: 20, bottom: 30, left: 40, right: 20 };
    const width = rect.width;
    const height = rect.height;
    const chartW = width - padding.left - padding.right;
    const chartH = height - padding.top - padding.bottom;

    ctx.clearRect(0, 0, width, height);

    switch (type) {
      case 'bar':
        this.drawBarChart(ctx, labels, values, colors, padding, chartW, chartH, maxVal);
        break;
      case 'line':
        this.drawLineChart(ctx, labels, values, colors, padding, chartW, chartH, maxVal);
        break;
      case 'pie':
        this.drawPieChart(ctx, labels, values, colors, width, height);
        break;
      case 'doughnut':
        this.drawDoughnutChart(ctx, labels, values, colors, width, height);
        break;
    }
  }

  drawBarChart(ctx, labels, values, colors, padding, chartW, chartH, maxVal) {
    const barWidth = chartW / values.length * 0.6;
    const gap = chartW / values.length * 0.4;
    const startX = padding.left + gap / 2;

    values.forEach((val, i) => {
      const barH = (val / maxVal) * chartH;
      const x = startX + i * (barWidth + gap);
      const y = padding.top + chartH - barH;

      ctx.fillStyle = colors[i % colors.length];
      ctx.beginPath();
      ctx.roundRect(x, y, barWidth, barH, [4, 4, 0, 0]);
      ctx.fill();

      ctx.fillStyle = '#888';
      ctx.font = '11px Inter, sans-serif';
      ctx.textAlign = 'center';
      ctx.fillText(labels[i], x + barWidth / 2, padding.top + chartH + 18);
      ctx.fillText(val, x + barWidth / 2, y - 6);
    });
  }

  drawLineChart(ctx, labels, values, colors, padding, chartW, chartH, maxVal) {
    const points = values.map((val, i) => ({
      x: padding.left + (i / (values.length - 1)) * chartW,
      y: padding.top + chartH - (val / maxVal) * chartH,
    }));

    ctx.strokeStyle = colors[0];
    ctx.lineWidth = 2;
    ctx.beginPath();
    points.forEach((p, i) => {
      if (i === 0) ctx.moveTo(p.x, p.y);
      else ctx.lineTo(p.x, p.y);
    });
    ctx.stroke();

    points.forEach((p, i) => {
      ctx.fillStyle = colors[0];
      ctx.beginPath();
      ctx.arc(p.x, p.y, 4, 0, Math.PI * 2);
      ctx.fill();

      ctx.fillStyle = '#888';
      ctx.font = '11px Inter, sans-serif';
      ctx.textAlign = 'center';
      ctx.fillText(labels[i], p.x, padding.top + chartH + 18);
    });
  }

  drawPieChart(ctx, labels, values, colors, width, height) {
    const total = values.reduce((a, b) => a + b, 0);
    const cx = width / 2;
    const cy = height / 2;
    const radius = Math.min(cx, cy) - 20;
    let startAngle = -Math.PI / 2;

    values.forEach((val, i) => {
      const sliceAngle = (val / total) * Math.PI * 2;
      ctx.fillStyle = colors[i % colors.length];
      ctx.beginPath();
      ctx.moveTo(cx, cy);
      ctx.arc(cx, cy, radius, startAngle, startAngle + sliceAngle);
      ctx.closePath();
      ctx.fill();

      const labelAngle = startAngle + sliceAngle / 2;
      const lx = cx + Math.cos(labelAngle) * (radius * 0.6);
      const ly = cy + Math.sin(labelAngle) * (radius * 0.6);
      ctx.fillStyle = '#fff';
      ctx.font = '12px Inter, sans-serif';
      ctx.textAlign = 'center';
      ctx.fillText(`${Math.round((val / total) * 100)}%`, lx, ly + 4);

      startAngle += sliceAngle;
    });

    const legendY = height - 20;
    ctx.font = '11px Inter, sans-serif';
    const legendX = (width - labels.length * 80) / 2;
    labels.forEach((label, i) => {
      const lx = legendX + i * 80;
      ctx.fillStyle = colors[i % colors.length];
      ctx.fillRect(lx, legendY - 6, 10, 10);
      ctx.fillStyle = '#888';
      ctx.textAlign = 'left';
      ctx.fillText(label, lx + 14, legendY + 4);
    });
  }

  drawDoughnutChart(ctx, labels, values, colors, width, height) {
    const total = values.reduce((a, b) => a + b, 0);
    const cx = width / 2;
    const cy = height / 2;
    const outerRadius = Math.min(cx, cy) - 20;
    const innerRadius = outerRadius * 0.55;
    let startAngle = -Math.PI / 2;

    values.forEach((val, i) => {
      const sliceAngle = (val / total) * Math.PI * 2;
      ctx.fillStyle = colors[i % colors.length];
      ctx.beginPath();
      ctx.arc(cx, cy, outerRadius, startAngle, startAngle + sliceAngle);
      ctx.arc(cx, cy, innerRadius, startAngle + sliceAngle, startAngle, true);
      ctx.closePath();
      ctx.fill();

      startAngle += sliceAngle;
    });
  }

  setupSpinWheel() {
    const canvas = this.elements.wheelCanvas;
    if (!canvas) return;

    this.wheel = {
      canvas,
      ctx: canvas.getContext('2d'),
      isSpinning: false,
      currentRotation: 0,
      segments: [],
      onComplete: null,
    };

    const segmentsAttr = canvas.getAttribute('data-wheel-segments');
    if (segmentsAttr) {
      this.wheel.segments = this.safeParseJSON(segmentsAttr) || [];
    }

    this.addEvent(canvas, 'click', () => this.spinWheel());

    if (this.wheel.segments.length) {
      this.drawWheel();
    }
  }

  drawWheel() {
    const { ctx, segments, canvas } = this.wheel;
    const cx = canvas.width / 2;
    const cy = canvas.height / 2;
    const radius = Math.min(cx, cy) - 5;
    const sliceAngle = (Math.PI * 2) / segments.length;

    ctx.clearRect(0, 0, canvas.width, canvas.height);

    segments.forEach((seg, i) => {
      const startAngle = this.wheel.currentRotation + i * sliceAngle;
      const endAngle = startAngle + sliceAngle;

      ctx.fillStyle = seg.color || this.config.confettiColors[i % this.config.confettiColors.length];
      ctx.beginPath();
      ctx.moveTo(cx, cy);
      ctx.arc(cx, cy, radius, startAngle, endAngle);
      ctx.closePath();
      ctx.fill();

      ctx.strokeStyle = 'rgba(255,255,255,0.3)';
      ctx.lineWidth = 1;
      ctx.stroke();

      const labelAngle = startAngle + sliceAngle / 2;
      const labelRadius = radius * 0.65;
      ctx.save();
      ctx.translate(cx + Math.cos(labelAngle) * labelRadius, cy + Math.sin(labelAngle) * labelRadius);
      ctx.rotate(labelAngle + Math.PI / 2);
      ctx.fillStyle = '#fff';
      ctx.font = 'bold 12px Inter, sans-serif';
      ctx.textAlign = 'center';
      ctx.fillText(seg.label || '', 0, 4);
      ctx.restore();
    });

    ctx.fillStyle = '#fff';
    ctx.beginPath();
    ctx.arc(cx, cy, 15, 0, Math.PI * 2);
    ctx.fill();

    ctx.fillStyle = '#333';
    ctx.beginPath();
    ctx.arc(cx, cy, 12, 0, Math.PI * 2);
    ctx.fill();

    ctx.fillStyle = '#fff';
    ctx.font = 'bold 14px Inter, sans-serif';
    ctx.textAlign = 'center';
    ctx.fillText('GO', cx, cy + 5);
  }

  spinWheel() {
    if (this.wheel.isSpinning) return;
    this.wheel.isSpinning = true;

    const spins = 5 + Math.random() * 3;
    const extraAngle = Math.random() * Math.PI * 2;
    const targetRotation = this.wheel.currentRotation + spins * Math.PI * 2 + extraAngle;
    const startRotation = this.wheel.currentRotation;
    const duration = this.config.wheelSpinDuration;
    const start = performance.now();

    const animate = (currentTime) => {
      const elapsed = currentTime - start;
      const progress = Math.min(elapsed / duration, 1);
      const eased = 1 - Math.pow(1 - progress, 4);
      this.wheel.currentRotation = startRotation + (targetRotation - startRotation) * eased;
      this.drawWheel();

      if (progress < 1) {
        requestAnimationFrame(animate);
      } else {
        this.wheel.currentRotation = targetRotation % (Math.PI * 2);
        this.wheel.isSpinning = false;
        const winnerIndex = this.getWheelWinner();
        this.toast(`You won: ${this.wheel.segments[winnerIndex]?.label || 'Prize'}!`, 'success');
        if (this.wheel.onComplete) this.wheel.onComplete(winnerIndex);
        this.trackEvent('wheel_spin', { prize: this.wheel.segments[winnerIndex]?.label });
      }
    };

    requestAnimationFrame(animate);
  }

  getWheelWinner() {
    const sliceAngle = (Math.PI * 2) / this.wheel.segments.length;
    const normalized = ((this.wheel.currentRotation % (Math.PI * 2)) + Math.PI * 2) % (Math.PI * 2);
    return Math.floor(normalized / sliceAngle) % this.wheel.segments.length;
  }

  setupScratchCard() {
    const canvas = this.elements.scratchCanvas;
    if (!canvas) return;

    this.scratch = {
      canvas,
      ctx: canvas.getContext('2d'),
      isRevealed: false,
      percentRevealed: 0,
      radius: this.config.scratchCardSize,
      isDrawing: false,
    };

    const rect = canvas.getBoundingClientRect();
    canvas.width = rect.width;
    canvas.height = rect.height;

    this.scratch.ctx.fillStyle = '#c0c0c0';
    this.scratch.ctx.fillRect(0, 0, canvas.width, canvas.height);
    this.scratch.ctx.fillStyle = '#999';
    this.scratch.ctx.font = 'bold 16px Inter, sans-serif';
    this.scratch.ctx.textAlign = 'center';
    this.scratch.ctx.fillText('Scratch here!', canvas.width / 2, canvas.height / 2 + 6);

    const getPos = (e) => {
      const r = canvas.getBoundingClientRect();
      const touch = e.touches?.[0];
      return {
        x: (touch ? touch.clientX : e.clientX) - r.left,
        y: (touch ? touch.clientY : e.clientY) - r.top,
      };
    };

    const scratch = (pos) => {
      if (this.scratch.isRevealed) return;
      this.scratch.ctx.globalCompositeOperation = 'destination-out';
      this.scratch.ctx.beginPath();
      this.scratch.ctx.arc(pos.x, pos.y, this.scratch.radius, 0, Math.PI * 2);
      this.scratch.ctx.fill();
      this.scratch.ctx.globalCompositeOperation = 'source-over';
      this.checkScratchReveal();
    };

    this.addEvent(canvas, 'mousedown', (e) => { this.scratch.isDrawing = true; scratch(getPos(e)); });
    this.addEvent(canvas, 'mousemove', (e) => { if (this.scratch.isDrawing) scratch(getPos(e)); });
    this.addEvent(canvas, 'mouseup', () => { this.scratch.isDrawing = false; });
    this.addEvent(canvas, 'mouseleave', () => { this.scratch.isDrawing = false; });
    this.addEvent(canvas, 'touchstart', (e) => { this.scratch.isDrawing = true; scratch(getPos(e)); });
    this.addEvent(canvas, 'touchmove', (e) => { e.preventDefault(); if (this.scratch.isDrawing) scratch(getPos(e)); });
    this.addEvent(canvas, 'touchend', () => { this.scratch.isDrawing = false; });
  }

  checkScratchReveal() {
    const { canvas, ctx } = this.scratch;
    const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
    const pixels = imageData.data;
    let transparent = 0;
    const total = pixels.length / 4;

    for (let i = 3; i < pixels.length; i += 4) {
      if (pixels[i] === 0) transparent++;
    }

    this.scratch.percentRevealed = (transparent / total) * 100;

    if (this.scratch.percentRevealed > 50 && !this.scratch.isRevealed) {
      this.scratch.isRevealed = true;
      ctx.clearRect(0, 0, canvas.width, canvas.height);
      const event = new CustomEvent('scratch:revealed', { detail: { canvas } });
      document.dispatchEvent(event);
      this.toast('Scratch card revealed!', 'success');
      this.trackEvent('scratch_card_revealed');
    }
  }

  setupFaucetClaim() {
    const { claimButton, claimResult, faucetTimer } = this.elements;
    if (!claimButton) return;

    this.addEvent(claimButton, 'click', async () => {
      if (claimButton.classList.contains('claiming') || claimButton.disabled) return;

      claimButton.classList.add('claiming');
      claimButton.disabled = true;
      claimButton.innerHTML = '<span class="spinner"></span> Claiming...';

      if (claimResult) {
        claimResult.innerHTML = '';
        claimResult.className = 'claim-result';
      }

      try {
        const response = await ZynAjax.post(`${APP_URL}/api/faucet/claim`);
        if (response.success) {
          const amount = response.amount || 0;
          this.state.balance += amount;

          if (claimResult) {
            claimResult.className = 'claim-result claim-success';
            claimResult.innerHTML = `
              <div class="claim-amount">+${this.formatCurrency(amount)}</div>
              <div class="claim-message">${response.message || 'Successfully claimed!'}</div>
            `;
          }

          this.updateBalanceDisplay();
          this.fireConfetti();
          this.playAudio('earn');
          this.toast(`Claimed ${this.formatCurrency(amount)}!`, 'success');
          this.trackEvent('faucet_claim', { amount });
        } else {
          throw new Error(response.message || 'Claim failed');
        }
      } catch (error) {
        if (claimResult) {
          claimResult.className = 'claim-result claim-error';
          claimResult.innerHTML = `<div class="claim-message">${error.message}</div>`;
        }
        this.toast(error.message, 'error');
      } finally {
        claimButton.classList.remove('claiming');
        claimButton.disabled = false;
        claimButton.innerHTML = '<span class="claim-icon">⚡</span> Claim Now';
      }
    });

    if (faucetTimer) {
      const remaining = parseInt(faucetTimer.getAttribute('data-remaining') || '0', 10);
      if (remaining > 0) {
        this.startFaucetCountdown(remaining);
      }
    }
  }

  startFaucetCountdown(seconds) {
    const { claimButton, faucetTimer } = this.elements;
    if (!faucetTimer) return;

    claimButton.disabled = true;

    const update = () => {
      if (seconds <= 0) {
        claimButton.disabled = false;
        faucetTimer.textContent = '';
        clearInterval(interval);
        return;
      }
      const mins = Math.floor(seconds / 60);
      const secs = seconds % 60;
      faucetTimer.textContent = `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
      seconds--;
    };

    update();
    const interval = setInterval(update, 1000);
    this.intervals.push(interval);
  }

  setupSkeletons() {
    const { skeletonElements } = this.elements;
    skeletonElements.forEach((el) => {
      const type = el.getAttribute('data-skeleton') || 'text';
      el.className = `skeleton skeleton-${type}`;

      setTimeout(() => {
        el.classList.add('skeleton-loaded');
        el.className = el.className.replace(/skeleton[-\w]*\s?/g, '');
      }, this.config.skeletonDelay);
    });
  }

  setupPageTransitions() {
    document.addEventListener('click', (e) => {
      const link = e.target.closest('a[data-transition]');
      if (!link) return;
      e.preventDefault();
      const href = link.getAttribute('href');
      if (!href || href === '#') return;

      this.pageTransition(href);
    });

    window.addEventListener('popstate', () => {
      this.pageTransition(window.location.href, true);
    });
  }

  pageTransition(url, isPop = false) {
    const overlay = document.createElement('div');
    overlay.className = 'page-transition';
    document.body.appendChild(overlay);

    requestAnimationFrame(() => overlay.classList.add('page-transition-active'));

    setTimeout(() => {
      if (!isPop) window.location.href = url;
    }, this.config.pageTransitionDuration);
  }

  setupDropdowns() {
    const { dropdownToggles } = this.elements;

    dropdownToggles.forEach((toggle) => {
      this.addEvent(toggle, 'click', (e) => {
        e.stopPropagation();
        const menu = toggle.nextElementSibling;
        if (!menu || !menu.matches('[data-dropdown-menu]')) return;

        const isOpen = menu.classList.contains('dropdown-open');
        this.closeAllDropdowns();
        if (!isOpen) {
          menu.classList.add('dropdown-open');
          toggle.setAttribute('aria-expanded', 'true');
        }
      });
    });

    document.addEventListener('click', () => this.closeAllDropdowns());
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') this.closeAllDropdowns();
    });
  }

  closeAllDropdowns() {
    document.querySelectorAll('[data-dropdown-menu]').forEach((menu) => {
      menu.classList.remove('dropdown-open');
    });
    document.querySelectorAll('[data-dropdown-toggle]').forEach((toggle) => {
      toggle.setAttribute('aria-expanded', 'false');
    });
  }

  setupTooltips() {
    const { tooltips } = this.elements;
    tooltips.forEach((el) => {
      const text = el.getAttribute('data-tooltip');
      const position = el.getAttribute('data-tooltip-position') || 'top';
      if (!text) return;

      const tooltip = document.createElement('span');
      tooltip.className = `tooltip tooltip-${position}`;
      tooltip.textContent = text;
      tooltip.setAttribute('role', 'tooltip');
      el.appendChild(tooltip);

      this.addEvent(el, 'mouseenter', () => tooltip.classList.add('tooltip-visible'));
      this.addEvent(el, 'mouseleave', () => tooltip.classList.remove('tooltip-visible'));
    });
  }

  setupTabs() {
    const { tabGroups } = this.elements;
    tabGroups.forEach((group) => {
      const tabs = group.querySelectorAll('[data-tab]');
      const panels = group.querySelectorAll('[data-tab-panel]');

      tabs.forEach((tab) => {
        this.addEvent(tab, 'click', () => {
          const target = tab.getAttribute('data-tab');
          tabs.forEach((t) => t.classList.remove('tab-active'));
          panels.forEach((p) => p.classList.remove('tab-panel-active'));
          tab.classList.add('tab-active');
          const panel = group.querySelector(`[data-tab-panel="${target}"]`);
          if (panel) panel.classList.add('tab-panel-active');
        });
      });

      const activeTab = group.querySelector('[data-tab].tab-active');
      if (!activeTab) tabs[0]?.click();
    });
  }

  setupAccordion() {
    const { accordionItems } = this.elements;
    accordionItems.forEach((item) => {
      const header = item.querySelector('[data-accordion-header]');
      const content = item.querySelector('[data-accordion-content]');
      if (!header || !content) return;

      this.addEvent(header, 'click', () => {
        const isOpen = item.classList.contains('accordion-open');

        if (item.hasAttribute('data-accordion-single')) {
          accordionItems.forEach((ai) => {
            ai.classList.remove('accordion-open');
            ai.querySelector('[data-accordion-content]')?.style?.setProperty('max-height', '0');
          });
        }

        if (isOpen) {
          item.classList.remove('accordion-open');
          content.style.maxHeight = '0';
        } else {
          item.classList.add('accordion-open');
          content.style.maxHeight = `${content.scrollHeight}px`;
        }
      });
    });
  }

  setupStarRating() {
    const { starRatings } = this.elements;
    starRatings.forEach((container) => {
      const max = parseInt(container.getAttribute('data-star-rating') || '5', 10);
      const initial = parseInt(container.getAttribute('data-star-initial') || '0', 10);
      const readonly = container.hasAttribute('data-star-readonly');
      const inputs = [];

      container.innerHTML = '';

      for (let i = 1; i <= max; i++) {
        const star = document.createElement('span');
        star.className = `star ${i <= initial ? 'star-active' : ''}`;
        star.textContent = i <= initial ? '★' : '☆';
        star.dataset.value = i;
        star.setAttribute('role', 'button');
        star.setAttribute('tabindex', '0');
        star.setAttribute('aria-label', `${i} star${i > 1 ? 's' : ''}`);
        container.appendChild(star);
        inputs.push(star);

        if (!readonly) {
          this.addEvent(star, 'mouseenter', () => {
            inputs.forEach((s, idx) => {
              s.textContent = idx < i ? '★' : '☆';
              s.classList.toggle('star-hover', idx < i);
            });
          });

          this.addEvent(star, 'click', () => {
            inputs.forEach((s, idx) => {
              s.classList.toggle('star-active', idx < i);
              s.textContent = idx < i ? '★' : '☆';
            });
            container.dataset.value = i;
            const event = new CustomEvent('rating:change', { detail: { value: i, container } });
            document.dispatchEvent(event);
            this.trackEvent('star_rating', { value: i });
          });
        }
      }

      this.addEvent(container, 'mouseleave', () => {
        if (readonly) return;
        const active = parseInt(container.dataset.value || '0', 10);
        inputs.forEach((s, idx) => {
          s.textContent = idx < active ? '★' : '☆';
          s.classList.remove('star-hover');
        });
      });
    });
  }

  setupFileUpload() {
    const { fileInputs } = this.elements;
    fileInputs.forEach((input) => {
      const preview = input.closest('.form-group')?.querySelector('[data-file-preview]');
      const label = input.closest('.form-group')?.querySelector('.file-upload-label');

      this.addEvent(input, 'change', () => {
        const file = input.files?.[0];
        if (!file) {
          if (preview) preview.innerHTML = '';
          if (label) label.textContent = label.getAttribute('data-label') || 'Choose file';
          return;
        }

        if (label) label.textContent = file.name;

        if (preview) {
          preview.innerHTML = '';
          if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = (e) => {
              const img = document.createElement('img');
              img.src = e.target.result;
              img.className = 'file-preview-image';
              preview.appendChild(img);
            };
            reader.readAsDataURL(file);
          } else {
            preview.innerHTML = `<div class="file-preview-icon">📄 ${file.name}</div>`;
          }
        }

        const maxSize = parseInt(input.getAttribute('data-max-size') || '0', 10);
        if (maxSize && file.size > maxSize) {
          this.toast(`File size exceeds ${this.formatFileSize(maxSize)}`, 'error');
          input.value = '';
          if (label) label.textContent = label.getAttribute('data-label') || 'Choose file';
          if (preview) preview.innerHTML = '';
        }
      });
    });
  }

  setupLazyLoading() {
    const { lazyImages } = this.elements;
    if (!lazyImages.length) return;

    const observer = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          const img = entry.target;
          const src = img.getAttribute('data-src');
          if (src) {
            img.src = src;
            img.addEventListener('load', () => img.classList.add('loaded'));
            img.addEventListener('error', () => img.classList.add('load-error'));
            img.removeAttribute('data-src');
          }
          observer.unobserve(img);
        }
      });
    }, {
      rootMargin: '200px 0px',
      threshold: 0.01,
    });

    lazyImages.forEach((img) => observer.observe(img));
    this.observers.push(observer);
  }

  setupInfiniteScroll() {
    const sentinel = document.querySelector('[data-infinite-scroll]');
    if (!sentinel) return;

    const loadMore = sentinel.getAttribute('data-infinite-scroll');
    const container = document.querySelector(sentinel.getAttribute('data-infinite-container') || '[data-infinite-container]');

    const observer = new IntersectionObserver(async (entries) => {
      if (entries[0].isIntersecting && this.state.hasMorePages && !this.state.isLoadingMore) {
        this.state.isLoadingMore = true;
        this.state.currentPage++;

        try {
          const response = await ZynAjax.get(`${APP_URL}${loadMore}`, { page: this.state.currentPage });

          if (response.data?.length) {
            response.data.forEach((item) => {
              const el = this.createInfiniteItem(item);
              if (el && container) container.appendChild(el);
            });
          }

          this.state.hasMorePages = response.hasMorePages ?? (response.data?.length > 0);
        } catch (error) {
          this.state.currentPage--;
          this.toast('Failed to load more items', 'error');
        } finally {
          this.state.isLoadingMore = false;
        }
      }
    }, { rootMargin: `${this.config.infiniteScrollThreshold}px` });

    observer.observe(sentinel);
    this.observers.push(observer);
  }

  createInfiniteItem(item) {
    const template = document.querySelector('[data-infinite-template]');
    if (!template) return null;
    const clone = template.content.cloneNode(true);
    Object.entries(item).forEach(([key, value]) => {
      const el = clone.querySelector(`[data-field="${key}"]`);
      if (el) el.textContent = value;
    });
    return clone;
  }

  setupBackToTop() {
    const { backToTop } = this.elements;
    if (!backToTop) return;

    this.addEvent(window, 'scroll', () => {
      if (window.pageYOffset > 300) {
        backToTop.classList.add('back-to-top-visible');
      } else {
        backToTop.classList.remove('back-to-top-visible');
      }
    }, { passive: true });

    this.addEvent(backToTop, 'click', () => {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  }

  setupSearchFilters() {
    const { searchInput } = this.elements;
    if (!searchInput) return;

    let searchTimeout;

    this.addEvent(searchInput, 'input', () => {
      clearTimeout(searchTimeout);
      const query = searchInput.value.trim();

      searchTimeout = setTimeout(() => {
        const filters = this.getActiveFilters();
        this.performSearch(query, filters);
      }, 300);
    });

    document.querySelectorAll('[data-filter]').forEach((filter) => {
      this.addEvent(filter, 'change', () => {
        const query = searchInput.value.trim();
        const filters = this.getActiveFilters();
        this.performSearch(query, filters);
      });
    });
  }

  getActiveFilters() {
    const filters = {};
    document.querySelectorAll('[data-filter]').forEach((el) => {
      const key = el.getAttribute('data-filter');
      const value = el.type === 'checkbox' ? (el.checked ? el.value : '') : el.value;
      if (value) filters[key] = value;
    });
    return filters;
  }

  async performSearch(query, filters = {}) {
    const { searchResults } = this.elements;
    if (!searchResults) return;
    if (!query && !Object.keys(filters).length) {
      searchResults.innerHTML = '';
      searchResults.classList.remove('search-active');
      return;
    }

    searchResults.classList.add('search-loading');
    searchResults.innerHTML = '<div class="search-skeleton"><div class="skeleton-text"></div></div>';

    try {
      const params = { q: query, ...filters };
      const response = await ZynAjax.get(`${APP_URL}/api/search`, params);

      if (response.data?.length) {
        searchResults.classList.remove('search-loading');
        searchResults.classList.add('search-active');
        searchResults.innerHTML = response.data.map((item) => `
          <a href="${item.url || '#'}" class="search-result-item">
            <div class="search-result-title">${this.escapeHtml(item.title)}</div>
            <div class="search-result-desc">${this.escapeHtml(item.description || '')}</div>
          </a>
        `).join('');
        this.trackEvent('search_performed', { query, results: response.data.length });
      } else {
        searchResults.classList.remove('search-loading');
        searchResults.classList.add('search-active');
        searchResults.innerHTML = '<div class="search-no-results">No results found</div>';
      }
    } catch {
      searchResults.classList.remove('search-loading');
      searchResults.innerHTML = '<div class="search-error">Search failed. Try again.</div>';
    }
  }

  setupFormWizard() {
    document.querySelectorAll('[data-wizard]').forEach((wizard) => {
      const steps = wizard.querySelectorAll('[data-wizard-step]');
      const prevBtn = wizard.querySelector('[data-wizard-prev]');
      const nextBtn = wizard.querySelector('[data-wizard-next]');
      const submitBtn = wizard.querySelector('[data-wizard-submit]');
      const progress = wizard.querySelector('[data-wizard-progress]');
      let currentStep = 0;

      const showStep = (index) => {
        steps.forEach((step, i) => {
          step.classList.toggle('wizard-step-active', i === index);
          step.classList.toggle('wizard-step-completed', i < index);
        });

        if (prevBtn) prevBtn.style.display = index === 0 ? 'none' : 'inline-flex';
        if (nextBtn) nextBtn.style.display = index === steps.length - 1 ? 'none' : 'inline-flex';
        if (submitBtn) submitBtn.style.display = index === steps.length - 1 ? 'inline-flex' : 'none';

        if (progress) {
          const pct = ((index + 1) / steps.length) * 100;
          progress.style.width = `${pct}%`;
          progress.setAttribute('aria-valuenow', Math.round(pct));
        }
      };

      if (nextBtn) {
        this.addEvent(nextBtn, 'click', () => {
          const current = steps[currentStep];
          const inputs = current.querySelectorAll('[required]');
          let valid = true;
          inputs.forEach((input) => {
            if (!this.validateField(input)) valid = false;
          });
          if (valid && currentStep < steps.length - 1) {
            currentStep++;
            showStep(currentStep);
          }
        });
      }

      if (prevBtn) {
        this.addEvent(prevBtn, 'click', () => {
          if (currentStep > 0) {
            currentStep--;
            showStep(currentStep);
          }
        });
      }

      showStep(0);
    });
  }

  setupConfetti() {
    this.confettiCanvas = null;
    this.confettiParticles = [];
    this.confettiAnimating = false;
  }

  fireConfetti() {
    if (this.confettiCanvas) {
      this.confettiCanvas.remove();
      this.confettiCanvas = null;
    }

    const canvas = document.createElement('canvas');
    canvas.className = 'confetti-canvas';
    canvas.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;pointer-events:none;z-index:99999';
    document.body.appendChild(canvas);
    this.confettiCanvas = canvas;

    const ctx = canvas.getContext('2d');
    canvas.width = window.innerWidth;
    canvas.height = window.innerHeight;

    this.confettiParticles = Array.from({ length: this.config.confettiCount }, () => ({
      x: Math.random() * canvas.width,
      y: Math.random() * canvas.height - canvas.height,
      w: Math.random() * 10 + 5,
      h: Math.random() * 6 + 3,
      color: this.config.confettiColors[Math.floor(Math.random() * this.config.confettiColors.length)],
      velocity: Math.random() * 3 + 2,
      rotation: Math.random() * 360,
      rotationSpeed: Math.random() * 10 - 5,
      opacity: Math.random() * 0.5 + 0.5,
    }));

    if (this.confettiAnimating) return;
    this.confettiAnimating = true;

    const animate = () => {
      if (!this.confettiCanvas) {
        this.confettiAnimating = false;
        return;
      }

      ctx.clearRect(0, 0, canvas.width, canvas.height);

      let active = 0;
      this.confettiParticles.forEach((p) => {
        if (p.y > canvas.height + 20) return;
        active++;
        p.y += p.velocity;
        p.rotation += p.rotationSpeed;
        ctx.save();
        ctx.translate(p.x, p.y);
        ctx.rotate((p.rotation * Math.PI) / 180);
        ctx.globalAlpha = p.opacity;
        ctx.fillStyle = p.color;
        ctx.fillRect(-p.w / 2, -p.h / 2, p.w, p.h);
        ctx.restore();
      });

      if (active > 0) {
        requestAnimationFrame(animate);
      } else {
        this.confettiAnimating = false;
        canvas.remove();
        this.confettiCanvas = null;
      }
    };

    requestAnimationFrame(animate);

    setTimeout(() => {
      if (this.confettiCanvas) {
        this.confettiAnimating = false;
        this.confettiCanvas.remove();
        this.confettiCanvas = null;
      }
    }, 10000);
  }

  setupParticles() {
    const container = document.querySelector('[data-particles]');
    if (!container) return;
    container.innerHTML = '';

    for (let i = 0; i < this.config.particleCount; i++) {
      const particle = document.createElement('div');
      particle.className = 'particle';
      const size = Math.random() * 4 + 1;
      particle.style.cssText = `
        position: absolute;
        width: ${size}px;
        height: ${size}px;
        background: rgba(255, 255, 255, ${Math.random() * 0.5 + 0.1});
        border-radius: 50%;
        left: ${Math.random() * 100}%;
        top: ${Math.random() * 100}%;
        animation: particle-float ${Math.random() * 10 + 10}s linear infinite;
        animation-delay: ${Math.random() * 5}s;
        pointer-events: none;
      `;
      container.appendChild(particle);
    }

    const style = document.createElement('style');
    style.textContent = `
      @keyframes particle-float {
        0% { transform: translateY(0) translateX(0); opacity: 0; }
        10% { opacity: 1; }
        90% { opacity: 1; }
        100% { transform: translateY(-100vh) translateX(${Math.random() * 200 - 100}px); opacity: 0; }
      }
    `;
    document.head.appendChild(style);
  }

  setupTypingEffect() {
    const elements = document.querySelectorAll('[data-typing]');
    elements.forEach((el) => {
      const text = el.getAttribute('data-typing') || el.textContent;
      el.textContent = '';
      el.classList.add('typing-cursor');

      this.typeText(el, text);
    });
  }

  typeText(el, text, callback) {
    let index = 0;
    const type = () => {
      if (index < text.length) {
        el.textContent += text[index];
        index++;
        setTimeout(type, this.config.typingSpeed);
      } else if (callback) {
        callback();
      }
    };
    type();
  }

  setupParallax() {
    document.querySelectorAll('[data-parallax]').forEach((el) => {
      const speed = parseFloat(el.getAttribute('data-parallax')) || this.config.parallaxFactor;

      this.addEvent(window, 'scroll', () => {
        const rect = el.getBoundingClientRect();
        if (rect.top < window.innerHeight && rect.bottom > 0) {
          const offset = (window.innerHeight - rect.top) * speed;
          el.style.transform = `translateY(${offset * 0.1}px)`;
        }
      }, { passive: true });
    });
  }

  setupAudioFeedback() {
    const { audioElements } = this.elements;

    Object.entries(audioElements).forEach(([key, el]) => {
      if (el) {
        el.volume = 0.3;
      }
    });
  }

  playAudio(type) {
    const el = this.elements.audioElements?.[type];
    if (el) {
      el.currentTime = 0;
      el.play().catch(() => {});
    }
  }

  setupKeyboardShortcuts() {
    this.addEvent(document, 'keydown', (e) => {
      if (e.ctrlKey || e.metaKey) {
        switch (e.key) {
          case '/':
            e.preventDefault();
            this.elements.searchInput?.focus();
            break;
          case 'd':
            e.preventDefault();
            this.elements.themeToggle?.click();
            break;
          case 'b':
            e.preventDefault();
            window.scrollTo({ top: 0, behavior: 'smooth' });
            break;
        }
      }

      if (e.key === '?' && !e.ctrlKey && !e.metaKey) {
        this.showKeyboardShortcutsHelp();
      }
    });
  }

  showKeyboardShortcutsHelp() {
    const modalId = 'keyboard-shortcuts-modal';
    const existing = document.querySelector(`[data-modal="${modalId}"]`);
    if (existing) {
      this.openModal(modalId);
      return;
    }

    const shortcuts = [
      { keys: 'Ctrl + /', desc: 'Search' },
      { keys: 'Ctrl + D', desc: 'Toggle theme' },
      { keys: 'Ctrl + B', desc: 'Scroll to top' },
      { keys: '?', desc: 'Show this help' },
      { keys: 'Esc', desc: 'Close modal / dropdown' },
    ];

    const modal = document.createElement('div');
    modal.className = 'modal';
    modal.setAttribute('data-modal', modalId);
    modal.innerHTML = `
      <div class="modal-overlay"></div>
      <div class="modal-content modal-sm">
        <div class="modal-header">
          <h3>Keyboard Shortcuts</h3>
          <button class="modal-close" data-modal-close>&times;</button>
        </div>
        <div class="modal-body">
          <div class="shortcuts-list">
            ${shortcuts.map((s) => `
              <div class="shortcut-item">
                <kbd>${s.keys}</kbd>
                <span>${s.desc}</span>
              </div>
            `).join('')}
          </div>
        </div>
      </div>
    `;
    document.body.appendChild(modal);
    this.setupModals();
    this.openModal(modalId);
  }

  setupOnlineDetection() {
    this.addEvent(window, 'online', () => {
      this.state.isOnline = true;
      document.body.classList.remove('offline');
      this.toast('You are back online!', 'success');
      this.startBalancePolling();
      this.startNotificationPolling();
      this.trackEvent('connection_restored');
    });

    this.addEvent(window, 'offline', () => {
      this.state.isOnline = false;
      document.body.classList.add('offline');
      this.toast('You are offline. Some features may be unavailable.', 'warning', 0);
      this.stopPolling();
      this.trackEvent('connection_lost');
    });

    if (!navigator.onLine) {
      document.body.classList.add('offline');
    }
  }

  setupServiceWorker() {
    if ('serviceWorker' in navigulator) {
      window.addEventListener('load', () => {
        navigator.serviceWorker.register(`${APP_URL}/sw.js`).then(
          (registration) => {
            this.trackEvent('sw_registered', { scope: registration.scope });
          },
          (error) => {
            console.warn('ServiceWorker registration failed:', error);
          }
        );
      });
    }
  }

  setupAnalytics() {
    this.analytics = {
      enabled: true,
      queue: [],
      flushInterval: 5000,
    };

    if (this.analytics.flushInterval > 0) {
      const interval = setInterval(() => this.flushAnalytics(), this.analytics.flushInterval);
      this.intervals.push(interval);
    }

    this.trackEvent('page_view', {
      path: window.location.pathname,
      title: document.title,
      referrer: document.referrer,
    });
  }

  trackEvent(eventName, data = {}) {
    if (!this.analytics?.enabled) return;

    const event = {
      event: eventName,
      data: {
        ...data,
        url: window.location.href,
        timestamp: new Date().toISOString(),
        screen: `${window.innerWidth}x${window.innerHeight}`,
        theme: this.state.theme,
        version: APP_VERSION,
      },
    };

    this.analytics.queue.push(event);

    if (this.analytics.queue.length >= 10) {
      this.flushAnalytics();
    }

    if (window.gtag) {
      window.gtag('event', eventName, event.data);
    }
  }

  flushAnalytics() {
    if (!this.analytics.queue.length) return;

    const batch = this.analytics.queue.splice(0, this.analytics.queue.length);

    if (navigator.sendBeacon) {
      navigator.sendBeacon(`${APP_URL}/api/analytics`, JSON.stringify(batch));
    } else {
      ZynAjax.post(`${APP_URL}/api/analytics`, { events: batch }).catch(() => {});
    }
  }

  setupAutoDismissAlerts() {
    document.querySelectorAll('[data-alert-dismiss]').forEach((alert) => {
      const delay = parseInt(alert.getAttribute('data-alert-dismiss'), 10) || this.config.alertDismissDelay;
      setTimeout(() => {
        alert.classList.add('alert-dismissing');
        setTimeout(() => alert.remove(), 300);
      }, delay);
    });

    document.addEventListener('click', (e) => {
      const close = e.target.closest('[data-alert-close]');
      if (close) {
        const alert = close.closest('[data-alert-dismiss]') || close.closest('.alert');
        if (alert) {
          alert.classList.add('alert-dismissing');
          setTimeout(() => alert.remove(), 300);
        }
      }
    });
  }

  setupLiveActivityFeed() {
    const { activityFeed } = this.elements;
    if (!activityFeed) return;

    if (!activityFeed.hasAttribute('data-activity-initialized')) {
      activityFeed.setAttribute('data-activity-initialized', 'true');
      this.loadActivityFeed();
    }
  }

  async loadActivityFeed() {
    const { activityFeed } = this.elements;
    if (!activityFeed) return;

    try {
      const response = await ZynAjax.get(`${APP_URL}/api/activities`, { limit: 20 });
      if (response.data?.length) {
        activityFeed.innerHTML = response.data.map((activity) => `
          <div class="activity-item ${activity.type || ''}">
            <div class="activity-avatar">
              ${activity.avatar ? `<img src="${activity.avatar}" alt="">` : '<div class="activity-avatar-placeholder"></div>'}
            </div>
            <div class="activity-content">
              <div class="activity-text">${this.escapeHtml(activity.text || '')}</div>
              <div class="activity-time">${this.timeAgo(activity.created_at || activity.timestamp)}</div>
            </div>
          </div>
        `).join('');
      }
    } catch {
      // Silently fail for activity feed
    }
  }

  setupNotificationCenter() {
    const { notificationCenter, notificationBadge } = this.elements;
    if (!notificationCenter) return;

    this.addEvent(notificationCenter.querySelector('[data-notifications-toggle]') || notificationCenter, 'click', (e) => {
      const panel = notificationCenter.querySelector('[data-notifications-panel]');
      if (panel) {
        panel.classList.toggle('notifications-panel-open');
        if (panel.classList.contains('notifications-panel-open')) {
          this.markNotificationsRead();
        }
      }
    });

    document.addEventListener('click', (e) => {
      if (!e.target.closest('[data-notification-center]')) {
        document.querySelectorAll('[data-notifications-panel]').forEach((p) => {
          p.classList.remove('notifications-panel-open');
        });
      }
    });
  }

  startBalancePolling() {
    if (!this.state.isOnline) return;
    this.stopBalancePolling();
    this.balanceInterval = setInterval(() => this.fetchBalance(), this.config.balancePollInterval);
    this.intervals.push(this.balanceInterval);
  }

  stopBalancePolling() {
    if (this.balanceInterval) {
      clearInterval(this.balanceInterval);
      this.balanceInterval = null;
    }
  }

  async fetchBalance() {
    try {
      const response = await ZynAjax.get(`${APP_URL}/api/balance`);
      if (response.balance !== undefined) {
        this.state.balance = response.balance;
        this.updateBalanceDisplay();
      }
    } catch {
      // Silently fail for polling
    }
  }

  updateBalanceDisplay() {
    const { balanceDisplay } = this.elements;
    if (!balanceDisplay) return;

    const current = parseFloat(balanceDisplay.getAttribute('data-balance-display') || '0');
    balanceDisplay.setAttribute('data-balance-display', this.state.balance);

    if (current !== this.state.balance) {
      balanceDisplay.textContent = this.formatCurrency(this.state.balance);
      balanceDisplay.classList.add('balance-updated');
      setTimeout(() => balanceDisplay.classList.remove('balance-updated'), 1000);
    }
  }

  startNotificationPolling() {
    if (!this.state.isOnline) return;
    this.stopNotificationPolling();
    this.notificationInterval = setInterval(() => this.fetchNotifications(), this.config.notificationPollInterval);
    this.intervals.push(this.notificationInterval);
  }

  stopNotificationPolling() {
    if (this.notificationInterval) {
      clearInterval(this.notificationInterval);
      this.notificationInterval = null;
    }
  }

  async fetchNotifications() {
    try {
      const response = await ZynAjax.get(`${APP_URL}/api/notifications`, { unread_only: true });
      if (response.data) {
        this.state.notifications = response.data;
        this.state.unreadCount = response.unread_count || response.data.length || 0;
        this.updateNotificationBadge();
        this.renderNotifications();
      }
    } catch {
      // Silently fail
    }
  }

  updateNotificationBadge() {
    const { notificationBadge } = this.elements;
    if (!notificationBadge) return;

    if (this.state.unreadCount > 0) {
      notificationBadge.textContent = this.state.unreadCount > 99 ? '99+' : this.state.unreadCount;
      notificationBadge.classList.add('badge-visible');
    } else {
      notificationBadge.classList.remove('badge-visible');
    }
  }

  renderNotifications() {
    const panel = document.querySelector('[data-notifications-panel]');
    if (!panel) return;

    const list = panel.querySelector('[data-notifications-list]');
    if (!list) return;

    if (!this.state.notifications.length) {
      list.innerHTML = '<div class="notifications-empty">No notifications</div>';
      return;
    }

    list.innerHTML = this.state.notifications.slice(0, 20).map((n) => `
      <div class="notification-item ${n.read ? '' : 'notification-unread'}" data-id="${n.id}">
        <div class="notification-icon">${this.getNotificationIcon(n.type)}</div>
        <div class="notification-body">
          <div class="notification-message">${this.escapeHtml(n.message)}</div>
          <div class="notification-time">${this.timeAgo(n.created_at)}</div>
        </div>
      </div>
    `).join('');
  }

  getNotificationIcon(type) {
    const icons = {
      earnings: '💰',
      bonus: '🎁',
      referral: '👥',
      achievement: '🏆',
      alert: '⚠️',
      info: 'ℹ️',
      withdraw: '💳',
      deposit: '📥',
    };
    return icons[type] || '🔔';
  }

  markNotificationsRead() {
    ZynAjax.post(`${APP_URL}/api/notifications/read`).catch(() => {});
    this.state.unreadCount = 0;
    this.updateNotificationBadge();

    document.querySelectorAll('[data-notifications-list] .notification-unread').forEach((el) => {
      el.classList.remove('notification-unread');
    });
  }

  stopPolling() {
    this.intervals.forEach((interval) => clearInterval(interval));
    this.intervals = [];
  }

  formatCurrency(amount, currency = 'USD') {
    const symbols = {
      USD: '$', EUR: '€', GBP: '£', BTC: '₿', INR: '₹',
      NGN: '₦', PHP: '₱', KRW: '₩', TRY: '₺', UAH: '₴',
    };
    const symbol = symbols[currency] || '$';
    const formatted = Number(amount).toFixed(2);
    return `${symbol}${Number(formatted).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
  }

  timeAgo(date) {
    if (!date) return '';
    const now = Date.now();
    const then = new Date(date).getTime();
    const seconds = Math.floor((now - then) / 1000);

    if (seconds < 5) return 'Just now';
    if (seconds < 60) return `${seconds}s ago`;
    const minutes = Math.floor(seconds / 60);
    if (minutes < 60) return `${minutes}m ago`;
    const hours = Math.floor(minutes / 60);
    if (hours < 24) return `${hours}h ago`;
    const days = Math.floor(hours / 24);
    if (days < 30) return `${days}d ago`;
    const months = Math.floor(days / 30);
    if (months < 12) return `${months}mo ago`;
    return `${Math.floor(months / 12)}y ago`;
  }

  formatFileSize(bytes) {
    if (bytes === 0) return '0 B';
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(1024));
    return `${(bytes / Math.pow(1024, i)).toFixed(1)} ${sizes[i]}`;
  }

  escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
  }

  safeParseJSON(str) {
    if (!str) return null;
    try {
      return JSON.parse(str);
    } catch {
      return null;
    }
  }

  formatNumber(num) {
    if (num >= 1000000) return `${(num / 1000000).toFixed(1)}M`;
    if (num >= 1000) return `${(num / 1000).toFixed(1)}K`;
    return num.toString();
  }

  debounce(fn, delay) {
    let timer;
    return (...args) => {
      clearTimeout(timer);
      timer = setTimeout(() => fn(...args), delay);
    };
  }

  throttle(fn, limit) {
    let inThrottle = false;
    return (...args) => {
      if (!inThrottle) {
        fn(...args);
        inThrottle = true;
        setTimeout(() => { inThrottle = false; }, limit);
      }
    };
  }

  addEvent(target, type, handler, options = {}) {
    if (!target) return;
    target.addEventListener(type, handler, options);
    this.eventListeners.push({ target, type, handler, options });
  }

  destroy() {
    this.stopPolling();
    this.observers.forEach((obs) => obs.disconnect());
    this.observers = [];
    this.eventListeners.forEach(({ target, type, handler, options }) => {
      target.removeEventListener(type, handler, options);
    });
    this.eventListeners = [];
    this.analytics.enabled = false;
    this.confettiAnimating = false;
    if (this.confettiCanvas) {
      this.confettiCanvas.remove();
      this.confettiCanvas = null;
    }
  }
}

const zynearn = new ZynEarn();

if (typeof module !== 'undefined' && module.exports) {
  module.exports = ZynEarn;
}

export default ZynEarn;
