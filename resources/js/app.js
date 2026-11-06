const appShell = document.querySelector('.app-shell, .admin-shell');
const sidebarToggle = document.querySelector('[data-sidebar-toggle]');
const sidebarCloseButtons = document.querySelectorAll('[data-sidebar-close]');

function closeSidebar() {
    appShell?.classList.remove('sidebar-open');
    sidebarToggle?.setAttribute('aria-expanded', 'false');
}

sidebarToggle?.addEventListener('click', () => {
    const isOpen = appShell?.classList.toggle('sidebar-open') ?? false;
    sidebarToggle.setAttribute('aria-expanded', String(isOpen));
});

sidebarCloseButtons.forEach((button) => {
    button.addEventListener('click', closeSidebar);
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        closeSidebar();
    }

    if (event.key === '/' && !['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement?.tagName)) {
        const search = document.querySelector('#global-event-search');
        if (search) {
            event.preventDefault();
            search.focus();
        }
    }
});

document.querySelectorAll('[data-dismiss]').forEach((button) => {
    button.addEventListener('click', () => {
        button.closest('.flash-message')?.remove();
    });
});

document.querySelectorAll('[data-password-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
        const input = button.closest('.password-input')?.querySelector('input');
        if (!input) {
            return;
        }

        const showPassword = input.type === 'password';
        input.type = showPassword ? 'text' : 'password';
        button.textContent = showPassword ? 'Hide' : 'Show';
        button.setAttribute('aria-label', showPassword ? 'Hide password' : 'Show password');
    });
});

document.querySelectorAll('form[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        if (!window.confirm(form.dataset.confirm)) {
            event.preventDefault();
        }
    });
});

document.querySelectorAll('form[data-loading-form]').forEach((form) => {
    form.addEventListener('submit', () => {
        const submitButton = form.querySelector('button[type="submit"]');
        if (submitButton) {
            submitButton.classList.add('is-loading');
            submitButton.disabled = true;
        }
    });
});

const jakartaDateTimeFormatter = new Intl.DateTimeFormat('sv-SE', {
    timeZone: document.documentElement.dataset.timezone || 'Asia/Jakarta',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    hourCycle: 'h23',
});

function jakartaDateTimeAfterNow() {
    const minimum = new Date();
    minimum.setMinutes(minimum.getMinutes() + 1, 0, 0);

    return jakartaDateTimeFormatter.format(minimum).replace(' ', 'T');
}

document.querySelectorAll('input[data-future-datetime]').forEach((input) => {
    const form = input.form;
    if (!form) {
        return;
    }

    const existingValue = input.dataset.existingValue ?? '';
    const relatedField = input.dataset.afterField
        ? form.elements.namedItem(input.dataset.afterField)
        : null;

    function validate() {
        const minimum = jakartaDateTimeAfterNow();
        const isUnchanged = existingValue !== '' && input.value === existingValue;
        const requiredMinimum = relatedField instanceof HTMLInputElement && relatedField.value
            ? relatedField.value
            : minimum;

        input.min = isUnchanged && input.value < minimum ? '' : requiredMinimum;
        let message = '';

        if (input.value && !isUnchanged && input.value < minimum) {
            message = input.name === 'due_at'
                ? 'Your deadline must be later than the current time. Please select a future date and time.'
                : 'Your event schedule must be later than the current time. Please select a future date and time.';
        } else if (input.value && relatedField instanceof HTMLInputElement && input.value <= relatedField.value) {
            message = 'The event end time must be later than its start time.';
        }

        input.setCustomValidity(message);
        input.toggleAttribute('aria-invalid', message !== '');
    }

    input.addEventListener('input', validate);
    input.addEventListener('change', validate);
    form.addEventListener('submit', validate, true);
    validate();
});

const notificationToggle = document.querySelector('[data-notification-toggle]');
const notificationPopover = document.querySelector('[data-notification-popover]');
const notificationList = document.querySelector('[data-notification-list]');
const notificationLoading = document.querySelector('[data-notification-loading]');

function setNotificationCount(count) {
    if (!notificationToggle) {
        return;
    }

    let badge = notificationToggle.querySelector('[data-notification-count]');

    if (count > 0 && !badge) {
        badge = document.createElement('span');
        badge.dataset.notificationCount = '';
        badge.className = 'topbar-notification-count';
        notificationToggle.appendChild(badge);
    }

    if (badge) {
        if (count > 0) {
            badge.textContent = count > 99 ? '99+' : String(count);
            badge.hidden = false;
        } else {
            badge.hidden = true;
        }
    }

    document.querySelectorAll('[data-notification-count]').forEach((countBadge) => {
        if (countBadge === badge) {
            return;
        }

        if (count > 0) {
            countBadge.textContent = count > 99 ? '99+' : String(count);
            countBadge.hidden = false;
        } else {
            countBadge.hidden = true;
        }
    });

    notificationToggle.setAttribute(
        'aria-label',
        count > 0 ? `Notifications, ${count} unread` : 'Notifications',
    );
}

function closeNotificationPopover() {
    if (!notificationToggle || !notificationPopover) {
        return;
    }

    notificationPopover.hidden = true;
    notificationToggle.setAttribute('aria-expanded', 'false');
}

async function refreshNotifications() {
    const feedUrl = notificationToggle?.dataset.notificationFeed;
    if (!feedUrl || !notificationList || !notificationLoading) {
        return;
    }

    try {
        const response = await fetch(feedUrl, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            throw new Error(`Notification feed returned ${response.status}`);
        }

        const payload = await response.json();
        setNotificationCount(Number(payload.count ?? 0));
        notificationLoading.hidden = true;
        notificationList.replaceChildren();

        if (!Array.isArray(payload.notifications) || payload.notifications.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'notification-popover-empty';
            empty.textContent = "You're all caught up.";
            notificationList.appendChild(empty);
            return;
        }

        payload.notifications.forEach((notification) => {
            const link = document.createElement('a');
            link.className = 'notification-popover-item';
            link.href = notification.url;

            const title = document.createElement('strong');
            title.textContent = notification.title;
            const message = document.createElement('span');
            message.textContent = notification.message;
            const time = document.createElement('time');
            time.textContent = notification.created_at;

            link.append(title, message, time);
            notificationList.appendChild(link);
        });
    } catch {
        notificationLoading.textContent = 'Notifications could not be loaded.';
        notificationLoading.hidden = false;
    }
}

notificationToggle?.addEventListener('click', () => {
    if (!notificationPopover) {
        return;
    }

    const shouldOpen = notificationPopover.hidden;
    notificationPopover.hidden = !shouldOpen;
    notificationToggle.setAttribute('aria-expanded', String(shouldOpen));

    if (shouldOpen) {
        refreshNotifications();
    }
});

document.addEventListener('click', (event) => {
    if (notificationPopover?.hidden) {
        return;
    }

    if (!event.target.closest('.notification-popover-wrap')) {
        closeNotificationPopover();
    }
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        closeNotificationPopover();
    }
});

window.addEventListener('focus', refreshNotifications);
document.addEventListener('visibilitychange', () => {
    if (!document.hidden) {
        refreshNotifications();
    }
});

if (notificationToggle) {
    refreshNotifications();
    window.setInterval(() => {
        if (!document.hidden) {
            refreshNotifications();
        }
    }, 60_000);
}
