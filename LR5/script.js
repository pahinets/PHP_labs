// ПР5. AJAX: завантаження списку, живий пошук і додавання теми без перезавантаження сторінки.
// DOM будується через createElement/textContent, тому дані з БД не інтерпретуються як HTML (немає XSS).

const results = document.getElementById('results');
const summary = document.getElementById('summary');
const searchInput = document.getElementById('search');
const form = document.getElementById('add-form');
const formStatus = document.getElementById('form-status');
const submitBtn = document.getElementById('submit-btn');

let tbody = null;   // <tbody> поточної таблиці (null, якщо замість неї показано повідомлення)
let requestId = 0;  // номер останнього запиту списку: застарілі відповіді ігноруються

// ---------- Відображення ----------

function showMessage(text, isError) {
    tbody = null;
    const box = document.createElement('div');
    box.className = isError ? 'flash flash-error' : 'empty';
    box.textContent = text;
    results.replaceChildren(box);
    updateSummary();
}

function buildCell(text) {
    const td = document.createElement('td');
    td.textContent = text;
    return td;
}

function buildRow(topic) {
    const tr = document.createElement('tr');
    tr.append(buildCell(topic.id), buildCell(topic.title), buildCell(topic.author), buildCell(topic.created_at));

    const typeCell = document.createElement('td');
    const label = document.createElement('span');
    label.className = topic.is_pinned ? 'label label-hot' : 'label';
    label.textContent = topic.is_pinned ? 'Закріплена' : 'Звичайна';
    typeCell.append(label);
    tr.append(typeCell);

    return tr;
}

function renderTable(topics) {
    if (topics.length === 0) {
        showMessage('Тем не знайдено.', false);
        return;
    }

    const table = document.createElement('table');
    const headRow = document.createElement('tr');
    for (const name of ['ID', 'Тема', 'Автор', 'Створено', 'Тип']) {
        const th = document.createElement('th');
        th.textContent = name;
        headRow.append(th);
    }
    const thead = document.createElement('thead');
    thead.append(headRow);

    tbody = document.createElement('tbody');
    tbody.append(...topics.map(buildRow));

    table.append(thead, tbody);
    results.replaceChildren(table);
    updateSummary();
}

function updateSummary() {
    const rows = tbody ? Array.from(tbody.children) : [];
    const pinned = rows.filter((tr) => tr.querySelector('.label-hot')).length;
    summary.textContent = `Показано тем: ${rows.length} | Закріплених у вибірці: ${pinned}`;
}

// ---------- Кроки 3–4, 7. Завантаження списку (при відкритті сторінки і при пошуку) ----------

async function loadList(query = '') {
    const myId = ++requestId;
    try {
        const response = await fetch('api_list.php?q=' + encodeURIComponent(query));
        if (!response.ok) {
            throw new Error('Сервер відповів зі статусом ' + response.status);
        }
        const topics = await response.json();
        if (myId !== requestId) {
            return; // уже надіслано новіший запит
        }
        renderTable(topics);
    } catch (error) {
        if (myId !== requestId) {
            return;
        }
        showMessage('Не вдалося завантажити список тем: ' + error.message, true);
    }
}

searchInput.addEventListener('input', () => loadList(searchInput.value.trim()));

// ---------- Кроки 6–7. Додавання теми ----------

function setFormStatus(text, isError) {
    formStatus.hidden = text === '';
    formStatus.textContent = text;
    formStatus.className = isError ? 'flash flash-error' : 'flash';
}

function setFieldErrors(errors = {}) {
    for (const field of ['title', 'author']) {
        document.getElementById('error-' + field).textContent = errors[field] || '';
    }
}

form.addEventListener('submit', async (event) => {
    event.preventDefault();
    setFieldErrors();
    setFormStatus('', false);

    const payload = {
        title: document.getElementById('title').value.trim(),
        author: document.getElementById('author').value.trim(),
        is_pinned: document.getElementById('is_pinned').checked,
    };

    submitBtn.disabled = true;
    try {
        const response = await fetch('api_add.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });

        let data = null;
        try {
            data = await response.json();
        } catch (e) {
            // відповідь не є JSON (наприклад, фатальна помилка PHP)
        }

        if (!response.ok) {
            if (data && data.errors) {
                setFieldErrors(data.errors);
            }
            setFormStatus((data && data.error) || 'Сервер відповів зі статусом ' + response.status, true);
            return;
        }

        addTopicToList(data);
        form.reset();
    } catch (error) {
        setFormStatus('Мережева помилка: не вдалося надіслати запит.', true);
    } finally {
        submitBtn.disabled = false;
    }
});

// Нова тема з'являється зверху списку без перезавантаження (якщо підходить під поточний пошук)
function addTopicToList(topic) {
    const query = searchInput.value.trim().toLowerCase();
    if (query !== '' && !topic.author.toLowerCase().includes(query)) {
        setFormStatus('Тему додано, але вона не підпадає під поточний пошук за автором.', false);
        return;
    }

    if (tbody) {
        tbody.prepend(buildRow(topic));
        updateSummary();
    } else {
        renderTable([topic]);
    }
    setFormStatus('Тему додано.', false);
}

loadList();
