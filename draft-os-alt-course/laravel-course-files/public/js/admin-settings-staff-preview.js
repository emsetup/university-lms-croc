(function () {
    var form = document.getElementById('ap-settings-staff-preview-form');
    if (!form) {
        return;
    }

    var input = document.getElementById('ap-settings-staff-q');
    var hidden = document.getElementById('ap-settings-staff-learner-id');
    var list = document.getElementById('ap-settings-staff-results');
    var picked = document.getElementById('ap-settings-staff-picked');
    var hint = document.getElementById('ap-settings-staff-hint');
    var url = form.getAttribute('data-search-url') || '';
    var timer = null;
    var lastItems = [];
    var selectedLabel = '';

    function setHint(text, kind) {
        if (!hint) {
            return;
        }
        hint.textContent = text || '';
        hint.hidden = !text;
        hint.classList.toggle('ap-settings-learner-hint--err', kind === 'err');
    }

    function clearPick() {
        hidden.value = '';
        selectedLabel = '';
        if (picked) {
            picked.hidden = true;
            picked.textContent = '';
        }
    }

    function pickItem(item) {
        hidden.value = String(item.id);
        selectedLabel = item.label || '';
        if (picked) {
            picked.textContent = 'Выбран: ' + selectedLabel;
            picked.hidden = false;
        }
        if (input && selectedLabel) {
            input.value = selectedLabel;
        }
        list.hidden = true;
        list.innerHTML = '';
        lastItems = [];
        setHint('');
    }

    function renderResults(items) {
        list.innerHTML = '';
        lastItems = items;
        items.forEach(function (item) {
            var li = document.createElement('li');
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.textContent = item.label;
            btn.addEventListener('click', function () {
                pickItem(item);
            });
            li.appendChild(btn);
            list.appendChild(li);
        });
        list.hidden = items.length === 0;
        if (items.length === 0) {
            setHint('Никого не найдено. Уточните email или имя (в списке только сотрудники портала).', 'err');
        }
    }

    input.addEventListener('input', function () {
        var q = input.value.trim();
        if (selectedLabel && q === selectedLabel) {
            return;
        }
        clearPick();
        if (q.length < 2) {
            list.hidden = true;
            list.innerHTML = '';
            lastItems = [];
            setHint(q.length ? 'Введите ещё символ для поиска.' : '');
            return;
        }
        setHint('Поиск…');
        clearTimeout(timer);
        timer = setTimeout(function () {
            fetch(url + '?q=' + encodeURIComponent(q), {
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            })
                .then(function (r) {
                    if (!r.ok) {
                        throw new Error('HTTP ' + r.status);
                    }
                    return r.json();
                })
                .then(function (data) {
                    var items = data.items || [];
                    renderResults(items);
                    if (items.length === 1) {
                        setHint('Нажмите на строку в списке или Enter, чтобы выбрать.');
                    } else if (items.length > 1) {
                        setHint('Выберите сотрудника из списка.');
                    }
                })
                .catch(function () {
                    list.hidden = true;
                    lastItems = [];
                    setHint('Не удалось загрузить список. Обновите страницу и попробуйте снова.', 'err');
                });
        }, 250);
    });

    input.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter' || list.hidden || lastItems.length === 0) {
            return;
        }
        e.preventDefault();
        pickItem(lastItems[0]);
    });

    form.addEventListener('submit', function (e) {
        if (hidden.value) {
            setHint('');
            return;
        }
        e.preventDefault();
        setHint('Сначала выберите сотрудника из списка подсказок (клик по строке).', 'err');
        input.focus();
    });
})();
