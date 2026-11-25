// Усі запити проходять через api.php. Дані API вставляються тільки як текст.
'use strict';

const search = document.getElementById('search');
const results = document.getElementById('results');
const listStatus = document.getElementById('list-status');
const summary = document.getElementById('summary');
const form = document.getElementById('add-form');
const formStatus = document.getElementById('form-status');
const submitBtn = document.getElementById('submit-btn');
const details = document.getElementById('topic-details');
let listRequest = 0;
let detailRequest = 0;
let selectedId = null;

async function requestApi(params = {}, options = {}) {
    const query = new URLSearchParams({ resource: 'topics', ...params });
    const response = await fetch('api.php?' + query, options);
    const body = await response.json();
    if (!response.ok || body.success !== true) {
        const error = new Error(body.error || 'Помилка запиту.');
        error.fields = body.errors || {};
        throw error;
    }
    return body.data;
}

function element(tag, text, className) {
    const node = document.createElement(tag);
    if (text !== undefined) node.textContent = text;
    if (className) node.className = className;
    return node;
}

function showStatus(message, isError = false) {
    formStatus.hidden = false;
    formStatus.className = 'flash' + (isError ? ' flash-error' : '');
    formStatus.textContent = message;
}

function renderDetails(topic) {
    details.hidden = false;
    selectedId = topic.id;
    const content = document.getElementById('topic-content');
    content.replaceChildren(
        element('p', `#${topic.id} — ${topic.title}`),
        element('p', `Автор: ${topic.author} · Дата: ${topic.created_at}`),
        element('p', topic.is_pinned ? 'Закріплена тема' : 'Звичайна тема')
    );
}

async function showTopic(id) {
    const request = ++detailRequest;
    try {
        const topic = await requestApi({ id });
        if (request === detailRequest) renderDetails(topic);
    } catch (error) {
        if (request === detailRequest) showStatus(error.message, true);
    }
}

async function pinTopic(id, button) {
    button.disabled = true;
    try {
        const topic = await requestApi({ action: 'pin', id }, { method: 'POST' });
        if (selectedId === id) {
            ++detailRequest;
            renderDetails(topic);
        }
        showStatus(`Тему #${id} закріплено.`);
        await loadList();
    } catch (error) {
        showStatus(error.message, true);
    } finally {
        button.disabled = false;
    }
}

function renderTable(topics) {
    results.replaceChildren();
    summary.textContent = `Тем: ${topics.length} · Закріплених: ${topics.filter(t => t.is_pinned).length}`;
    if (topics.length === 0) {
        results.append(element('p', 'Тем не знайдено.', 'empty'));
        return;
    }
    const table = element('table');
    table.append(element('caption', 'Теми форуму', 'visually-hidden'));
    const head = element('thead');
    const header = element('tr');
    ['ID', 'Тема', 'Автор', 'Створено', 'Тип', 'Дія'].forEach(text => {
        const th = element('th', text);
        th.scope = 'col';
        header.append(th);
    });
    head.append(header);
    const tbody = element('tbody');
    for (const topic of topics) {
        const row = element('tr');
        row.append(element('td', topic.id));
        const titleCell = element('td', undefined, 'topic-title');
        const view = element('button', topic.title, 'topic-link');
        view.type = 'button';
        view.addEventListener('click', () => showTopic(topic.id));
        titleCell.append(view);
        row.append(titleCell, element('td', topic.author), element('td', topic.created_at));
        const typeCell = element('td');
        typeCell.append(element('span', topic.is_pinned ? 'Закріплена' : 'Звичайна',
            'label' + (topic.is_pinned ? ' label-hot' : '')));
        const actions = element('td', undefined, 'actions');
        if (!topic.is_pinned) {
            const pin = element('button', 'Закріпити', 'btn btn-outline');
            pin.type = 'button';
            pin.setAttribute('aria-label', `Закріпити тему #${topic.id}`);
            pin.addEventListener('click', () => pinTopic(topic.id, pin));
            actions.append(pin);
        } else {
            actions.textContent = '—';
        }
        row.append(typeCell, actions);
        tbody.append(row);
    }
    table.append(head, tbody);
    results.append(table);
}

async function loadList() {
    const request = ++listRequest;
    listStatus.hidden = false;
    listStatus.textContent = 'Завантаження…';
    try {
        const topics = await requestApi({ q: search.value.trim() });
        // Стара повільна відповідь не замінює результати нового пошуку.
        if (request !== listRequest) return;
        renderTable(topics);
        listStatus.hidden = true;
    } catch (error) {
        if (request !== listRequest) return;
        results.replaceChildren();
        summary.textContent = '';
        listStatus.textContent = error.message;
    }
}

form.addEventListener('submit', async event => {
    event.preventDefault();
    for (const name of ['title', 'author', 'is_pinned']) {
        document.getElementById('error-' + name).textContent = '';
        document.getElementById(name).removeAttribute('aria-invalid');
    }
    formStatus.hidden = true;
    submitBtn.disabled = true;
    try {
        const topic = await requestApi({}, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                title: form.elements.title.value,
                author: form.elements.author.value,
                is_pinned: form.elements.is_pinned.checked ? 1 : 0
            })
        });
        form.reset();
        const query = search.value.trim().toLocaleLowerCase('uk');
        const matches = topic.author.toLocaleLowerCase('uk').includes(query);
        showStatus(`Тему #${topic.id} додано.` + (matches ? '' : ' Змініть фільтр, щоб побачити її у списку.'));
        await loadList();
    } catch (error) {
        showStatus(error.message, true);
        for (const name of ['title', 'author', 'is_pinned']) {
            if (error.fields && error.fields[name]) {
                document.getElementById('error-' + name).textContent = error.fields[name];
                document.getElementById(name).setAttribute('aria-invalid', 'true');
            }
        }
    } finally {
        submitBtn.disabled = false;
    }
});

search.addEventListener('input', loadList);
document.getElementById('refresh').addEventListener('click', loadList);
document.getElementById('close-details').addEventListener('click', () => {
    ++detailRequest;
    selectedId = null;
    details.hidden = true;
});
loadList();
